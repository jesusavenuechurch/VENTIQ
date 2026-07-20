<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MopayService
{
    private string $apiKey;
    private string $baseUrl = 'https://mopay.co.ls';

    public function __construct()
    {
        $this->apiKey = config('services.mopay.api_key');
    }

    /**
     * Create a payment session with MoPay.
     * Returns the full response array on success, throws on failure.
     */
    public function createSession(array $data): array
    {
        // MoPay reference must be alphanumeric only — strip everything else
        $cleanReference = preg_replace('/[^A-Za-z0-9]/', '', $data['reference']);

        $payload = [
            'amount'        => number_format((float) $data['amount'], 2, '.', ''),
            'reference'     => $cleanReference,
            'redirectUrl'   => $data['redirectUrl'],
            'description'   => $data['description'] ?? null,
            'customerEmail' => $data['customerEmail'] ?? null,
            'customerName'  => $data['customerName'] ?? null,
        ];

        Log::info('MoPay: creating session', ['reference' => $cleanReference, 'amount' => $payload['amount']]);

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/api/external/payment", $payload);

        if (!$response->successful()) {
            Log::error('MoPay: HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('MoPay request failed: HTTP ' . $response->status());
        }

        $result = $response->json();

        if (empty($result['success'])) {
            Log::error('MoPay: session creation failed', $result);
            throw new \RuntimeException('MoPay error: ' . ($result['error'] ?? 'Unknown error'));
        }

        Log::info('MoPay: session created', ['sessionId' => $result['sessionId']]);

        return $result;
    }

    /**
     * Verify a session server-side. Always call this before trusting redirect params.
     */
    public function verifySession(string $sessionId): array
    {
        Log::info('MoPay: verifying session', ['sessionId' => $sessionId]);

        $response = Http::timeout(30)
            ->get("{$this->baseUrl}/api/external/session/v1/{$sessionId}");

        if (!$response->successful()) {
            Log::error('MoPay: verification HTTP error', ['status' => $response->status()]);
            throw new \RuntimeException('MoPay verification failed: HTTP ' . $response->status());
        }

        $result = $response->json();

        if (empty($result['success'])) {
            Log::error('MoPay: verification failed', $result);
            throw new \RuntimeException('MoPay verification error: ' . ($result['error'] ?? 'Unknown'));
        }

        return $result['session'] ?? $result;
    }
}