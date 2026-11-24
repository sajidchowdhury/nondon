<?php

namespace App\Modules\Inventory\Http\Livewire\Garments;

use Livewire\Component;
use Livewire\WithPagination;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Catalog\Models\Product;

class GarmentList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $branchFilter = null;
    public ?string $statusFilter = null;
    public ?int $productFilter = null;
    public int $perPage = 25;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingBranchFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingProductFilter(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->branchFilter = null;
        $this->statusFilter = null;
        $this->productFilter = null;
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', Garment::class);

        $query = Garment::query()
            ->with(['product', 'productSize', 'currentBranch', 'fiscalYear']);

        if ($this->search) {
            $query->search($this->search);
        }

        if ($this->branchFilter) {
            $query->forBranch($this->branchFilter);
        }

        if ($this->statusFilter) {
            $query->where('current_status', $this->statusFilter);
        }

        if ($this->productFilter) {
            $query->forProduct($this->productFilter);
        }

        $garments = $query->latest()->paginate($this->perPage);

        $branches = Branch::active()->orderBy('code')->get();
        $products = Product::active()->orderBy('name')->limit(100)->get();
        $statuses = Garment::STATUSES;

        return view('livewire.inventory.garments.list', [
            'garments' => $garments,
            'branches' => $branches,
            'products' => $products,
            'statuses' => $statuses,
        ])->layout('layouts.app');
    }
}
