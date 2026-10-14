<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Tickets\GuestListImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        return view('organizer.guest-list', [
            'event'   => $event,
            'tiers'   => $event->tiers()->where('is_active', true)->orderBy('price')->get(),
            'methods' => self::METHODS,
            'preview' => null,
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
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors(['file' => 'We couldn\'t read that file. Save it as CSV or .xlsx with a header row (full_name, phone, email) and try again.']);
        }

        if (!$result['rows']) {
            return back()->withInput()->withErrors(['file' => 'That file has no rows under its header.']);
        }

        $token = Str::random(32);
        Cache::put($this->key($request, $token), $result + [
            'event_tier_id' => (int) $data['event_tier_id'],
            'mode'          => $data['mode'],
            'method'        => $data['method'] ?? 'cash',
            'reason'        => $data['reason'] ?? null,
            'send_whatsapp' => $request->boolean('send_whatsapp'),
        ], now()->addMinutes(30));

        $tier = $event->tiers()->findOrFail($data['event_tier_id']);

        return view('organizer.guest-list', [
            'event'   => $event,
            'tiers'   => collect(),
            'methods' => self::METHODS,
            'preview' => $result + ['token' => $token, 'tier' => $tier, 'mode' => $data['mode'], 'send_whatsapp' => $request->boolean('send_whatsapp')],
        ]);
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
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);
    }
}
