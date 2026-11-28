<?php

namespace App\Modules\Catalog\Http\Livewire\Products;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProductEdit extends Component
{
    public Product $product;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $category = '';

    public string $newSize = '';

    public function mount(Product $product): void
    {
        $this->authorize('update', $product);

        $this->product = $product;
        $this->code = $product->code;
        $this->name = $product->name;
        $this->description = $product->description ?? '';
        $this->category = $product->category ?? '';
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:products,code,'.$this->product->id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'newSize' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function update(ProductService $productService)
    {
        $this->authorize('update', $this->product);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code,'.$this->product->id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $productService->update($this->product, [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'category' => $validated['category'] ?: null,
            ]);

            $this->product = $this->product->fresh('sizes');

            session()->flash('success', __('Product updated successfully.'));

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function addSize(ProductService $productService)
    {
        $this->authorize('manageSizes', $this->product);

        $this->validate(['newSize' => 'required|string|max:20']);

        try {
            $productService->addSize($this->product, trim($this->newSize));
            $this->product = $this->product->fresh('sizes');
            $this->newSize = '';

            session()->flash('success', __('Size added.'));

        } catch (ValidationException $e) {
            $this->addError('newSize', collect($e->errors())->flatten()->first());
        }
    }

    public function removeSize(string $sizeLabel, ProductService $productService)
    {
        $this->authorize('manageSizes', $this->product);

        try {
            $productService->removeSize($this->product, $sizeLabel);
            $this->product = $this->product->fresh('sizes');

            session()->flash('success', __('Size removed.'));

        } catch (ValidationException $e) {
            $this->addError('sizes', collect($e->errors())->flatten()->first());
        }
    }

    public function activate(ProductService $productService)
    {
        $this->authorize('toggleStatus', $this->product);

        $productService->activate($this->product);
        $this->product = $this->product->fresh();

        session()->flash('success', __('Product activated.'));
    }

    public function deactivate(ProductService $productService)
    {
        $this->authorize('toggleStatus', $this->product);

        $productService->deactivate($this->product);
        $this->product = $this->product->fresh();

        session()->flash('success', __('Product deactivated.'));
    }

    public function render()
    {
        return view('livewire.catalog.products.edit', [
            'sizes' => $this->product->fresh('sizes')->sizes,
        ])->layout('layouts.app');
    }
}
