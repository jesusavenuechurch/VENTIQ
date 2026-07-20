<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Event;
use App\Models\Ticket;
use App\Observers\EventObserver;
use App\Observers\TicketObserver;
use App\Models\OrganizationPackage;
use App\Observers\OrganizationPackageObserver;
use App\Contracts\AI\AIProvider;
use App\Services\AI\AIService;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenRouterProvider;
// use App\Services\AI\Providers\ClaudeProvider; // ← uncomment when you build this
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProvider::class, function () {
            return match (config('ai.default')) {
                'openrouter' => new OpenRouterProvider(),
                // 'claude'     => new ClaudeProvider(),
                default      => new OllamaProvider(),
            };
        });

        $this->app->singleton(AIService::class, fn ($app) => new AIService(
            $app->make(AIProvider::class)
        ));
    }

    public function boot(): void
    {
        Event::observe(EventObserver::class);
        Ticket::observe(TicketObserver::class);
        OrganizationPackage::observe(OrganizationPackageObserver::class);
    }
}