<?php

namespace App\Modules\Catalog\Http\Livewire\Prices;

use Livewire\Component;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\PriceService;
use Illuminate\Validation\ValidationException;

class PriceManager extends Component
{
    public Product $product;

    // Form fields
    public ?int $product_size_id = null;
    public string $standard_price = '';
    public string $minimum_price = '';

    // Display data
    public $currentPrices;
    public $priceHistory;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->refreshData();
    }

    protected function refreshData(): void
    {
        $priceService = app(PriceService::class);
        $this->currentPrices = $priceService->getAllCurrentPrices($this->product);
        $this->priceHistory = $priceService->getPriceHistory($this->product);
    }

    protected function rules(): array
    {
        return [
            'product_size_id' => ['nullable', 'integer'],
            'standard_price' => ['required', 'numeric', 'min:0'],
            'minimum_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'standard_price.required' => __('Standard price is required.'),
            'standard_price.numeric' => __('Standard price must be a number.'),
            'standard_price.min' => __('Standard price cannot be negative.'),
            'minimum_price.required' => __('Minimum price is required.'),
            'minimum_price.numeric' => __('Minimum price must be a number.'),
            'minimum_price.min' => __('Minimum price cannot be negative.'),
        ];
    }

    /**
     * Set a new price.
     */
    public function setPrice(PriceService $priceService)
    {
        $this->authorize('create', \App\Modules\Catalog\Models\ProductPrice::class);

        $validated = $this->validate();

        $size = $validated['product_size_id']
            ? \App\Modules\Catalog\Models\ProductSize::find($validated['product_size_id'])
            : null;

        try {
            $priceService->setPrice(
                product: $this->product,
                size: $size,
                standardPrice: (float) $validated['standard_price'],
                minimumPrice: (float) $validated['minimum_price'],
            );

            $this->reset(['standard_price', 'minimum_price']);
            $this->refreshData();

            session()->flash('success', __('Price set successfully.'));

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
        $sizes = $this->product->sizes()->active()->orderBy('size_label')->get();

        return view('livewire.catalog.prices.manager', [
            'sizes' => $sizes,
        ]);
    }
}
