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

    // PayLesotho expects the bare local number (e.g. "62552155"), not E.164
    // — a request built with a "+266" prefix still comes back 201 "Transaction
    // Initiated" but silently never reaches the handset. Strip any leading
    // "266" (with or without a "+") so callers can keep passing E.164 numbers
    // (that's what the rest of the app stores/displays) without needing to
    // know about this quirk at every call site.
    protected function localMobileNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        if (str_starts_with($digits, '266')) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }

    // Same story for amount: "1.00" is accepted (201) but PayLesotho's own
    // working example sends whole amounts as "1", not "1.00" — trim
    // insignificant trailing zeros/decimal point rather than always padding
    // to 2dp.
    protected function formatAmount(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
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