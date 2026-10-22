<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Tickets\GuestListImport;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Import a guest list: upload, see every row checked, then confirm.
 * The checked rows wait in the cache for half an hour between the two.
 */
class GuestListController extends Controller
{
    public const METHODS = ['cash' => 'Cash', 'ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer'];

    public function create(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        // After "Check the list": the checked rows, kept under ?token= for half an hour.
        $token = (string) $request->query('token');
        $checked = $token !== '' ? Cache::get($this->key($request, $token)) : null;
        $tier = $checked ? $event->tiers()->find($checked['event_tier_id']) : null;

        return Inertia::render('Organizer/GuestList', [
            'event'   => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status, 'feesSponsored' => (bool) $event->fees_sponsored],
            'tiers'   => $event->tiers()->where('is_active', true)->orderBy('price')->get()->map(fn ($t) => [
                'id'     => $t->id,
                'name'   => $t->tier_name,
                'price'  => (float) $t->price,
                'admits' => max(1, (int) ($t->quantity_per_purchase ?? 1)),
            ])->values(),
            'methods' => self::METHODS,
            'perPerson' => (float) config('constants.fees.operational_per_person'),
            'preview' => $checked && $tier ? [
                'token'        => $token,
                'ok'           => $checked['ok'],
                'problems'     => $checked['problems'],
                'rows'         => collect($checked['rows'])->map(fn ($r) => Arr::only($r, ['row', 'full_name', 'phone', 'problem']))->values(),
                'tier'         => ['name' => $tier->tier_name, 'price' => (float) $tier->price],
                'mode'         => $checked['mode'],
                'sendWhatsapp' => $checked['send_whatsapp'],
            ] : null,
            'meta'    => ['title' => "Import guest list · {$event->name} | VENTIQ"],
        ]);
    }

    public function check(Request $request, Event $event, GuestListImport $import)
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'file'          => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'event_tier_id' => ['required', Rule::exists('event_tiers', 'id')->where('event_id', $event->id)->where('is_active', true)],
            'mode'          => ['required', Rule::in([GuestListImport::MODE_COMP, GuestListImport::MODE_PAID])],
            'method'        => ['nullable', Rule::in(array_keys(self::METHODS))],
            'reason'        => ['nullable', 'string', 'max:255'],
            'send_whatsapp' => ['nullable', 'boolean'],
        ], ['file.mimes' => 'Upload a CSV or Excel file.']);

        try {
            $result = $import->check($request->file('file'), $event);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['file' => 'We couldn\'t read that file. Save it as CSV or .xlsx with a header row (full_name, phone, email) and try again.']);
        }

        if (!$result['rows']) {
            return back()->withErrors(['file' => 'That file has no rows under its header.']);
        }

        $token = Str::random(32);
        Cache::put($this->key($request, $token), $result + [
            'event_tier_id' => (int) $data['event_tier_id'],
            'mode'          => $data['mode'],
            'method'        => $data['method'] ?? 'cash',
            'reason'        => $data['reason'] ?? null,
            'send_whatsapp' => $request->boolean('send_whatsapp'),
        ], now()->addMinutes(30));

        return redirect()->route('organizer.events.guests.create', [$event, 'token' => $token]);
    }

    public function store(Request $request, Event $event, GuestListImport $import)
    {
        $this->authorizeEvent($request, $event);
        $key = $this->key($request, (string) $request->input('token'));
        $checked = Cache::pull($key);

        if (!$checked) {
            return redirect()->route('organizer.events.guests.create', $event)
                ->withErrors(['file' => 'That check has expired. Upload the file again.']);
        }

        $tier = $event->tiers()->findOrFail($checked['event_tier_id']);
        $result = $import->import($event, $tier, $checked['rows'], $checked['mode'], $request->user(),
            $checked['send_whatsapp'], $checked['reason'], $checked['method']);

        $message = "{$result['created']} " . Str::plural('ticket', $result['created']) . ' created';
        if ($result['skipped']) {
            $message .= '; ' . count($result['skipped']) . ' skipped (' . collect($result['skipped'])->unique()->first() . ')';
        }

        return redirect()->route('organizer.events.attendees', $event)->with('status', $message . '.');
    }

    /** A CSV with the expected header and an example row. */
    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['full_name', 'phone', 'email']);
            fputcsv($out, ['Lerato Mokoena', '5949 4756', 'lerato@example.com']);
            fclose($out);
        }, 'ventiq-guest-list.csv', ['Content-Type' => 'text/csv']);
    }

    private function key(Request $request, string $token): string
    {
        return 'guest-list:' . $request->user()->id . ':' . $token;
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id && $event->kind === \App\Models\Event::KIND_EVENT, 404);
    }
}
