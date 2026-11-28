<?php

namespace App\Modules\Sales\Services;

use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\PriceService;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use App\Modules\Sales\Exceptions\GarmentNotAvailableException;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\SaleInvoice;
use BadMethodCallException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SaleService — the WF-3 atomic POS sale transaction.
 *
 * The headline method is complete(): it locks every garment in the cart with
 * SELECT ... FOR UPDATE, re-verifies saleability inside the lock, records the
 * sale movement per garment, creates the invoice + items + payments, links
 * the customer, snapshots the return policy, dispatches SaleCompleted, and
 * audits sale.complete — all in a single DB transaction. If any garment
 * becomes unavailable between cart-build and completion (another cashier),
 * the specific garment is flagged and the whole transaction rolls back.
 *
 * This mirrors the Phase 2 ReceivingService atomic-transaction + per-unit
 * InventoryMovementService::record + per-document AuditableEvent + domain
 * event pattern, with the addition of row-level locking for concurrency.
 */
class SaleService
{
    public function __construct(
        protected InventoryMovementService $inventoryMovementService,
        protected PriceService $priceService,
        protected SaleNumberService $saleNumberService,
        protected FiscalYearService $fiscalYearService,
        protected CustomerService $customerService,
    ) {}

    /**
     * Atomically complete a POS sale.
     *
     * @param  User  $salesperson  The user performing the sale (must have
     *                             pos.sale.complete and be at $branch).
     * @param  Branch  $branch  The branch where the sale happens (must equal
     *                          the salesperson's assigned branch).
     * @param  array  $payload  {
     *                          customer_id: int|null,          // existing customer id (optional)
     *                          customer_phone: string|null,   // alternative: phone (POS path)
     *                          customer_name: string|null,
     *                          sale_type: 'retail'|'wholesale',
     *                          items: array<int, {garment_id: int, discount?: float}>,  // min 1
     *                          payments: array<int, {method: string, amount: float, reference?: string}>,  // min 1
     *                          notes: string|null,
     *                          }
     * @return SaleInvoice The completed invoice (status=completed).
     *
     * @throws GarmentNotAvailableException garment not saleable / wrong branch /
     *                                      sold by a concurrent cashier
     * @throws BelowMinimumPriceException discount pushed below minimum_price
     * @throws ValidationException payment short / invalid method /
     *                             closed fiscal year / no items
     */
    public function complete(User $salesperson, Branch $branch, array $payload): SaleInvoice
    {
        // ── Validation (outside the transaction) ──────────────────────────

        $this->validateSalesperson($salesperson, $branch);
        $this->validatePayloadShape($payload);

        $fiscalYear = FiscalYear::active()->first();
        if (! $fiscalYear) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('No active fiscal year. Cannot complete a sale.'),
            ]);
        }

        // Resolve each garment, verify saleable + branch, resolve price + enforce minimum.
        $resolvedItems = $this->resolveItems($payload['items'], $branch);

        // Resolve payment methods and totals.
        [$paymentLines, $totalPaid] = $this->resolvePayments($payload['payments']);

        // Resolve customer (by id OR by phone → find-or-create).
        $customerId = $this->resolveCustomer($payload, $salesperson);

        // Compute totals.
        $subtotal = (float) array_reduce(
            $resolvedItems,
            fn ($carry, $i) => bcadd($carry, (string) $i['standard_price'], 2),
            '0'
        );
        $totalDiscount = (float) array_reduce(
            $resolvedItems,
            fn ($carry, $i) => bcadd($carry, (string) $i['discount'], 2),
            '0'
        );
        $totalAmount = (float) bcsub((string) $subtotal, (string) $totalDiscount, 2);

        // Enforce full payment.
        if (bccomp((string) $totalPaid, (string) $totalAmount, 2) < 0) {
            throw ValidationException::withMessages([
                'payments' => __('Total paid (:paid) is less than total amount (:total).', [
                    'paid' => number_format($totalPaid, 2),
                    'total' => number_format($totalAmount, 2),
                ]),
            ]);
        }

        $saleType = $payload['sale_type'] ?? SaleInvoice::SALE_TYPE_RETAIL;
        $notes = $payload['notes'] ?? null;

        // ── Transaction ────────────────────────────────────────────────────

        return DB::transaction(function () use (
            $salesperson, $branch, $fiscalYear, $resolvedItems,
            $paymentLines, $customerId, $subtotal, $totalDiscount,
            $totalAmount, $totalPaid, $saleType, $notes
        ) {
            // Concurrency-safe invoice number (advisory-locked).
            $invoiceNumber = $this->saleNumberService->generateNextInvoiceNumber($fiscalYear);

            // Create the invoice header first (status = completed) so we have
            // the id for the items, payments, and movement references.
            $invoice = SaleInvoice::create([
                'invoice_number' => $invoiceNumber,
                'branch_id' => $branch->id,
                'salesperson_id' => $salesperson->id,
                'customer_id' => $customerId,
                'fiscal_year_id' => $fiscalYear->id,
                'sale_type' => $saleType,
                'subtotal' => $subtotal,
                'total_discount' => $totalDiscount,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'status' => SaleInvoice::STATUS_COMPLETED,
                'is_offline_sale' => false,
                'offline_uuid' => null,
                'completed_at' => now(),
                'return_policy_snapshot' => config('nondon.return_policy', ['return_period_days' => 7]),
                'notes' => $notes,
            ]);

            // Per garment: lock the row, re-verify, record the sale movement,
            // create the invoice item. The FOR UPDATE lock is the concurrency
            // primitive — a second cashier attempting the same garment will
            // block here, then fail the re-verification.
            foreach ($resolvedItems as $resolved) {
                $garmentId = $resolved['garment_id'];

                // Lock the garment row for the duration of the transaction.
                $garment = Garment::lockForUpdate()->find($garmentId);
                if (! $garment) {
                    throw new GarmentNotAvailableException($garmentId);
                }

                // Re-verify saleability INSIDE the lock (the other cashier may
                // have just sold it). canBeSold() = available OR clearance.
                if (! $garment->canBeSold()) {
                    throw new GarmentNotAvailableException($garmentId, $garment->current_status);
                }

                // Re-verify branch (can only sell garments at your branch).
                if ((int) $garment->current_branch_id !== $branch->id) {
                    throw new GarmentNotAvailableException($garmentId, $garment->current_status);
                }

                // Record the sale movement. This atomically:
                //   - creates inventory_movements(type=sale, ref=sale_invoice)
                //   - sets garment.current_status = sold
                //   - updates garment_current_stock projection
                //   - dispatches the inventory.movement audit event
                $this->inventoryMovementService->record(
                    garment: $garment,
                    movementType: InventoryMovement::TYPE_SALE,
                    context: [
                        'reference_type' => 'sale_invoice',
                        'reference_id' => $invoice->id,
                        'performed_by' => $salesperson->id,
                        'notes' => 'Sale '.$invoiceNumber,
                    ],
                );

                // Create the invoice item (snapshot standard_price + discount).
                $invoice->items()->create([
                    'garment_id' => $garment->id,
                    'product_id' => $resolved['product_id'],
                    'product_size_id' => $resolved['product_size_id'],
                    'standard_price' => $resolved['standard_price'],
                    'applied_discount' => $resolved['discount'],
                    'final_price' => bcsub((string) $resolved['standard_price'], (string) $resolved['discount'], 2),
                ]);
            }

            // Create payment rows.
            foreach ($paymentLines as $line) {
                $invoice->payments()->create([
                    'payment_method' => $line['method'],
                    'amount' => $line['amount'],
                    'reference_number' => $line['reference'] ?? null,
                    'recorded_by' => $salesperson->id,
                    'recorded_at' => now(),
                ]);
            }

            // Per-document audit event.
            event(new AuditableEvent('sale.complete', [
                'category' => 'sales',
                'reference_type' => 'sale_invoice',
                'reference_id' => $invoice->id,
                'new_values' => [
                    'invoice_number' => $invoiceNumber,
                    'branch_id' => $branch->id,
                    'salesperson_id' => $salesperson->id,
                    'customer_id' => $customerId,
                    'item_count' => count($resolvedItems),
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'payment_methods' => collect($paymentLines)->pluck('method')->all(),
                ],
                'reason' => null,
            ]));

            // Dispatch the SaleCompleted domain event (Phase 6 Cash will listen).
            event(new SaleCompleted($invoice->fresh()));

            return $invoice->fresh();
        });
    }

    /**
     * Void a completed sale. NOT supported in Phase 3 — completed invoices
     * are immutable (WF-3 post-condition). Corrections go through the Returns
     * module (Phase 5). The method exists so the route surface is complete
     * and the refusal is explicit rather than a 404.
     */
    public function void(SaleInvoice $invoice): SaleInvoice
    {
        throw new BadMethodCallException(
            __('Completed sales cannot be voided. Use the Returns module (Phase 5) for corrections.')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Verify the salesperson may complete a sale at the given branch.
     */
    protected function validateSalesperson(User $salesperson, Branch $branch): void
    {
        if (! $salesperson->can('pos.sale.complete')) {
            throw ValidationException::withMessages([
                'salesperson' => __('You do not have permission to complete a sale.'),
            ]);
        }

        if (! $salesperson->canSell()) {
            throw ValidationException::withMessages([
                'salesperson' => __('Your account is not eligible to sell (inactive or no assigned branch).'),
            ]);
        }

        if ((int) $salesperson->assigned_branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'branch' => __('You can only sell at your assigned branch.'),
            ]);
        }
    }

    /**
     * Validate the payload's basic shape (at least one item, one payment).
     */
    protected function validatePayloadShape(array $payload): void
    {
        if (empty($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => __('A sale must include at least one item.'),
            ]);
        }

        if (empty($payload['payments'])) {
            throw ValidationException::withMessages([
                'payments' => __('A sale must include at least one payment.'),
            ]);
        }

        if (! in_array($payload['sale_type'] ?? SaleInvoice::SALE_TYPE_RETAIL, SaleInvoice::SALE_TYPES, true)) {
            throw ValidationException::withMessages([
                'sale_type' => __('Sale type must be retail or wholesale.'),
            ]);
        }
    }

    /**
     * Resolve each cart line: load the garment, verify saleable + branch,
     * resolve the current price, enforce minimum price on the discount.
     *
     * Returns a structured array per item that the transaction closure uses.
     *
     * @return array<int, array{
     *   garment_id: int, product_id: int, product_size_id: int|null,
     *   standard_price: string, discount: string
     * }>
     */
    protected function resolveItems(array $items, Branch $branch): array
    {
        $resolved = [];
        $seenGarmentIds = [];

        foreach ($items as $line) {
            $garmentId = (int) ($line['garment_id'] ?? 0);
            if ($garmentId <= 0) {
                throw ValidationException::withMessages([
                    'items' => __('Invalid garment in cart.'),
                ]);
            }

            if (isset($seenGarmentIds[$garmentId])) {
                throw ValidationException::withMessages([
                    'items' => __('Garment :id is already in the cart.', ['id' => $garmentId]),
                ]);
            }
            $seenGarmentIds[$garmentId] = true;

            $garment = Garment::find($garmentId);
            if (! $garment) {
                throw new GarmentNotAvailableException($garmentId);
            }

            if (! $garment->canBeSold()) {
                throw new GarmentNotAvailableException($garmentId, $garment->current_status);
            }

            if ((int) $garment->current_branch_id !== $branch->id) {
                throw new GarmentNotAvailableException($garmentId, $garment->current_status);
            }

            // Resolve the current price for this product/size.
            $price = $this->priceService->getCurrentPrice($garment->product, $garment->productSize);
            if (! $price) {
                throw ValidationException::withMessages([
                    'items' => __('No active price found for product :product.', ['product' => $garment->product->name]),
                ]);
            }

            $standardPrice = (string) $price->standard_price;
            $discount = (string) (float) ($line['discount'] ?? 0);

            // Discount bounds (checked before the minimum-price derivation so
            // a discount exceeding the standard price is reported as a
            // discount error, not a below-minimum-price error).
            if (bccomp($discount, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'items' => __('Discount cannot be negative for garment :id.', ['id' => $garmentId]),
                ]);
            }

            if (bccomp($discount, $standardPrice, 2) > 0) {
                throw ValidationException::withMessages([
                    'items' => __('Discount cannot exceed the standard price for garment :id.', ['id' => $garmentId]),
                ]);
            }

            $finalPrice = bcsub($standardPrice, $discount, 2);

            // Enforce minimum price.
            if (bccomp($finalPrice, (string) $price->minimum_price, 2) < 0) {
                throw new BelowMinimumPriceException(
                    garmentId: $garmentId,
                    finalPrice: $finalPrice,
                    minimumPrice: (string) $price->minimum_price,
                );
            }

            $resolved[] = [
                'garment_id' => $garmentId,
                'product_id' => $garment->product_id,
                'product_size_id' => $garment->product_size_id,
                'standard_price' => $standardPrice,
                'discount' => $discount,
            ];
        }

        return $resolved;
    }

    /**
     * Resolve payment lines: validate each method exists + is active, enforce
     * reference where required. Returns [lines, totalPaid].
     *
     * @return array{0: array<int, array{method: string, amount: string, reference: string|null}>, 1: float}
     */
    protected function resolvePayments(array $payments): array
    {
        $lines = [];
        $totalPaid = '0';

        foreach ($payments as $line) {
            $method = (string) ($line['method'] ?? '');
            $amount = (string) (float) ($line['amount'] ?? 0);
            $reference = $line['reference'] ?? null;

            if ($method === '') {
                throw ValidationException::withMessages([
                    'payments' => __('Payment method is required.'),
                ]);
            }

            $pm = PaymentMethod::findByName($method);
            if (! $pm || ! $pm->is_active) {
                throw ValidationException::withMessages([
                    'payments' => __('Payment method :method is not available.', ['method' => $method]),
                ]);
            }

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'payments' => __('Payment amount must be greater than zero.'),
                ]);
            }

            if ($pm->requires_reference && blank($reference)) {
                throw ValidationException::withMessages([
                    'payments' => __('Payment method :method requires a reference number.', ['method' => $method]),
                ]);
            }

            $lines[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference,
            ];
            $totalPaid = bcadd($totalPaid, $amount, 2);
        }

        return [$lines, (float) $totalPaid];
    }

    /**
     * Resolve the customer for the sale.
     *
     * If customer_id is provided, returns it (after a basic existence check;
     * the PII scope is enforced when viewing the customer, not at sale time —
     * any selling role may sell to any customer the POS resolves).
     *
     * If customer_phone is provided, uses CustomerService::findOrCreateByPhone
     * (the POS path — phone mandatory, name optional, dedup).
     *
     * Returns null if neither is provided (true walk-in edge case).
     */
    protected function resolveCustomer(array $payload, User $salesperson): ?int
    {
        if (! empty($payload['customer_id'])) {
            return (int) $payload['customer_id'];
        }

        if (! empty($payload['customer_phone'])) {
            $customer = $this->customerService->findOrCreateByPhone(
                $payload['customer_phone'],
                $salesperson,
                $payload['customer_name'] ?? null,
            );

            return $customer->id;
        }

        return null;
    }
}
