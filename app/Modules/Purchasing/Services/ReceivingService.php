<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReceivingService
{
    public function __construct(
        protected GarmentIdentityService $garmentIdentityService,
        protected InventoryMovementService $inventoryMovementService,
    ) {}

    /**
     * Receive goods for a purchase — the WF-1 atomic transaction.
     *
     * This is the core goods-receiving operation. For each received garment:
     * 1. Generates a garment identity (UUID, barcode, fiscal-year serial) via
     *    GarmentIdentityService with advisory locking.
     * 2. Creates an inventory_movement record (type: goods_receipt).
     * 3. Links the garment to the purchase and purchase item.
     *
     * After all garments are created:
     * 4. Creates a goods_receipt record.
     * 5. Links all created garments to the goods receipt.
     * 6. Updates purchase_items.received_quantity.
     * 7. Updates purchase.status (received or partially_received).
     * 8. Audit logs: purchase.receive per garment AND per receipt.
     * 9. Dispatches PurchaseReceived event.
     *
     * All steps are wrapped in a single DB transaction. If any step fails,
     * the entire operation rolls back — no partial garment identities.
     *
     * @param Purchase $purchase Must be confirmed or partially_received
     * @param array $receivedQuantities [purchase_item_id => received_qty]
     * @param User $receivedBy The user performing the receiving
     * @param string|null $notes Optional notes for the receipt
     * @return GoodsReceipt
     * @throws ValidationException
     */
    public function receiveGoods(
        Purchase $purchase,
        array $receivedQuantities,
        User $receivedBy,
        ?string $notes = null,
    ): GoodsReceipt {
        // ── Validation ──────────────────────────────────────────────

        if (!$purchase->canBeReceived()) {
            throw ValidationException::withMessages([
                'purchase' => __('Only confirmed or partially received purchases can receive goods.'),
            ]);
        }

        // Check permission via policy
        if (!$receivedBy->can('receive', $purchase)) {
            throw ValidationException::withMessages([
                'received_by' => __('You do not have permission to receive goods.'),
            ]);
        }

        if (empty($receivedQuantities)) {
            throw ValidationException::withMessages([
                'quantities' => __('At least one item must have a received quantity.'),
            ]);
        }

        // Validate each received quantity against the purchase items
        $items = $purchase->items()->whereIn('id', array_keys($receivedQuantities))->get()->keyBy('id');

        foreach ($receivedQuantities as $itemId => $qty) {
            if (!isset($items[$itemId])) {
                throw ValidationException::withMessages([
                    'quantities' => __('Invalid purchase item selected.'),
                ]);
            }

            $item = $items[$itemId];
            $remaining = $item->remainingQuantity();

            if ($qty > $remaining) {
                throw ValidationException::withMessages([
                    'quantities' => __('Received quantity (:qty) exceeds remaining quantity (:remaining) for :product.', [
                        'qty' => $qty,
                        'remaining' => $remaining,
                        'product' => $item->product?->name ?? 'item',
                    ]),
                ]);
            }
        }

        // Load context
        $mainBranch = Branch::where('code', 'M')->first();
        if (!$mainBranch) {
            throw ValidationException::withMessages([
                'branch' => __('Main Branch not found. Receiving only happens at Main Branch.'),
            ]);
        }

        $fiscalYear = $purchase->fiscalYear;

        // ── Transaction ─────────────────────────────────────────────

        return DB::transaction(function () use ($purchase, $receivedQuantities, $receivedBy, $notes, $mainBranch, $fiscalYear, $items) {
            // Generate receipt number
            $receiptNumber = $this->generateReceiptNumber($fiscalYear);

            $totalReceived = 0;
            $createdGarmentIds = [];

            foreach ($receivedQuantities as $itemId => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                $item = $items[$itemId];
                $totalReceived += $qty;

                // Create garments for each received unit
                for ($i = 0; $i < $qty; $i++) {
                    // Create the garment with purchase context
                    $garment = $this->garmentIdentityService->createGarment(
                        product: $item->product,
                        size: $item->productSize,
                        fiscalYear: $fiscalYear,
                        branch: $mainBranch,
                        purchaseCost: (float) $item->unit_cost,
                        purchaseId: $purchase->id,
                        purchaseItemId: $item->id,
                    );

                    $createdGarmentIds[] = $garment->id;

                    // Record the goods_receipt movement
                    // This creates an inventory_movements record with
                    // movement_type=goods_receipt, reference_type=purchase
                    $this->inventoryMovementService->record(
                        garment: $garment,
                        movementType: InventoryMovement::TYPE_GOODS_RECEIPT,
                        context: [
                            'to_branch_id' => $mainBranch->id,
                            'reference_type' => 'purchase',
                            'reference_id' => $purchase->id,
                            'performed_by' => $receivedBy->id,
                            'notes' => $notes,
                        ],
                    );

                    // Audit per garment
                    event(new \App\Modules\Audit\Events\AuditableEvent('purchase.receive', [
                        'category' => 'purchasing',
                        'reference_type' => 'garment',
                        'reference_id' => $garment->id,
                        'new_values' => [
                            'purchase_id' => $purchase->id,
                            'purchase_item_id' => $item->id,
                            'receipt_number' => $receiptNumber,
                            'barcode' => $garment->barcode,
                            'serial' => $garment->fiscal_year_serial,
                        ],
                        'reason' => __('Goods received for purchase :number', ['number' => $purchase->purchase_number]),
                    ]));
                }

                // Update purchase item received quantity
                $item->increment('received_quantity', $qty);
            }

            // Create the goods receipt record
            $receipt = GoodsReceipt::create([
                'purchase_id' => $purchase->id,
                'receipt_number' => $receiptNumber,
                'received_date' => now(),
                'received_by' => $receivedBy->id,
                'total_received_quantity' => $totalReceived,
                'notes' => $notes,
            ]);

            // Link all created garments to this goods receipt
            Garment::whereIn('id', $createdGarmentIds)->update([
                'goods_receipt_id' => $receipt->id,
            ]);

            // Update purchase status
            $purchase->fresh('items');
            if ($purchase->isFullyReceived()) {
                $purchase->update(['status' => Purchase::STATUS_RECEIVED]);
            } else {
                $purchase->update(['status' => Purchase::STATUS_PARTIALLY_RECEIVED]);
            }

            // Audit per receipt
            event(new \App\Modules\Audit\Events\AuditableEvent('purchase.receive', [
                'category' => 'purchasing',
                'reference_type' => 'goods_receipt',
                'reference_id' => $receipt->id,
                'new_values' => [
                    'total_received' => $totalReceived,
                    'receipt_number' => $receiptNumber,
                    'purchase_id' => $purchase->id,
                    'purchase_number' => $purchase->purchase_number,
                ],
                'reason' => __('Goods receipt :number created for purchase :purchase', [
                    'number' => $receiptNumber,
                    'purchase' => $purchase->purchase_number,
                ]),
            ]));

            // Dispatch the PurchaseReceived event
            event(new \App\Modules\Purchasing\Events\PurchaseReceived(
                $purchase->fresh(),
                $receipt,
                $receivedBy,
            ));

            return $receipt->fresh();
        });
    }

    /**
     * Get goods receipts for a purchase.
     *
     * @return Collection<int, GoodsReceipt>
     */
    public function getGoodsReceiptsForPurchase(Purchase $purchase): Collection
    {
        return $purchase->goodsReceipts()->orderBy('received_date', 'desc')->get();
    }

    /**
     * Get a goods receipt with all details.
     */
    public function getGoodsReceiptDetail(GoodsReceipt $receipt): GoodsReceipt
    {
        return $receipt->load(['purchase.supplier', 'receiver', 'garments.product', 'garments.productSize']);
    }

    /**
     * Generate a unique receipt number for the fiscal year.
     *
     * Format: GR-{YY}-{NNNNN} (e.g., GR-25-00001)
     *
     * The sequence is per-fiscal-year, computed as count+1 of existing
     * receipts in that fiscal year. Safe for admin/supervisor-only receiving
     * (low concurrency). The advisory lock in FiscalYearService protects
     * serial generation; receipt numbers are generated within the same
     * transaction so they're also protected.
     *
     * @param FiscalYear $fiscalYear
     * @return string
     */
    protected function generateReceiptNumber(FiscalYear $fiscalYear): string
    {
        // Count receipts that belong to purchases in this fiscal year
        $count = GoodsReceipt::whereHas('purchase', function ($q) use ($fiscalYear) {
            $q->where('fiscal_year_id', $fiscalYear->id);
        })->count();

        $next = $count + 1;

        return 'GR-' . $fiscalYear->serial_prefix . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
