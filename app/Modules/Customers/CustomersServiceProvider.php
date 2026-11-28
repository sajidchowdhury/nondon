<?php

namespace App\Modules\Customers;

use Illuminate\Support\ServiceProvider;

class CustomersServiceProvider extends ServiceProvider
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
        // Load module migrations.
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
