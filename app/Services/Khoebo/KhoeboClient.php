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
 *
 * VENTIQ keeps two sets of books, each a company in Khoebo with its own
 * address and token: Events (services.khoebo) and Sessions
 * (services.khoebo.sessions).
 */
class KhoeboClient
{
    public const BOOKS = ['events' => 'services.khoebo', 'sessions' => 'services.khoebo.sessions'];

    public function __construct(public readonly string $book = 'events') {}

    public static function for(string $book): static
    {
        return new static($book);
    }

    /** A setting of this book: url, token, journals, products… */
    public function setting(string $key, mixed $default = null): mixed
    {
        return config((self::BOOKS[$this->book] ?? self::BOOKS['events']) . ".{$key}", $default);
    }

    public function configured(): bool
    {
        return filled($this->setting('url')) && filled($this->setting('token'));
    }

    /** The journal a payment by this method is recorded in (bank_transfer, ecocash, mpesa, cash). */
    public function journal(?string $method): ?int
    {
        $id = ($method ? $this->setting("journals.{$method}") : null) ?: $this->setting('journals.default');

        return $id ? (int) $id : null;
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
            throw new KhoeboException($this->book === 'sessions'
                ? 'Khoebo for Sessions is not set up: set SESSIONS_KHOEBO_URL and SESSIONS_KHOEBO_TOKEN.'
                : 'Khoebo is not set up: set KHOEBO_URL and KHOEBO_TOKEN.');
        }

        $request = Http::baseUrl(rtrim($this->setting('url'), '/'))
            ->withToken($this->setting('token'))
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
            // Khoebo answers {"error": {"code", "message", "details"}}; a Cloudflare block is an HTML page.
            $message = isset($body['error']['message']) ? trim(($body['error']['code'] ?? '') . ': ' . $body['error']['message'], ': ')
                : ($body['message'] ?? null);
            $message ??= (str_contains($response->body(), 'Cloudflare')
                ? 'blocked by Khoebo\'s firewall (Cloudflare): ask Khoebo to allow this server'
                : 'HTTP ' . $response->status());
            Log::warning('Khoebo refused a request', ['book' => $this->book, 'path' => $path, 'status' => $response->status(), 'body' => $body]);
            throw new KhoeboException("Khoebo: {$message}", $response->status(), $body);
        }

        return (array) ($response->json('data') ?? $response->json());
    }
}
