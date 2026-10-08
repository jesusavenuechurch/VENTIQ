<?php

namespace App\Console\Commands;

use App\Contracts\AI\AIProvider;
use Illuminate\Console\Command;

/**
 * Which AI provider VENTIQ is really using, and whether it answers. If this
 * says OpenRouter after AI_PROVIDER was changed, the config is cached
 * (php artisan config:clear) or the queue workers are still running the
 * old settings (php artisan queue:restart).
 */
class AiStatus extends Command
{
    protected $signature = 'ai:status';
    protected $description = 'Show which AI provider is in use and check that it answers';

    public function handle(): int
    {
        $provider = app(AIProvider::class);
        $env = env('AI_PROVIDER');

        $this->line('AI_PROVIDER in .env:   ' . (app()->configurationIsCached() ? '(not read: config is cached)' : ($env ?? '(not set)')));
        $this->line('Provider in use:       ' . config('ai.default') . ' → ' . $provider->name());
        if (config('ai.default') === 'ollama') {
            $this->line('Ollama address:        ' . config('ai.ollama.url'));
        }
        if (app()->configurationIsCached()) {
            $this->warn('Config is cached: .env changes take effect only after `php artisan config:clear` (or config:cache).');
        }

        $this->line('Checking it answers…');
        if ($provider->isAvailable()) {
            $this->info('It answers.');
        } else {
            $this->error('No answer. Check the address, that Ollama listens beyond localhost (OLLAMA_HOST=0.0.0.0), and that this server is on the tailnet.');
        }

        $this->line('Queued AI jobs run in the queue worker: after changing .env, run `php artisan queue:restart`.');

        return self::SUCCESS;
    }
}
