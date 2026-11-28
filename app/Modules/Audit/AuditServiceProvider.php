<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Audit\Listeners\AuditListener;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        // Register AuditService as singleton
        $this->app->singleton(
            AuditService::class,
            fn ($app) => new AuditService
        );
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        // Load module migrations
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Register the audit event listener
        // All AuditableEvent instances are picked up by AuditListener
        // Using closure-based listener for synchronous, immediate execution
        Event::listen(AuditableEvent::class, function (AuditableEvent $event) {
            app(AuditListener::class)->handle($event);
        });
    }
}
