<?php

namespace App\Modules\Inventory\Http\Livewire\Stock;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Organization\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class StockAvailability extends Component
{
    use WithPagination;

    public ?int $branchFilter = null;

    public ?int $productFilter = null;

    public ?int $sizeFilter = null;

    public string $search = '';

    public int $perPage = 50;

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSizeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->branchFilter = null;
        $this->productFilter = null;
        $this->sizeFilter = null;
        $this->search = '';
        $this->resetPage();
    }

    public function render(StockQueryService $stockQueryService)
    {
        $this->authorize('viewAny', Garment::class);

        // If search term is provided, use search instead of paginated list
        if ($this->search) {
            $garments = $stockQueryService->searchGarments($this->search);
            $garments = new LengthAwarePaginator(
                $garments->forPage(request()->input('page', 1), $this->perPage),
                $garments->count(),
                $this->perPage,
                request()->input('page', 1),
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $garments = $stockQueryService->getPaginatedAvailableStock([
                'branch_id' => $this->branchFilter,
                'product_id' => $this->productFilter,
                'size_id' => $this->sizeFilter,
                'per_page' => $this->perPage,
            ]);
        }

        $branches = Branch::active()->orderBy('code')->get();
        $products = Product::active()->orderBy('name')->limit(100)->get();
        $sizes = $this->productFilter
            ? ProductSize::where('product_id', $this->productFilter)->active()->orderBy('size_label')->get()
            : collect();

        return view('livewire.inventory.stock.availability', [
            'garments' => $garments,
            'branches' => $branches,
            'products' => $products,
            'sizes' => $sizes,
        ])->layout('layouts.app');
    }
}
