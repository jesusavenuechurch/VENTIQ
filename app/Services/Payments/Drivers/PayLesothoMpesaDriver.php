<?php 
// app/Services/Payments/Drivers/PayLesothoMpesaDriver.php
namespace App\Services\Payments\Drivers;

use App\Models\PaymentSession;
use App\Services\Payments\Contracts\PaymentDriver;
use App\Services\Payments\DTOs\{PaymentInitiationData, PaymentInitiationResult, PaymentStatusResult};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayLesothoMpesaDriver extends AbstractPayLesothoDriver implements PaymentDriver
{
    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $response = $this->client()->post(
            config('gateways.paylesotho.base_url') . '/api/v1/vcl/payment',
            [
                'amount'          => $this->formatAmount($data->amount),
                'mobileNumber'    => $this->localMobileNumber($data->mobileNumber),
                'merchantid'      => config('gateways.paylesotho.mpesa.merchant_number'),
                'merchantname'    => config('gateways.paylesotho.mpesa.merchant_name'),
                'clientReference' => $data->clientReference,
                'callback_url'    => $this->callbackUrlFor('mpesa'),
            ]
        );

        $body = $response->json() ?? [];
        Log::info('PayLesotho M-Pesa initiate', ['ref' => $data->clientReference, 'response' => $body]);

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
}