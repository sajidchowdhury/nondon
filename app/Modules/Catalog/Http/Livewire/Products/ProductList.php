<?php

namespace App\Modules\Catalog\Http\Livewire\Products;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $categoryFilter = null;

    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
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
        $this->categoryFilter = null;
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function render(ProductService $productService)
    {
        $this->authorize('viewAny', Product::class);

        $products = $productService->getPaginatedList([
            'search' => $this->search,
            'category' => $this->categoryFilter,
            'status' => $this->statusFilter,
        ]);

        $categories = $productService->getCategories();

        return view('livewire.catalog.products.list', [
            'products' => $products,
            'categories' => $categories,
        ])->layout('layouts.app');
    }
}
