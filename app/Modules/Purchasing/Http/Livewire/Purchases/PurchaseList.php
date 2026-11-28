<?php

namespace App\Modules\Purchasing\Http\Livewire\Purchases;

use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $supplierFilter = null;

    public string $statusFilter = '';

    public ?int $fiscalYearFilter = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFiscalYearFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->supplierFilter = null;
        $this->statusFilter = '';
        $this->fiscalYearFilter = null;
        $this->resetPage();
    }

    public function render(PurchasingService $purchasingService)
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = $purchasingService->getPurchaseList([
            'search' => $this->search,
            'supplier_id' => $this->supplierFilter,
            'status' => $this->statusFilter,
            'fiscal_year_id' => $this->fiscalYearFilter,
        ]);

        $suppliers = Supplier::active()->orderBy('name')->get();
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

        return view('livewire.purchasing.purchases.list', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'fiscalYears' => $fiscalYears,
        ])->layout('layouts.app');
    }
}
