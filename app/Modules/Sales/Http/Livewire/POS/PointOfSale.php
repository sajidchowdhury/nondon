<?php

namespace App\Modules\Sales\Http\Livewire\POS;

use App\Modules\Catalog\Services\PriceService;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Support\CartItem;
use App\Modules\Sales\Support\CartState;
use Livewire\Component;

/**
 * POS interface — the "build the sale" half of WF-3.
 *
 * Garment lookup by barcode or fiscal-year serial, cart add/remove/replace,
 * and per-line discount with minimum-price enforcement. Cart state lives
 * in-memory on this component (Livewire serializes it across round-trips);
 * no DB draft invoices. Completion is Session 3.4 (this component emits
 * `openCheckout` when the cashier clicks "Proceed to Checkout").
 *
 * Authorization: pos.sale.create required to view the POS (enforced in
 * render()). Each mutation re-checks authorization so a permission
 * revocation mid-session can't be bypassed.
 */
class PointOfSale extends Component
{
    /** @var array<int, array> Livewire-serializable cart items (CartItem as array). */
    public array $cartItems = [];

    public string $scanInput = '';

    public string $searchTerm = '';

    public string $scanError = '';

    public string $scanSuccess = '';

    /** @var array<int, array> Product/size search results dropdown. */
    public array $searchResults = [];

    /**
     * Discount being applied per-line (keyed by garment_id; ephemeral input
     * wired to the discount field).
     */
    public array $discountInputs = [];

    public string $saleType = 'retail';

    protected $listeners = ['checkoutClosed' => 'refreshCart'];

    /*
    |--------------------------------------------------------------------------
    | Scan / lookup
    |--------------------------------------------------------------------------
    */

    /**
     * Scan a barcode or fiscal-year serial and add the garment to the cart.
     *
     * Detects serial format (YY-NNNNN) and uses lookupBySerial; otherwise
     * treats the input as a barcode.
     */
    public function scanBarcode(GarmentIdentityService $identityService, PriceService $priceService)
    {
        $this->authorize('create', SaleInvoice::class);

        $code = trim($this->scanInput);
        $this->scanError = '';
        $this->scanSuccess = '';

        if ($code === '') {
            $this->scanError = __('Scan or type a barcode / serial.');

            return;
        }

        $branch = $this->activeBranch();
        if (! $branch) {
            $this->scanError = __('No active branch assigned to your account.');

            return;
        }

        // Serial format: YY-NNNNN (two digits, dash, five digits).
        $isSerial = (bool) preg_match('/^\d{2}-\d{5}$/', $code);
        $garment = $isSerial
            ? $identityService->lookupBySerial($code)
            : $identityService->lookupByBarcode($code);

        if (! $garment) {
            $this->scanError = $isSerial
                ? __('No garment found with serial :code.', ['code' => $code])
                : __('No garment found with barcode :code.', ['code' => $code]);
            $this->scanInput = '';

            return;
        }

        $this->addGarmentToCart($garment, $priceService);
        $this->scanInput = '';
    }

    /**
     * Search products/garments by name or code; populates the dropdown.
     */
    public function searchProducts(StockQueryService $stockQuery, PriceService $priceService)
    {
        $this->authorize('create', SaleInvoice::class);

        $term = trim($this->searchTerm);
        if ($term === '') {
            $this->searchResults = [];

            return;
        }

        $branch = $this->activeBranch();
        if (! $branch) {
            $this->searchResults = [];

            return;
        }

        // Reuse the existing StockQueryService search (already hides
        // purchase_cost), then narrow to available garments at this branch.
        $results = $stockQuery->searchGarments($term, 20)
            ->filter(fn (Garment $g) => $g->canBeSold() && (int) $g->current_branch_id === $branch->id)
            ->values();

        $this->searchResults = $results->map(function (Garment $g) use ($priceService) {
            $price = $priceService->getCurrentPrice($g->product, $g->productSize);

            return [
                'garment_id' => $g->id,
                'barcode' => $g->barcode,
                'serial' => $g->fiscal_year_serial,
                'product_name' => $g->product?->name ?? '(unknown)',
                'size_label' => $g->productSize?->size_label,
                'price' => $price ? (string) $price->standard_price : '0.00',
            ];
        })->toArray();
    }

    /**
     * Add a garment (from the search dropdown) to the cart by id.
     */
    public function addByGarmentId(int $garmentId, PriceService $priceService)
    {
        $this->authorize('create', SaleInvoice::class);

        $garment = Garment::find($garmentId);
        if (! $garment) {
            $this->scanError = __('Garment not found.');

            return;
        }

        $this->addGarmentToCart($garment, $priceService);
    }

    /**
     * Core add-to-cart: eligibility checks, then add to CartState.
     */
    protected function addGarmentToCart(Garment $garment, PriceService $priceService): void
    {
        $branch = $this->activeBranch();
        if (! $branch) {
            $this->scanError = __('No active branch assigned to your account.');

            return;
        }

        if (! $garment->canBeSold()) {
            $this->scanError = $this->rejectionReason($garment);

            return;
        }

        if ((int) $garment->current_branch_id !== $branch->id) {
            $this->scanError = __('Garment :barcode is at another branch.', ['barcode' => $garment->barcode]);

            return;
        }

        $cart = $this->cartState();
        if ($cart->has($garment->id)) {
            $this->scanError = __('Garment :barcode is already in the cart.', ['barcode' => $garment->barcode]);

            return;
        }

        $price = $priceService->getCurrentPrice($garment->product, $garment->productSize);
        if (! $price) {
            $this->scanError = __('No active price found for product :product.', ['product' => $garment->product?->name ?? '(unknown)']);

            return;
        }

        $cart->add($garment, $price);
        $this->syncCartState($cart);
        $this->scanSuccess = __('Added :name (:barcode).', [
            'name' => $garment->product?->name ?? 'garment',
            'barcode' => $garment->barcode,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cart mutations
    |--------------------------------------------------------------------------
    */

    public function removeFromCart(int $garmentId): void
    {
        $this->authorize('create', SaleInvoice::class);

        $cart = $this->cartState();
        $cart->remove($garmentId);
        $this->syncCartState($cart);
        unset($this->discountInputs[$garmentId]);
    }

    /**
     * Replace a cart line with a different garment (scanned by barcode).
     */
    public function replaceItem(int $oldGarmentId, string $newBarcode, GarmentIdentityService $identityService, PriceService $priceService): void
    {
        $this->authorize('create', SaleInvoice::class);

        $code = trim($newBarcode);
        if ($code === '') {
            $this->scanError = __('Scan the replacement garment barcode.');

            return;
        }

        $isSerial = (bool) preg_match('/^\d{2}-\d{5}$/', $code);
        $newGarment = $isSerial
            ? $identityService->lookupBySerial($code)
            : $identityService->lookupByBarcode($code);

        if (! $newGarment) {
            $this->scanError = __('Replacement garment not found.');

            return;
        }

        $branch = $this->activeBranch();
        if (! $newGarment->canBeSold()) {
            $this->scanError = $this->rejectionReason($newGarment);

            return;
        }
        if ((int) $newGarment->current_branch_id !== $branch?->id) {
            $this->scanError = __('Replacement garment is at another branch.');

            return;
        }

        $newPrice = $priceService->getCurrentPrice($newGarment->product, $newGarment->productSize);
        if (! $newPrice) {
            $this->scanError = __('No active price for the replacement product.');

            return;
        }

        $cart = $this->cartState();
        try {
            $cart->replace($oldGarmentId, $newGarment, $newPrice);
        } catch (\RuntimeException $e) {
            $this->scanError = $e->getMessage();

            return;
        }
        $this->syncCartState($cart);
        $this->scanSuccess = __('Replaced with :barcode.', ['barcode' => $newGarment->barcode]);
    }

    /**
     * Apply a discount to a cart line (enforces minimum price).
     */
    public function applyDiscount(int $garmentId): void
    {
        $this->authorize('create', SaleInvoice::class);

        $rawDiscount = (string) ($this->discountInputs[$garmentId] ?? '0');
        $discount = $this->normalizeDiscount($rawDiscount);

        $cart = $this->cartState();
        try {
            $cart->applyDiscount($garmentId, $discount);
        } catch (BelowMinimumPriceException $e) {
            $this->addError("discount.{$garmentId}", $e->getMessage());

            return;
        } catch (\RuntimeException $e) {
            $this->addError("discount.{$garmentId}", $e->getMessage());

            return;
        }
        $this->syncCartState($cart);
    }

    public function clearCart(): void
    {
        $this->authorize('create', SaleInvoice::class);

        $cart = $this->cartState();
        $cart->clear();
        $this->syncCartState($cart);
        $this->discountInputs = [];
        $this->scanSuccess = __('Cart cleared.');
    }

    /**
     * Emit to the checkout component (Session 3.4). For 3.3 this is a
     * no-op trigger the view wires; the checkout component will be added
     * in the next session and listen for `openCheckout`.
     */
    public function proceedToCheckout(): void
    {
        $this->authorize('create', SaleInvoice::class);

        $cart = $this->cartState();
        if ($cart->isEmpty()) {
            $this->scanError = __('Cart is empty.');

            return;
        }

        $this->emit('openCheckout', ['items' => $cart->toArray(), 'saleType' => $this->saleType]);
    }

    /**
     * Re-sync the cart from the checkout component after it closes
     * (e.g., after a completed sale the checkout clears the cart).
     */
    public function refreshCart(array $items = []): void
    {
        // Rebuild CartState from the array payload.
        $rebuilt = [];
        foreach ($items as $row) {
            $rebuilt[$row['garment_id']] = new CartItem(
                garmentId: $row['garment_id'],
                barcode: $row['barcode'],
                serial: $row['serial'],
                productName: $row['product_name'],
                sizeLabel: $row['size_label'],
                standardPrice: $row['standard_price'],
                appliedDiscount: $row['applied_discount'],
                minimumPrice: $row['minimum_price'],
                isClearance: $row['is_clearance'],
            );
        }
        $cart = new CartState;
        $cart->items = $rebuilt;
        $this->syncCartState($cart);
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        $this->authorize('create', SaleInvoice::class);

        $cart = $this->cartState();

        return view('livewire.sales.pos.point-of-sale', [
            'cartItems' => $cart->toArray(),
            'subtotal' => $cart->subtotal(),
            'totalDiscount' => $cart->totalDiscount(),
            'totalAmount' => $cart->total(),
            'itemCount' => $cart->count(),
            'searchResults' => $this->searchResults,
            'branchName' => $this->activeBranch()?->name,
        ])->layout('layouts.app');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Reconstruct the CartState from the serialized $cartItems property.
     */
    protected function cartState(): CartState
    {
        $state = new CartState;
        foreach ($this->cartItems as $row) {
            $state->items[$row['garment_id']] = new CartItem(
                garmentId: $row['garment_id'],
                barcode: $row['barcode'],
                serial: $row['serial'],
                productName: $row['product_name'],
                sizeLabel: $row['size_label'],
                standardPrice: $row['standard_price'],
                appliedDiscount: $row['applied_discount'],
                minimumPrice: $row['minimum_price'],
                isClearance: $row['is_clearance'],
            );
        }

        return $state;
    }

    /**
     * Serialize the CartState back into the $cartItems public property.
     */
    protected function syncCartState(CartState $cart): void
    {
        $this->cartItems = $cart->toArray();
    }

    /**
     * Normalize a raw discount input to a decimal:2 string.
     */
    protected function normalizeDiscount(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '0.00';
        }
        // Strip anything that's not a digit or dot.
        $clean = preg_replace('/[^0-9.]/', '', $raw);
        if ($clean === '' || $clean === '.') {
            return '0.00';
        }

        return bcadd($clean, '0', 2);
    }

    /**
     * Human-readable rejection reason for a non-saleable garment.
     */
    protected function rejectionReason(Garment $garment): string
    {
        return match ($garment->current_status) {
            Garment::STATUS_SOLD => __('Garment :barcode has already been sold.', ['barcode' => $garment->barcode]),
            Garment::STATUS_RESERVED => __('Garment :barcode is reserved for a transfer.', ['barcode' => $garment->barcode]),
            Garment::STATUS_IN_TRANSIT => __('Garment :barcode is in transit.', ['barcode' => $garment->barcode]),
            Garment::STATUS_DAMAGED => __('Garment :barcode is damaged.', ['barcode' => $garment->barcode]),
            Garment::STATUS_MISSING => __('Garment :barcode is missing.', ['barcode' => $garment->barcode]),
            Garment::STATUS_FROZEN => __('Garment :barcode is frozen for stock count.', ['barcode' => $garment->barcode]),
            Garment::STATUS_UNDER_INSPECTION => __('Garment :barcode is under inspection.', ['barcode' => $garment->barcode]),
            default => __('Garment :barcode is not available for sale.', ['barcode' => $garment->barcode]),
        };
    }

    /**
     * The active branch (the salesperson's assigned branch), set by the
     * SetActiveBranchScope middleware as app('active.branch').
     */
    protected function activeBranch(): ?Branch
    {
        $branch = app()->bound('active.branch') ? app('active.branch') : null;
        if ($branch) {
            return $branch;
        }

        return auth()->user()?->assignedBranch;
    }
}
