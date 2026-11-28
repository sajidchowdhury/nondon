<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Http\Livewire\Prices\PriceManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
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

        // Register Livewire component aliases so they can be referenced
        // by dot-notation in Blade views (e.g., <livewire:catalog.prices.price-manager />).
        Livewire::component('catalog.prices.price-manager', PriceManager::class);
    }
}
