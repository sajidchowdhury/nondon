<?php

namespace App\Modules\Transfers\Http\Livewire\Transfers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Transfers\Models\TransferRequest;
use App\Modules\Transfers\Services\TransferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * TransferReceive — the destination-branch receiving UI (WF-7).
 *
 * Two modes (toggle):
 *  - Scan mode: scan each garment barcode → marks it received; un-scanned
 *    remain missing.
 *  - Quantity mode: enter received count per product/size group; system marks
 *    the first N garments as received and the rest missing (per WF-7 step 2d
 *    "optionally scans individual barcodes, not mandatory" — when not
 *    scanned, missing-garment identification is by serial order).
 *
 * On "Confirm Receipt": calls TransferService::receive(). Missing garments
 * are flagged `missing` — the UI must NOT offer "mark all as received" without
 * per-garment confirmation (WF-7 step 3: "System never silently marks missing
 * garments as received").
 */
class TransferReceive extends Component
{
    public TransferRequest $transfer;

    public string $mode = 'scan';

    public string $scanInput = '';

    public string $scanError = '';

    /** @var array<int, int> garment ids confirmed received */
    public array $receivedGarmentIds = [];

    /** @var array<string, int> quantity-mode counts keyed by product/size group */
    public array $quantityInputs = [];

    public string $discrepancyReason = '';

    public function mount(TransferRequest $transfer): void
    {
        $this->authorize('receive', $transfer);

        if (! $transfer->isDispatched()) {
            throw ValidationException::withMessages([
                'transfer' => __('Only dispatched transfer requests can be received.'),
            ]);
        }

        $this->transfer = $transfer->load(['items.garment.product', 'items.garment.productSize', 'fromBranch', 'toBranch']);
    }

    /**
     * Scan mode: scan a garment barcode → mark it received.
     */
    public function scanBarcode(GarmentIdentityService $identityService)
    {
        $this->authorize('receive', $this->transfer);
        $this->scanError = '';

        $code = trim($this->scanInput);
        if ($code === '') {
            $this->scanError = __('Scan a barcode.');

            return;
        }

        $garment = $identityService->lookupByBarcode($code);
        if (! $garment) {
            $garment = $identityService->lookupBySerial($code);
        }

        if (! $garment) {
            $this->scanError = __('Garment not found: :code', ['code' => $code]);
            $this->scanInput = '';

            return;
        }

        // Check if the garment is in this transfer's dispatched items.
        $expectedIds = $this->transfer->items->where('dispatched', true)->pluck('garment_id')->all();
        if (in_array($garment->id, $expectedIds, true)) {
            if (! in_array($garment->id, $this->receivedGarmentIds, true)) {
                $this->receivedGarmentIds[] = $garment->id;
            }
            $this->scanError = '';
        } else {
            // Excess — a garment not in this transfer. Still add it to the
            // received list so TransferService::receive flags it as excess.
            if (! in_array($garment->id, $this->receivedGarmentIds, true)) {
                $this->receivedGarmentIds[] = $garment->id;
            }
            $this->scanError = __('Garment :barcode is not in this transfer (excess).', ['barcode' => $garment->barcode]);
        }

        $this->scanInput = '';
    }

    /**
     * Quantity mode: compute the received set from the quantity inputs.
     * For each product/size group, mark the first N garments (by serial order)
     * as received and the rest as missing.
     */
    public function applyQuantities(): void
    {
        $this->authorize('receive', $this->transfer);
        $this->scanError = '';

        $this->receivedGarmentIds = [];

        // Group dispatched items by product+size, then take the first N per group.
        $groups = $this->transfer->items
            ->where('dispatched', true)
            ->groupBy(function ($item) {
                $product = $item->product?->name ?? 'product-'.$item->product_id;
                $size = $item->productSize?->size_label ?? 'no-size';

                return $product.' / '.$size;
            });

        foreach ($groups as $key => $items) {
            $qty = (int) ($this->quantityInputs[$key] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            // Sort by serial to pick the first N (deterministic ordering).
            $sorted = $items->sortBy(fn ($item) => $item->garment?->fiscal_year_serial ?? '');
            $count = 0;
            foreach ($sorted as $item) {
                if ($count >= $qty) {
                    break;
                }
                $this->receivedGarmentIds[] = $item->garment_id;
                $count++;
            }
        }
    }

    /**
     * Confirm the receipt → TransferService::receive().
     */
    public function confirmReceipt(TransferService $transferService)
    {
        $this->authorize('receive', $this->transfer);
        $this->scanError = '';

        if (empty($this->receivedGarmentIds)) {
            $this->scanError = __('No garments have been confirmed received. Mark at least one or confirm all are missing.');

            return;
        }

        // Check if there will be a discrepancy (shortage or excess).
        $expectedIds = $this->transfer->items->where('dispatched', true)->pluck('garment_id')->all();
        $missing = array_diff($expectedIds, $this->receivedGarmentIds);
        $excess = array_diff($this->receivedGarmentIds, $expectedIds);

        if ((! empty($missing) || ! empty($excess)) && trim($this->discrepancyReason) === '') {
            $this->scanError = __('A discrepancy reason is required when there are missing or excess garments.');

            return;
        }

        try {
            /** @var User $user */
            $user = auth()->user();
            $request = $transferService->receive(
                $this->transfer,
                $user,
                $this->receivedGarmentIds,
                $this->discrepancyReason ?: null
            );

            session()->flash('success', __('Transfer received with status: :status', ['status' => $request->status]));

            return $this->redirect(route('transfers.show', $request), navigate: true);
        } catch (AuthorizationException $e) {
            $this->scanError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->scanError = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $this->authorize('receive', $this->transfer);

        $expectedItems = $this->transfer->items->where('dispatched', true);
        $expectedIds = $expectedItems->pluck('garment_id')->all();
        $missing = array_values(array_diff($expectedIds, $this->receivedGarmentIds));
        $excess = array_values(array_diff($this->receivedGarmentIds, $expectedIds));

        return view('livewire.transfers.receive', [
            'expectedItems' => $expectedItems,
            'receivedCount' => count(array_intersect($expectedIds, $this->receivedGarmentIds)),
            'missingCount' => count($missing),
            'excessCount' => count($excess),
            'hasDiscrepancy' => ! empty($missing) || ! empty($excess),
        ])->layout('layouts.app');
    }
}
