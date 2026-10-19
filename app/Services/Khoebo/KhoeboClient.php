<?php

namespace App\Services\Khoebo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{Http, Log};

/**
 * Talks to Khoebo, VENTIQ's accounting system.
 *
 * Every create sends an Idempotency-Key made from what it is for (e.g.
 * ventiq-customer-org-12), so a request retried after a timeout returns
 * the record made the first time instead of a second one.
 */
class KhoeboClient
{
    public function configured(): bool
    {
        return filled(config('services.khoebo.url')) && filled(config('services.khoebo.token'));
    }

    /** @return array the record Khoebo returns (its "data") */
    public function create(string $path, array $body, string $idempotencyKey): array
    {
        return $this->send('post', $path, $body, $idempotencyKey);
    }

    /** @return array the record or list Khoebo returns (its "data") */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    private function send(string $method, string $path, array $data, ?string $idempotencyKey = null): array
    {
        if (! $this->configured()) {
            throw new KhoeboException('Khoebo is not set up: set KHOEBO_URL and KHOEBO_TOKEN.');
        }

        $request = Http::baseUrl(rtrim(config('services.khoebo.url'), '/'))
            ->withToken(config('services.khoebo.token'))
            ->acceptJson()
            ->timeout(20)
            // Only a lost connection is retried; the idempotency key keeps
            // a retried create from making a second record.
            ->retry(2, 1000, fn ($e) => $e instanceof ConnectionException, throw: false);

        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $request->{$method}('/' . ltrim($path, '/'), $data);
        } catch (ConnectionException $e) {
            Log::warning('Khoebo unreachable', ['path' => $path, 'error' => $e->getMessage()]);
            throw new KhoeboException("Couldn't reach Khoebo: " . $e->getMessage());
        }

        if (! $response->successful()) {
            $body = (array) $response->json();
            // A Cloudflare block comes back as an HTML page, not JSON.
            $message = $body['message'] ?? (str_contains($response->body(), 'Cloudflare')
                ? 'blocked by Khoebo\'s firewall (Cloudflare): ask Khoebo to allow this server'
                : 'HTTP ' . $response->status());
            Log::warning('Khoebo refused a request', ['path' => $path, 'status' => $response->status(), 'body' => $body]);
            throw new KhoeboException("Khoebo: {$message}", $response->status(), $body);
        }

        return (array) ($response->json('data') ?? $response->json());
    }
}
