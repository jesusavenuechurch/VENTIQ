<?php

namespace App\Services\Tickets;

use App\Models\{Client, Event, EventTier, Ticket, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use App\Support\{Phone, TierCapacity};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Log};
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
        private TicketActivationService $activation,
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
        $ticket = DB::transaction(function () use ($event, $tier, $row, $by, $sendWhatsApp) {
            $tier = EventTier::whereKey($tier->id)->lockForUpdate()->first();
            if (!TierCapacity::hasRoom($tier)) {
                throw new InvalidArgumentException("{$tier->tier_name} is full.");
            }

            $client = Client::firstOrCreate(
                ['phone' => $row['phone'], 'organization_id' => $event->organization_id],
                ['full_name' => $row['full_name'], 'email' => $row['email'], 'created_by' => $by->id],
            );

            $ticket = Ticket::create([
                'event_id'           => $event->id,
                'client_id'          => $client->id,
                'event_tier_id'      => $tier->id,
                'created_by'         => $by->id,
                'status'             => 'pending',
                'payment_status'     => 'pending',
                'amount'             => $tier->price,
                'admissions'         => max(1, (int) ($tier->quantity_per_purchase ?? 1)),
                'has_whatsapp'       => $sendWhatsApp,
                'preferred_delivery' => $sendWhatsApp ? 'both' : 'email',
            ]);
            TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => $tier->price, 'status' => 'pending', 'payment_type' => 'full']);

            return $ticket;
        });

        $this->activation->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, $method, 'Guest list import', $by->id);
        $ticket->generateQrCode();
    }
}
