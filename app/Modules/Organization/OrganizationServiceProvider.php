<?php

namespace App\Modules\Organization;

use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        // Load module migrations
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Load module views
        $this->loadViewsFrom(__DIR__.'/../resources/views/organization', 'organization');
    }
}
