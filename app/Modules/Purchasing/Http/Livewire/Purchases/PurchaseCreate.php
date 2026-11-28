<?php

namespace App\Modules\Purchasing\Http\Livewire\Purchases;

use App\Modules\Catalog\Models\Product;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PurchaseCreate extends Component
{
    public ?int $supplier_id = null;

    public ?int $fiscal_year_id = null;

    public string $purchase_date = '';

    public string $expected_delivery_date = '';

    public string $notes = '';

    public array $items = [];

    public function mount(): void
    {
        $this->authorize('create', Purchase::class);

        // Default to the active fiscal year
        $this->fiscal_year_id = FiscalYear::active()->first()?->id;
        $this->purchase_date = now()->format('Y-m-d');

        // Start with one empty line item
        $this->items = [['product_id' => '', 'product_size_id' => '', 'ordered_quantity' => '', 'unit_cost' => '']];
    }

    /**
     * Add a new empty line item row.
     */
    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'product_size_id' => '', 'ordered_quantity' => '', 'unit_cost' => ''];
    }

    /**
     * Remove a line item row by index.
     */
    public function removeItem(int $index): void
    {
        // Keep at least one row
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    /**
     * Computed: grand total of all line items.
     */
    public function getGrandTotalProperty(): string
    {
        $total = 0;
        foreach ($this->items as $item) {
            $qty = (float) ($item['ordered_quantity'] ?? 0);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $total += $qty * $cost;
        }

        return number_format($total, 2, '.', '');
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'purchase_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_size_id' => ['nullable', 'exists:product_sizes,id'],
            'items.*.ordered_quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'supplier_id.required' => __('Please select a supplier.'),
            'fiscal_year_id.required' => __('Please select a fiscal year.'),
            'purchase_date.required' => __('Purchase date is required.'),
            'items.*.product_id.required' => __('Product is required.'),
            'items.*.ordered_quantity.required' => __('Quantity is required.'),
            'items.*.ordered_quantity.min' => __('Quantity must be at least 1.'),
            'items.*.unit_cost.required' => __('Unit cost is required.'),
        ];
    }

    /**
     * Create the purchase as a draft.
     */
    public function create(PurchasingService $purchasingService)
    {
        $this->authorize('create', Purchase::class);

        $validated = $this->validate();

        try {
            $purchase = $purchasingService->createPurchase([
                'supplier_id' => $validated['supplier_id'],
                'fiscal_year_id' => $validated['fiscal_year_id'],
                'purchase_date' => $validated['purchase_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'items' => collect($validated['items'])->map(function ($item) {
                    return [
                        'product_id' => (int) $item['product_id'],
                        'product_size_id' => $item['product_size_id'] ? (int) $item['product_size_id'] : null,
                        'ordered_quantity' => (int) $item['ordered_quantity'],
                        'unit_cost' => (float) $item['unit_cost'],
                    ];
                })->toArray(),
            ]);

            session()->flash('success', __('Purchase :number created successfully.', ['number' => $purchase->purchase_number]));

            return $this->redirect(route('purchases.show', $purchase), navigate: true);

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
        $this->authorize('create', Purchase::class);

        $suppliers = Supplier::active()->orderBy('name')->get();
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $products = Product::active()->with('sizes')->orderBy('name')->get();

        return view('livewire.purchasing.purchases.create', [
            'suppliers' => $suppliers,
            'fiscalYears' => $fiscalYears,
            'products' => $products,
        ])->layout('layouts.app');
    }
}
