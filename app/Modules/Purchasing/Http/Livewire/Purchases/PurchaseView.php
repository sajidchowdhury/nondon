<?php

namespace App\Modules\Purchasing\Http\Livewire\Purchases;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Purchasing\Models\Purchase;
use Livewire\Component;

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
            'goodsReceipts.receiver',
        ]);

        // Make cost fields visible — this is safe because mount() is gated
        // by the 'view' policy (admin only).
        $this->purchase->withTotalCost();
        $this->purchase->items->each(fn ($item) => $item->withUnitCost());

        // Security §4.3: access to restricted purchase cost data is itself
        // audit-logged (who viewed which purchase, when, from where).
        app(AuditService::class)->logDataAccess('purchase_cost', 'purchase', $purchase->id);
    }

    public function render()
    {
        return view('livewire.purchasing.purchases.view', [
            'purchase' => $this->purchase,
        ])->layout('layouts.app');
    }
}
