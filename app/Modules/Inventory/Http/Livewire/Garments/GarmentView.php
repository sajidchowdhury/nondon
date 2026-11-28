<?php

namespace App\Modules\Inventory\Http\Livewire\Garments;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Inventory\Models\Garment;
use Livewire\Component;

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

        // Reveal purchase cost only for admin, and audit the sensitive-data
        // access (Security §4.3: restricted-data access is logged).
        if (auth()->user()?->hasRole('main_branch_admin')) {
            $this->garment->withPurchaseCost();

            app(AuditService::class)->logDataAccess('purchase_cost', 'garment', $garment->id);
        }
    }

    public function render()
    {
        return view('livewire.inventory.garments.view')
            ->layout('layouts.app');
    }
}
