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
            config('gateways.paylesotho.base_url') . '/api/v2/mpesa-deposit/payment',
            [
                'merchantName'    => config('gateways.paylesotho.merchant_name'),
                'mobileNumber'    => $data->mobileNumber,
                'clientReference' => $data->clientReference,
                'amount'          => number_format($data->amount, 2, '.', ''),
                'merchantNumber'  => config('gateways.paylesotho.mpesa.merchant_number'),
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