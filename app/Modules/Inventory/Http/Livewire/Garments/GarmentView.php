<?php

namespace App\Modules\Inventory\Http\Livewire\Garments;

use Livewire\Component;
use App\Modules\Inventory\Models\Garment;

class GarmentView extends Component
{
    public Garment $garment;

    public function mount(Garment $garment): void
    {
        $this->authorize('view', $garment);
        $this->garment = $garment->load([
            'product',
            'productSize',
            'currentBranch',
            'currentLocation',
            'fiscalYear',
        ]);
    }

    public function render()
    {
        // Reveal purchase cost only for admin
        if (auth()->user()?->hasRole('main_branch_admin')) {
            $this->garment->withPurchaseCost();
        }

        return view('livewire.inventory.garments.view')
            ->layout('layouts.app');
    }
}
