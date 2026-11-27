<?php

namespace App\Modules\Purchasing\Http\Livewire\Suppliers;

use Livewire\Component;
use Livewire\WithPagination;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\SupplierService;

class SupplierList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function render(SupplierService $supplierService)
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $supplierService->getPaginatedList([
            'search' => $this->search,
            'status' => $this->statusFilter,
        ]);

        return view('livewire.purchasing.suppliers.list', [
            'suppliers' => $suppliers,
        ])->layout('layouts.app');
    }
}
