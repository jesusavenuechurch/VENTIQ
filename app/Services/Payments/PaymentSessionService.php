<?php 
// app/Services/Payments/PaymentSessionService.php
namespace App\Services\Payments;

use App\Jobs\ChargeMobileMoney;
use App\Models\PaymentSession;
use App\Services\Payments\Contracts\WaitsForPayment;
use App\Services\Payments\DTOs\PaymentInitiationData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentSessionService
{
    public function __construct(private PaymentGatewayFactory $factory) {}

    public function initiate(
        string $payableType,
        int $payableId,
        string $method,           // 'mpesa' | 'ecocash'
        float $amount,
        string $mobileNumber,
        ?int $organizationId = null,
        ?int $initiatedBy = null,
    ): PaymentSession {
        // The reference is all a callback has to quote to find its
        // session, so it must not be guessable: it used to be the ticket
        // id and a timestamp, enough to fake a "paid" callback.
        $clientReference = strtoupper($method) . $payableId . 'T' . random_int(1_000_000_000, 9_999_999_999);

        $session = PaymentSession::create([
            'payable_type'     => $payableType,
            'payable_id'       => $payableId,
            'gateway'          => 'paylesotho',
            'client_reference' => $clientReference,
            'payment_method'   => $method,
            'amount'           => $amount,
            'status'           => 'pending',
            'organization_id'  => $organizationId,
            'initiated_by'     => $initiatedBy,
        ]);

        $driver = $this->factory->make($method);

        // EcoCash v2 answers only once the payer has decided, up to two
        // minutes later: wait for it after the response is sent.
        if ($driver instanceof WaitsForPayment && $driver->waitsForPayment()) {
            $session->update(['callback_payload' => ['push_to' => $mobileNumber, 'sent_at' => now()->toIso8601String()]]);
            ChargeMobileMoney::dispatchAfterResponse($session->id, $mobileNumber);

            return $session->fresh();
        }

        $result = $driver->initiate(new PaymentInitiationData(
            amount: $amount,
            mobileNumber: $mobileNumber,
            clientReference: $clientReference,
        ));

        $session->update([
            'transaction_id'   => $result->gatewayReference,
            'callback_payload' => ['initiate_response' => $result->raw],
            'status'           => $result->success ? 'pending' : 'failed',
        ]);

        return $session->fresh();
    }

    public function handleCallback(Request $request, string $method, PaymentSession $session): PaymentSession
    {
        $result = $this->factory->make($method)->handleCallback($request, $session);

        $session->update([
            'status'           => $result->status,
            'transaction_id'   => $result->gatewayTransactionId ?? $session->transaction_id,
            'callback_payload' => array_merge($session->callback_payload ?? [], ['callback' => $result->raw]),
        ]);

        return $session->fresh();
    }
}