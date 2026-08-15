<?php 
// app/Services/Payments/Drivers/AbstractPayLesothoDriver.php
namespace App\Services\Payments\Drivers;

use Illuminate\Support\Facades\Http;

abstract class AbstractPayLesothoDriver
{
    protected function client()
    {
        return Http::withToken(config('gateways.paylesotho.token'))
            ->acceptJson()
            ->timeout(20);
    }

    protected function callbackUrlFor(string $method): string
    {
        return route('paylesotho.callback', ['method' => $method]);
    }

    /**
     * TODO: this is a best-guess mapping until we see a real callback
     * payload from PayLesotho. Run a live test transaction, dump the
     * raw request that hits /payment/paylesotho/callback/{method},
     * and tighten this against the actual field names.
     */
    protected function resolveStatus(array $payload): string
    {
        $raw = strtolower($payload['status'] ?? $payload['transaction_status'] ?? '');

        return match (true) {
            in_array($raw, ['completed', 'success', 'successful']) => 'completed',
            in_array($raw, ['failed', 'declined', 'cancelled', 'expired']) => 'failed',
            default => 'pending',
        };
    }
}