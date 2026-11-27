<?php

namespace App\Modules\Purchasing\Http\Livewire\Receiving;

use Livewire\Component;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;

class GoodsReceiptView extends Component
{
    public GoodsReceipt $receipt;

    public function mount(GoodsReceipt $receipt): void
    {
        // Goods receipts are admin-only (same as purchases)
        $this->authorize('viewAny', Purchase::class);

        $this->receipt = $receipt->load([
            'purchase.supplier',
            'purchase.fiscalYear',
            'receiver',
            'garments.product',
            'garments.productSize',
            'garments.fiscalYear',
        ]);

        // Make garment purchase_cost visible — safe because this view is admin-only
        $this->receipt->garments->each(fn ($g) => $g->withPurchaseCost());
    }

    public function render()
    {
        return view('livewire.purchasing.receiving.view', [
            'receipt' => $this->receipt,
        ])->layout('layouts.app');
    }
}
