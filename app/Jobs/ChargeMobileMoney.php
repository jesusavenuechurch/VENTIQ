<?php

namespace App\Jobs;

use App\Models\PaymentSession;
use App\Services\Payments\Contracts\WaitsForPayment;
use App\Services\Payments\DTOs\PaymentInitiationData;
use App\Services\Payments\{PaymentCompletion, PaymentGatewayFactory};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Sends the push and waits (up to ~2 minutes) for PayLesotho to say how it
 * ended, then records it. Run after the response is sent, so the attendee's
 * page isn't held open and no queue worker is needed; the page follows the
 * session's status meanwhile.
 */
class ChargeMobileMoney implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;   // a second try would be a second push

    public function __construct(public int $sessionId, public string $mobileNumber) {}

    public function handle(PaymentGatewayFactory $gateways, PaymentCompletion $completion): void
    {
        $session = PaymentSession::find($this->sessionId);
        if (!$session || $session->status !== 'pending') {
            return;
        }

        @set_time_limit((int) config('gateways.paylesotho.wait_seconds', 120) + 30);

        $driver = $gateways->make($session->payment_method);
        abort_unless($driver instanceof WaitsForPayment, 500, 'Driver cannot wait for payment');

        $result = $driver->charge(new PaymentInitiationData(
            amount: (float) $session->amount,
            mobileNumber: $this->mobileNumber,
            clientReference: $session->client_reference,
        ));

        // Someone may have decided this session by hand while we waited.
        $session->refresh();
        $payload = array_merge($session->callback_payload ?? [], ['charge' => $result->raw, 'answered_at' => now()->toIso8601String()]);

        if ($result->status === 'completed') {
            // The money moved, whatever was decided meanwhile.
            if (!$session->isCompleted()) {
                $session->update(['status' => 'completed', 'transaction_id' => $result->gatewayTransactionId ?? $session->transaction_id, 'callback_payload' => $payload]);
                $completion->completed($session->fresh());
            }

            return;
        }

        if ($session->status !== 'pending') {
            return;
        }

        $session->update($result->status === 'failed'
            ? ['status' => 'failed', 'callback_payload' => $payload + ['failure' => $result->message]]
            // No clear answer: stays pending, for a person to check.
            : ['callback_payload' => $payload + ['no_answer' => true]]);
    }
}
