<?php

namespace App\Modules\Catalog\Http\Livewire\Products;

use Livewire\Component;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Validation\ValidationException;

class ProductCreate extends Component
{
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $category = '';
    public array $sizes = [];
    public string $newSize = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'sizes' => ['array'],
            'newSize' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => __('Product code is required.'),
            'code.unique' => __('Product code already exists.'),
            'name.required' => __('Product name is required.'),
        ];
    }

    /**
     * Add a size to the temporary list.
     */
    public function addSize(): void
    {
        $this->validate(['newSize' => 'required|string|max:20']);

        $sizeLabel = trim($this->newSize);

        // Prevent duplicates in the form
        if (!in_array($sizeLabel, $this->sizes)) {
            $this->sizes[] = $sizeLabel;
        }

        $this->newSize = '';
    }

    /**
     * Remove a size from the temporary list.
     */
    public function removeSize(int $index): void
    {
        unset($this->sizes[$index]);
        $this->sizes = array_values($this->sizes);
    }

    /**
     * Create the product.
     */
    public function create(ProductService $productService)
    {
        $this->authorize('create', Product::class);

        $validated = $this->validate();

        try {
            $product = $productService->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'category' => $validated['category'] ?: null,
                'sizes' => $validated['sizes'],
            ]);

            session()->flash('success', __('Product :name created successfully.', ['name' => $product->name]));

            return $this->redirect(route('products.show', $product), navigate: true);

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function render()
    {
        // Authorize on initial render so non-admin users get 403
        // when navigating to /products/create directly.
        $this->authorize('create', Product::class);

        return view('livewire.catalog.products.create')
            ->layout('layouts.app');
    }
}
