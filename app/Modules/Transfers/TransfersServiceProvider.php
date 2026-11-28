<?php

namespace App\Modules\Transfers;

use App\Modules\Transfers\Http\Livewire\Transfers\TransferCreate;
use App\Modules\Transfers\Http\Livewire\Transfers\TransferList;
use App\Modules\Transfers\Http\Livewire\Transfers\TransferView;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class TransfersServiceProvider extends ServiceProvider
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

        Livewire::component('transfers.transfer-list', TransferList::class);
        Livewire::component('transfers.transfer-create', TransferCreate::class);
        Livewire::component('transfers.transfer-view', TransferView::class);
    }
}
