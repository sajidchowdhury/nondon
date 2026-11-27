<?php

namespace App\Modules\Purchasing;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class PurchasingServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
