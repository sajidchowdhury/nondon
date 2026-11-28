<?php

namespace App\Modules\Purchasing\Http\Livewire\Receiving;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Purchasing\Models\GoodsReceipt;
use Livewire\Component;

class GoodsReceiptView extends Component
{
    public GoodsReceipt $receipt;

    public function mount(GoodsReceipt $receipt): void
    {
        // Admins see all receipts; Main-Branch receiving staff (branch
        // supervisors assigned to the Main Branch) can view receipts —
        // the records of their own receiving workflow (WF-2).
        $this->authorize('view-goods-receipts');

        $this->receipt = $receipt->load([
            'purchase.supplier',
            'purchase.fiscalYear',
            'receiver',
            'garments.product',
            'garments.productSize',
            'garments.fiscalYear',
        ]);

        // Make garment purchase_cost visible — only for admins. Supplier
        // identity and cost stay hidden for receiving staff (Security §4.3);
        // the view renders those columns conditionally for admins only.
        if (auth()->user()?->hasRole('main_branch_admin')) {
            $this->receipt->garments->each(fn ($g) => $g->withPurchaseCost());

            // Security §4.3: restricted-data access is audit-logged.
            app(AuditService::class)->logDataAccess('purchase_cost', 'goods_receipt', $receipt->id);
        }
    }

    public function render()
    {
        return view('livewire.purchasing.receiving.view', [
            'receipt' => $this->receipt,
        ])->layout('layouts.app');
    }
}
