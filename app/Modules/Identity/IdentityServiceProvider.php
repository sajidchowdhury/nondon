<?php

namespace App\Modules\Identity;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

class IdentityServiceProvider extends ServiceProvider
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

        // Load module views (if any)
        $this->loadViewsFrom(__DIR__.'/../resources/views/livewire/auth', 'identity');

        // Load module routes (if any, besides main routes/web.php)
        // $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        // Register Livewire component aliases so they can be referenced
        // by dot-notation in Blade views (e.g., <livewire:identity.roles.role-assignment />).
        Livewire::component('identity.roles.role-assignment', \App\Modules\Identity\Http\Livewire\Roles\RoleAssignment::class);
        Livewire::component('identity.roles.permission-override', \App\Modules\Identity\Http\Livewire\Roles\PermissionOverride::class);

        // Register module-specific configurations
        $this->registerModuleConfig();
    }

    /**
     * Register module-specific configuration.
     */
    protected function registerModuleConfig(): void
    {
        // The auth config already references our User model.
        // Additional identity module config can be added here.
    }
}
