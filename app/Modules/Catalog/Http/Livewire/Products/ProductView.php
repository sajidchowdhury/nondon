<?php

namespace App\Modules\Catalog\Http\Livewire\Products;

use App\Modules\Catalog\Models\Product;
use Livewire\Component;

class ProductView extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->authorize('view', $product);
        $this->product = $product->load('sizes', 'createdByUser', 'deactivatedByUser');
    }

    public function render()
    {
        return view('livewire.catalog.products.view')
            ->layout('layouts.app');
    }
}
