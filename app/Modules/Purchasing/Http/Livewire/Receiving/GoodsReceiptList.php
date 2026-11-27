<?php

namespace App\Modules\Purchasing\Http\Livewire\Receiving;

use Livewire\Component;
use Livewire\WithPagination;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;

class GoodsReceiptList extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        // Goods receipts are admin-only (same as purchases)
        $this->authorize('viewAny', Purchase::class);

        $query = GoodsReceipt::query()
            ->with(['purchase.supplier', 'receiver']);

        if (!empty($this->search)) {
            $query->search($this->search);
        }

        $receipts = $query->latest('received_date')->paginate(25);

        return view('livewire.purchasing.receiving.list', [
            'receipts' => $receipts,
        ])->layout('layouts.app');
    }
}
