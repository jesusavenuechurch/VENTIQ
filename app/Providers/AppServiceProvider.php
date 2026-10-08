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

        // Public registrations: generous per connection (a venue's wifi or
        // a phone network can put many people behind one address), tight
        // per phone number, so one person can't hold places in bulk.
        \Illuminate\Support\Facades\RateLimiter::for('registrations', function (\Illuminate\Http\Request $request) {
            $tooMany = fn () => back()->withInput()->with('error', 'Too many registrations from here in a short time. Please wait a few minutes and try again.');

            return [
                \Illuminate\Cache\RateLimiting\Limit::perHour(60)->by('ip:' . $request->ip())->response($tooMany),
                \Illuminate\Cache\RateLimiting\Limit::perHour(6)->by('phone:' . preg_replace('/^266/', '', preg_replace('/\D/', '', (string) $request->input('phone'))))->response($tooMany),
            ];
        });
    }
}