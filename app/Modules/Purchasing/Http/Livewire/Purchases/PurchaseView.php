<?php

namespace App\Modules\Purchasing\Http\Livewire\Purchases;

use Livewire\Component;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Services\PurchasingService;

class PurchaseView extends Component
{
    public Purchase $purchase;

    public function mount(Purchase $purchase): void
    {
        $this->authorize('view', $purchase);

        // Load all relationships for the detail view
        $this->purchase = $purchase->load([
            'supplier',
            'fiscalYear',
            'items.product',
            'items.productSize',
            'creator',
            'confirmer',
        ]);

        // Make cost fields visible — this is safe because mount() is gated
        // by the 'view' policy (admin only).
        $this->purchase->withTotalCost();
        $this->purchase->items->each(fn ($item) => $item->withUnitCost());
    }

    public function render()
    {
        return view('livewire.purchasing.purchases.view', [
            'purchase' => $this->purchase,
        ])->layout('layouts.app');
    }
}
