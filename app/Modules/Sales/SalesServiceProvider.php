<?php

namespace App\Modules\Sales;

use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
