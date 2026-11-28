<?php

namespace App\Modules\Purchasing\Http\Livewire\Receiving;

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Services\ReceivingService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ReceiveGoods extends Component
{
    public Purchase $purchase;

    /** @var array<int, string> Item ID => received quantity string */
    public array $receivedQuantities = [];

    public string $notes = '';

    public function mount(Purchase $purchase): void
    {
        $this->authorize('receive', $purchase);

        $this->purchase = $purchase->load(['supplier', 'items.product', 'items.productSize']);

        // Initialize received quantities to empty for each item
        foreach ($this->purchase->items as $item) {
            $this->receivedQuantities[$item->id] = '';
        }
    }

    /**
     * Receive the goods — calls the ReceivingService transaction.
     */
    public function receive(ReceivingService $receivingService)
    {
        $this->authorize('receive', $this->purchase);

        // Validate: at least one item has a quantity
        $hasQuantity = false;
        $quantities = [];

        foreach ($this->receivedQuantities as $itemId => $qtyStr) {
            $qty = (int) $qtyStr;
            if ($qty > 0) {
                $hasQuantity = true;
                $quantities[(int) $itemId] = $qty;
            }
        }

        if (! $hasQuantity) {
            $this->addError('quantities', __('At least one item must have a received quantity greater than zero.'));

            return;
        }

        // Validate each quantity against remaining
        foreach ($quantities as $itemId => $qty) {
            $item = $this->purchase->items->firstWhere('id', $itemId);
            if (! $item) {
                $this->addError("receivedQuantities.{$itemId}", __('Invalid item.'));

                return;
            }

            $remaining = $item->remainingQuantity();
            if ($qty > $remaining) {
                $this->addError(
                    "receivedQuantities.{$itemId}",
                    __('Quantity :qty exceeds remaining :remaining.', ['qty' => $qty, 'remaining' => $remaining])
                );

                return;
            }
        }

        try {
            $receipt = $receivingService->receiveGoods(
                purchase: $this->purchase,
                receivedQuantities: $quantities,
                receivedBy: auth()->user(),
                notes: $this->notes ?: null,
            );

            session()->flash('success', __('Goods received successfully. :count garments generated.', [
                'count' => $receipt->total_received_quantity,
            ]));

            return $this->redirect(route('goods-receipts.show', $receipt), navigate: true);

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
        return view('livewire.purchasing.receiving.form', [
            'purchase' => $this->purchase,
        ])->layout('layouts.app');
    }
}
