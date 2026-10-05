<?php
// app/Services/Payments/Drivers/PayLesothoEcocashDriver.php
namespace App\Services\Payments\Drivers;

use App\Models\PaymentSession;
use App\Services\Payments\Contracts\{PaymentDriver, WaitsForPayment};
use App\Services\Payments\DTOs\{PaymentInitiationData, PaymentInitiationResult, PaymentStatusResult};
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayLesothoEcocashDriver extends AbstractPayLesothoDriver implements PaymentDriver, WaitsForPayment
{
    public function waitsForPayment(): bool
    {
        return (bool) config('gateways.paylesotho.ecocash.waits_for_pin', true);
    }

    /**
     * v2 (/api/v2/econet/payment): the request is held open while the
     * attendee sees the prompt, then PayLesotho answers:
     *   200 "Transaction processed successfully" (with amount) = paid
     *   415 "Insufficient Balance or wrong ecocash number..." = not paid
     * Anything else, or no answer in time, isn't trusted either way.
     */
    public function charge(PaymentInitiationData $data): PaymentStatusResult
    {
        $url  = config('gateways.paylesotho.base_url') . config('gateways.paylesotho.ecocash.endpoint', '/api/v2/econet/payment');
        $body = $this->body($data);
        $this->log('EcoCash charge: sent', ['ref' => $data->clientReference, 'url' => $url, 'body' => $body]);

        $started = microtime(true);
        try {
            $response = $this->client()
                ->timeout((int) config('gateways.paylesotho.wait_seconds', 120))
                ->post($url, $body);
        } catch (ConnectionException $e) {
            $this->log('EcoCash charge: no answer', ['ref' => $data->clientReference, 'after_s' => round(microtime(true) - $started, 1), 'error' => $e->getMessage()]);

            return new PaymentStatusResult('pending', raw: ['error' => $e->getMessage()]);
        }

        $reply = $response->json() ?? ['body' => $response->body()];
        $this->log('EcoCash charge: answer', [
            'ref' => $data->clientReference, 'http' => $response->status(),
            'after_s' => round(microtime(true) - $started, 1), 'reply' => $reply,
        ]);

        $code = (string) ($reply['status_code'] ?? $response->status());

        if ($code === '200' && $response->successful()) {
            // Paid, but only for the amount asked: anything else waits for a person.
            $amount = $reply['amount'] ?? null;
            if ($amount !== null && abs((float) $amount - $data->amount) > 0.009) {
                Log::error('PayLesotho EcoCash: paid amount differs from the amount asked', ['ref' => $data->clientReference, 'asked' => $data->amount, 'paid' => $amount]);

                return new PaymentStatusResult('pending', $reply['transaction_reference'] ?? null, $reply);
            }

            return new PaymentStatusResult('completed', $reply['reference'] ?? $reply['transaction_reference'] ?? null, $reply);
        }

        if ($code === '415') {
            return new PaymentStatusResult('failed', raw: $reply,
                message: "The payment didn't go through. The PIN may have been cancelled, the balance may be too low, or the number may not be on EcoCash.");
        }

        // A 4xx we sent badly is a definite no; a 5xx or an unknown code isn't.
        if ($response->clientError()) {
            return new PaymentStatusResult('failed', raw: $reply, message: "EcoCash didn't accept the payment request. Please try again.");
        }

        return new PaymentStatusResult('pending', raw: $reply);
    }

    /** v3: only says the push was sent. Kept for when v2 isn't used. */
    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $response = $this->client()->post(
            config('gateways.paylesotho.base_url') . '/api/v3/ecocash/deposit',
            $this->body($data),
        );

        $body = $response->json() ?? [];
        $this->log('EcoCash initiate (v3)', ['ref' => $data->clientReference, 'http' => $response->status(), 'reply' => $body]);

        return new PaymentInitiationResult(
            success: $response->successful() && (string) ($body['status_code'] ?? '') === '201',
            gatewayReference: $body['transaction_reference'] ?? null,
            message: $body['message'] ?? null,
            raw: $body,
        );
    }

    public function handleCallback(Request $request, PaymentSession $session): PaymentStatusResult
    {
        $payload = $request->all();

        return new PaymentStatusResult(
            status: $this->resolveStatus($payload),
            gatewayTransactionId: $payload['transaction_reference'] ?? $session->transaction_id,
            raw: $payload,
        );
    }

    private function body(PaymentInitiationData $data): array
    {
        return [
            'mobileNumber'     => $this->localMobileNumber($data->mobileNumber),
            'client_reference' => $data->clientReference,
            'merchantid'       => config('gateways.paylesotho.ecocash.merchant_id'),
            'merchantname'     => config('gateways.paylesotho.ecocash.merchant_name'),
            'amount'           => $this->formatAmount($data->amount),
        ];
    }
}
