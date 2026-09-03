<?php
// app/Services/Payments/Drivers/PayLesothoEcocashDriver.php
namespace App\Services\Payments\Drivers;

use App\Models\PaymentSession;
use App\Services\Payments\Contracts\PaymentDriver;
use App\Services\Payments\DTOs\{PaymentInitiationData, PaymentInitiationResult, PaymentStatusResult};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayLesothoEcocashDriver extends AbstractPayLesothoDriver implements PaymentDriver
{
    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $response = $this->client()->post(
            config('gateways.paylesotho.base_url') . '/api/v3/ecocash/deposit',
            [
                'mobileNumber'     => $this->localMobileNumber($data->mobileNumber),
                'client_reference' => $data->clientReference,
                'merchantid'       => config('gateways.paylesotho.ecocash.merchant_id'),
                'callback_url'     => $this->callbackUrlFor('ecocash'),
                'merchantname'     => config('gateways.paylesotho.ecocash.merchant_name'),
                'amount'           => $this->formatAmount($data->amount),
            ]
        );

        $body = $response->json() ?? [];
        Log::info('PayLesotho EcoCash initiate', ['ref' => $data->clientReference, 'response' => $body]);

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