<?php

namespace App\Modules\Purchasing\Http\Livewire\Purchases;

use Livewire\Component;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

class PurchaseEdit extends Component
{
    public Purchase $purchase;

    public ?int $supplier_id = null;
    public ?int $fiscal_year_id = null;
    public string $purchase_date = '';
    public string $expected_delivery_date = '';
    public string $notes = '';
    public array $items = [];

    public function mount(Purchase $purchase): void
    {
        $this->authorize('update', $purchase);

        $this->purchase = $purchase->load('items');
        $this->fillFromPurchase($purchase);
    }

    /**
     * Fill form fields from the purchase model.
     */
    protected function fillFromPurchase(Purchase $purchase): void
    {
        $this->supplier_id = $purchase->supplier_id;
        $this->fiscal_year_id = $purchase->fiscal_year_id;
        $this->purchase_date = $purchase->purchase_date->format('Y-m-d');
        $this->expected_delivery_date = $purchase->expected_delivery_date?->format('Y-m-d') ?? '';
        $this->notes = $purchase->notes ?? '';

        $this->items = $purchase->items->map(function ($item) {
            return [
                'product_id' => (string) $item->product_id,
                'product_size_id' => $item->product_size_id ? (string) $item->product_size_id : '',
                'ordered_quantity' => (string) $item->ordered_quantity,
                'unit_cost' => (string) $item->unit_cost,
            ];
        })->toArray();

        if (empty($this->items)) {
            $this->items = [['product_id' => '', 'product_size_id' => '', 'ordered_quantity' => '', 'unit_cost' => '']];
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'product_size_id' => '', 'ordered_quantity' => '', 'unit_cost' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

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

    /**
     * Update the draft purchase.
     */
    public function update(PurchasingService $purchasingService)
    {
        $this->authorize('update', $this->purchase);

        $validated = $this->validate();

        try {
            $purchasingService->updateDraftPurchase($this->purchase, [
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

            session()->flash('success', __('Purchase updated successfully.'));

            return $this->redirect(route('purchases.show', $this->purchase->fresh()), navigate: true);

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Confirm the draft purchase.
     */
    public function confirm(PurchasingService $purchasingService)
    {
        $this->authorize('confirm', $this->purchase);

        try {
            $purchasingService->confirmPurchase($this->purchase);
            session()->flash('success', __('Purchase confirmed.'));
            return $this->redirect(route('purchases.show', $this->purchase->fresh()), navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Cancel the purchase with a reason.
     */
    public function cancel(PurchasingService $purchasingService)
    {
        $this->authorize('cancel', $this->purchase);

        $reason = $this->notes ? $this->notes : __('Cancelled by user');
        if (str_contains($reason, '[Cancellation reason:')) {
            // Extract the reason from notes if already has cancellation marker
            $reason = trim($this->notes);
        }

        try {
            $purchasingService->cancelPurchase($this->purchase, $reason);
            session()->flash('success', __('Purchase cancelled.'));
            return $this->redirect(route('purchases.show', $this->purchase->fresh()), navigate: true);
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
        $suppliers = Supplier::active()->orderBy('name')->get();
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $products = Product::active()->with('sizes')->orderBy('name')->get();

        return view('livewire.purchasing.purchases.edit', [
            'suppliers' => $suppliers,
            'fiscalYears' => $fiscalYears,
            'products' => $products,
        ])->layout('layouts.app');
    }
}
