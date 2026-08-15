<?php 
// app/Services/Payments/PaymentSessionService.php
namespace App\Services\Payments;

use App\Models\PaymentSession;
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
        $clientReference = strtoupper($method) . $payableId . 'T' . time();

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

        $result = $this->factory->make($method)->initiate(new PaymentInitiationData(
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