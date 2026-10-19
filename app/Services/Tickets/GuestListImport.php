<?php

namespace App\Services\Tickets;

use App\Models\{Event, EventTier, User};
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A guest list from a spreadsheet, in two steps: read and check every row
 * (nothing is created), then create tickets for the rows that passed.
 *
 * Every row needs a name and a phone number we can reach. A row without
 * one is reported, never guessed: the Filament import made up numbers and
 * sent WhatsApp tickets to them. Clients are matched by phone within this
 * organization only.
 *
 * Tickets are complimentary, or already paid to the organizer (recorded
 * as organizer-direct payments at the ticket price, so their fees are
 * invoiced like any other).
 */
class GuestListImport
{
    public const MAX_ROWS = 500;
    public const MODE_COMP = 'complimentary';
    public const MODE_PAID = 'paid_direct';

    public function __construct(
        private ComplimentaryTicketService $comps,
        private DirectSaleService $sales,
    ) {}

    /**
     * @return array{rows: array<int, array{row: int, full_name: ?string, phone: ?string, email: ?string, problem: ?string}>, ok: int, problems: int}
     */
    public function check(UploadedFile $file, Event $event): array
    {
        $sheet = Excel::toCollection(new class implements WithHeadingRow {}, $file)->first() ?? collect();

        if ($sheet->count() > self::MAX_ROWS) {
            throw new InvalidArgumentException('That file has ' . $sheet->count() . ' rows. Import up to ' . self::MAX_ROWS . ' at a time.');
        }

        $existing = $event->tickets()->whereNotIn('tickets.status', ['cancelled', 'expired'])
            ->join('clients', 'clients.id', '=', 'tickets.client_id')->pluck('clients.phone')->flip();
        $seen = [];

        $rows = $sheet->values()->map(function (Collection $raw, int $i) use ($existing, &$seen) {
            $get = fn (array $keys) => collect($keys)->map(fn ($k) => trim((string) ($raw[$k] ?? '')))->first(fn ($v) => $v !== '');

            $name = $get(['full_name', 'name', 'fullname', 'guest']);
            $rawPhone = $get(['phone', 'phone_number', 'mobile', 'whatsapp', 'cell']);
            $email = $get(['email', 'email_address']);
            $phone = Phone::normalize($rawPhone);

            $problem = match (true) {
                !$name                          => 'No name',
                !$rawPhone                      => 'No phone number',
                !$phone                         => "\"{$rawPhone}\" isn't a phone number we can use",
                $email && !filter_var($email, FILTER_VALIDATE_EMAIL) => "\"{$email}\" isn't an email address",
                isset($seen[$phone])            => "Same number as row {$seen[$phone]}",
                isset($existing[$phone])        => 'Already has a ticket for this event',
                default                         => null,
            };

            if ($phone && !isset($seen[$phone])) {
                $seen[$phone] = $i + 2;
            }

            return ['row' => $i + 2, 'full_name' => $name, 'phone' => $phone ?? $rawPhone, 'email' => $email ?: null, 'problem' => $problem];
        })
        // Fully blank lines at the end of a sheet aren't rows.
        ->reject(fn ($r) => !$r['full_name'] && !$r['phone'] && !$r['email'])
        ->values()
        ->all();

        $problems = count(array_filter($rows, fn ($r) => $r['problem']));

        return ['rows' => $rows, 'ok' => count($rows) - $problems, 'problems' => $problems];
    }

    /**
     * Create tickets for the rows that passed the check.
     *
     * @return array{created: int, skipped: array<int, string>}
     */
    public function import(Event $event, EventTier $tier, array $rows, string $mode, User $by, bool $sendWhatsApp, ?string $reason = null, string $method = 'cash'): array
    {
        $created = 0;
        $skipped = [];

        foreach (array_filter($rows, fn ($r) => !$r['problem']) as $row) {
            try {
                $mode === self::MODE_COMP
                    ? $this->comps->issue($event, $tier, $row, $by, $reason ?: 'Guest list', $sendWhatsApp)
                    : $this->paid($event, $tier, $row, $by, $sendWhatsApp, $method);
                $created++;
            } catch (InvalidArgumentException $e) {
                // Most likely the tier filled up part way through.
                $skipped[$row['row']] = $e->getMessage();
            }
        }

        Log::info("Guest list imported for event {$event->id} by user {$by->id}", ['mode' => $mode, 'created' => $created, 'skipped' => count($skipped)]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function paid(Event $event, EventTier $tier, array $row, User $by, bool $sendWhatsApp, string $method): void
    {
        $this->sales->sell($event, $tier, $row, $by, $method, 'Guest list import', $sendWhatsApp);
    }
}
