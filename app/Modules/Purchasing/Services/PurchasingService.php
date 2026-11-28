<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingService
{
    /**
     * Create a new draft purchase with line items.
     *
     * @param  array  $data  {
     *                       - supplier_id: int (required, must be active)
     *                       - fiscal_year_id: int (required, must not be closed)
     *                       - purchase_date: string (required, date)
     *                       - expected_delivery_date: string|null
     *                       - notes: string|null
     *                       - items: array (required, at least 1) {
     *                       - product_id: int
     *                       - product_size_id: int|null
     *                       - ordered_quantity: int (> 0)
     *                       - unit_cost: float (>= 0)
     *                       }
     *                       }
     *
     * @throws ValidationException
     */
    public function createPurchase(array $data): Purchase
    {
        $supplier = Supplier::find($data['supplier_id']);
        if (! $supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => __('Selected supplier does not exist.'),
            ]);
        }

        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier_id' => __('Cannot create purchase for an inactive supplier.'),
            ]);
        }

        $fiscalYear = FiscalYear::find($data['fiscal_year_id']);
        if (! $fiscalYear) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => __('Selected fiscal year does not exist.'),
            ]);
        }

        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => __('Cannot create purchase in a closed fiscal year.'),
            ]);
        }

        if (empty($data['items'])) {
            throw ValidationException::withMessages([
                'items' => __('At least one line item is required.'),
            ]);
        }

        // Validate each line item
        foreach ($data['items'] as $index => $item) {
            if (empty($item['product_id']) || ! Product::where('id', $item['product_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => __('Product is invalid or inactive.'),
                ]);
            }

            if (! isset($item['ordered_quantity']) || $item['ordered_quantity'] <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.ordered_quantity" => __('Ordered quantity must be greater than zero.'),
                ]);
            }

            if (! isset($item['unit_cost']) || $item['unit_cost'] < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => __('Unit cost cannot be negative.'),
                ]);
            }
        }

        return DB::transaction(function () use ($data, $supplier, $fiscalYear) {
            $purchaseNumber = $this->generatePurchaseNumber($fiscalYear);

            $totalCost = collect($data['items'])->sum(function ($item) {
                return $item['ordered_quantity'] * $item['unit_cost'];
            });

            $purchase = Purchase::create([
                'purchase_number' => $purchaseNumber,
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $fiscalYear->id,
                'purchase_date' => $data['purchase_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => Purchase::STATUS_DRAFT,
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'product_size_id' => $item['product_size_id'] ?? null,
                    'ordered_quantity' => $item['ordered_quantity'],
                    'received_quantity' => 0,
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            return $purchase->fresh('items');
        });
    }

    /**
     * Update a draft purchase (line items, dates, notes).
     * Only draft purchases can be edited.
     *
     * @throws ValidationException
     */
    public function updateDraftPurchase(Purchase $purchase, array $data): Purchase
    {
        if (! $purchase->isDraft()) {
            throw ValidationException::withMessages([
                'status' => __('Only draft purchases can be edited.'),
            ]);
        }

        return DB::transaction(function () use ($purchase, $data) {
            $updates = [];
            if (isset($data['purchase_date'])) {
                $updates['purchase_date'] = $data['purchase_date'];
            }
            if (array_key_exists('expected_delivery_date', $data)) {
                $updates['expected_delivery_date'] = $data['expected_delivery_date'];
            }
            if (array_key_exists('notes', $data)) {
                $updates['notes'] = $data['notes'];
            }

            // If items are provided, replace them
            if (! empty($data['items'])) {
                // Validate new items
                foreach ($data['items'] as $index => $item) {
                    if (! isset($item['ordered_quantity']) || $item['ordered_quantity'] <= 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.ordered_quantity" => __('Ordered quantity must be greater than zero.'),
                        ]);
                    }
                    if (! isset($item['unit_cost']) || $item['unit_cost'] < 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit_cost" => __('Unit cost cannot be negative.'),
                        ]);
                    }
                }

                // Delete existing items and create new ones
                $purchase->items()->delete();

                $totalCost = 0;
                foreach ($data['items'] as $item) {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $item['product_id'],
                        'product_size_id' => $item['product_size_id'] ?? null,
                        'ordered_quantity' => $item['ordered_quantity'],
                        'received_quantity' => 0,
                        'unit_cost' => $item['unit_cost'],
                    ]);
                    $totalCost += $item['ordered_quantity'] * $item['unit_cost'];
                }
                $updates['total_cost'] = $totalCost;
            }

            if (! empty($updates)) {
                $purchase->update($updates);
            }

            return $purchase->fresh('items');
        });
    }

    /**
     * Confirm a draft purchase.
     * Sets status to confirmed, records confirmed_at and confirmed_by.
     *
     * @throws ValidationException if not draft
     */
    public function confirmPurchase(Purchase $purchase): Purchase
    {
        if (! $purchase->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'status' => __('Only draft purchases can be confirmed.'),
            ]);
        }

        return DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => Purchase::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            $purchase->auditCustomAction(
                'confirm',
                ['status' => Purchase::STATUS_DRAFT],
                ['status' => Purchase::STATUS_CONFIRMED],
            );

            return $purchase->fresh('items');
        });
    }

    /**
     * Cancel a draft or confirmed purchase.
     *
     * @throws ValidationException
     */
    public function cancelPurchase(Purchase $purchase, string $reason): Purchase
    {
        if (! $purchase->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('Only draft or confirmed purchases can be cancelled.'),
            ]);
        }

        return DB::transaction(function () use ($purchase, $reason) {
            $previousStatus = $purchase->status;

            $purchase->update([
                'status' => Purchase::STATUS_CANCELLED,
                'notes' => $purchase->notes."\n[Cancellation reason: {$reason}]",
            ]);

            $purchase->auditCustomAction(
                'cancel',
                ['status' => $previousStatus],
                ['status' => Purchase::STATUS_CANCELLED],
                $reason,
            );

            return $purchase->fresh('items');
        });
    }

    /**
     * Get a paginated list of purchases with filters.
     *
     * @param  array  $filters  {
     *                          - search: string
     *                          - supplier_id: int
     *                          - status: string
     *                          - fiscal_year_id: int
     *                          - per_page: int (default 25)
     *                          }
     */
    public function getPurchaseList(array $filters = []): LengthAwarePaginator
    {
        $query = Purchase::query()->with(['supplier', 'items']);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->forSupplier((int) $filters['supplier_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->forFiscalYear((int) $filters['fiscal_year_id']);
        }

        return $query->latest('purchase_date')
            ->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Get purchase detail with items, supplier, and goods receipts.
     */
    public function getPurchaseDetail(Purchase $purchase): Purchase
    {
        return $purchase->load(['supplier', 'items.product', 'items.productSize', 'creator', 'confirmer']);
    }

    /**
     * Generate a unique purchase number for the fiscal year.
     *
     * Format: PO-{YY}-{NNNNN} (e.g., PO-25-00001)
     *
     * The sequence is per-fiscal-year, computed as count+1 of existing
     * purchases in that fiscal year. This is safe for admin-only creation
     * (low concurrency). For higher concurrency, an advisory lock or
     * dedicated counter column would be needed — documented as a known
     * limitation for Phase 2.
     */
    protected function generatePurchaseNumber(FiscalYear $fiscalYear): string
    {
        $count = Purchase::forFiscalYear($fiscalYear->id)->count();
        $next = $count + 1;

        return 'PO-'.$fiscalYear->serial_prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
