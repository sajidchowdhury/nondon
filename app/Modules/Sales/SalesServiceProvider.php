<?php

namespace App\Modules\Sales;

use App\Modules\Sales\Http\Livewire\POS\Checkout;
use App\Modules\Sales\Http\Livewire\POS\PointOfSale;
use App\Modules\Sales\Http\Livewire\POS\PrintReceipt;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        // Register the POS Livewire components under explicit kebab aliases
        // so they resolve both when mounted via <livewire:checkout /> /
        // <livewire:point-of-sale /> and when targeted by dispatch()->to().
        Livewire::component('pos.point-of-sale', PointOfSale::class);
        Livewire::component('pos.checkout', Checkout::class);
        Livewire::component('pos.print-receipt', PrintReceipt::class);
        // Short aliases for inline mounting in the POS view.
        Livewire::component('checkout', Checkout::class);
    }
}
