<?php

namespace App\Modules\Purchasing\Http\Livewire\Receiving;

use App\Modules\Purchasing\Models\GoodsReceipt;
use Livewire\Component;
use Livewire\WithPagination;

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
        // Admins see all receipts; Main-Branch receiving staff (branch
        // supervisors assigned to the Main Branch) see the receipts of
        // their own receiving workflow. Sensitive fields (supplier identity,
        // purchase cost) are hidden for non-admins in the view layer.
        $this->authorize('view-goods-receipts');

        $query = GoodsReceipt::query()
            ->with(['purchase.supplier', 'receiver']);

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        $receipts = $query->latest('received_date')->paginate(25);

        return view('livewire.purchasing.receiving.list', [
            'receipts' => $receipts,
        ])->layout('layouts.app');
    }
}
