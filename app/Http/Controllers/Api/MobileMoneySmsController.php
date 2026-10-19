<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileMoney\MobileMoneyInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Where the phone holding VENTIQ's merchant SIM forwards each SMS. Any SMS
 * forwarder app works: it posts the sender and text (JSON or a form), and
 * the token VENTIQ gave it. See docs/mobile-money-sms.md.
 */
class MobileMoneySmsController extends Controller
{
    public function store(Request $request, MobileMoneyInbox $inbox)
    {
        $token = (string) config('services.mobile_money.token');
        abort_if($token === '', 503, 'SMS forwarding is not set up.');

        $given = (string) ($request->header('X-Ventiq-Token') ?: $request->query('token', ''));
        abort_unless(hash_equals($token, $given), 401);

        $sender = trim((string) $this->first($request, ['from', 'sender', 'phone', 'address']));
        $body   = trim((string) $this->first($request, ['text', 'message', 'body', 'content']));
        abort_if($sender === '' || $body === '', 422, 'Send the sender (from) and the message (text).');

        // Only the mobile money senders are kept: the SIM's other SMS stay private.
        $provider = MobileMoneyInbox::providerFor($sender);
        if (!$provider) {
            return response()->json(['status' => 'ignored'], 202);
        }

        $message = $inbox->receive(
            provider: $provider,
            sender: $sender,
            body: mb_substr($body, 0, 2000),
            receivedAt: $this->receivedAt($this->first($request, ['receivedStamp', 'received_at', 'sentStamp', 'timestamp'])),
            device: mb_substr((string) $this->first($request, ['device', 'sim']), 0, 100) ?: null,
            raw: $request->except(['token']),
        );

        return response()->json(['status' => $message->status, 'id' => $message->id]);
    }

    private function first(Request $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (filled($request->input($key))) {
                return $request->input($key);
            }
        }

        return null;
    }

    /** The phone's time for the SMS (epoch milliseconds or seconds, or a date), else now. */
    private function receivedAt(mixed $value): Carbon
    {
        try {
            $at = match (true) {
                is_numeric($value) && (float) $value > 1e11 => Carbon::createFromTimestampMs((int) $value),
                is_numeric($value)                          => Carbon::createFromTimestamp((int) $value),
                filled($value)                              => Carbon::parse((string) $value),
                default                                     => now(),
            };
        } catch (\Throwable) {
            return now();
        }

        // A phone clock far off is not trusted.
        return $at->between(now()->subDays(30), now()->addMinutes(10)) ? $at->setTimezone(config('app.timezone')) : now();
    }
}
