<?php

namespace App\Modules\Sales\Http\Livewire\POS;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use App\Modules\Sales\Exceptions\GarmentNotAvailableException;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Services\SaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * POS checkout — the "commit the sale" half of WF-3.
 *
 * Listens for `openCheckout` from the PointOfSale component (which sends the
 * cart items + sale type). Collects the customer (phone lookup / walk-in
 * create via CustomerService::findOrCreateByPhone), one or more payment
 * methods, and validates `total_paid >= total_amount`. On "Complete Sale"
 * assembles the SaleService::complete() payload and commits atomically.
 *
 * On success: clears the local state, shows the invoice number + a "Print
 * receipt" link (routed in Session 3.5), and emits `checkoutClosed` to the
 * PointOfSale so it clears its cart.
 *
 * On a garment conflict (another cashier sold it mid-checkout): the
 * SaleService throws GarmentNotAvailableException — caught and surfaced
 * with the specific garment id; no invoice is created (atomic rollback),
 * and the cashier is told to remove that garment and retry.
 *
 * Authorization: pos.sale.complete required (SaleInvoicePolicy::complete
 * enforces branch match). Re-checked on every action.
 */
class Checkout extends Component
{
    protected $listeners = ['openCheckout' => 'mountFromCart'];

    /** @var array<int, array> Cart items received from PointOfSale. */
    public array $cartItems = [];

    public string $saleType = 'retail';

    // Customer state.
    public string $customerPhone = '';

    public string $customerName = '';

    public ?int $customerId = null;

    public ?string $foundCustomerName = null;

    public bool $customerLookupDone = false;

    public bool $walkIn = false;

    /** @var array<int, array{method: string, amount: string, reference: string|null}> */
    public array $payments = [];

    public string $notes = '';

    // Result state.
    public ?int $completedInvoiceId = null;

    public ?string $completedInvoiceNumber = null;

    public string $completionError = '';

    public string $completionSuccess = '';

    public function mount(): void
    {
        // Default to a single cash payment line.
        if (empty($this->payments)) {
            $this->payments = [['method' => 'cash', 'amount' => '', 'reference' => null]];
        }
    }

    /**
     * Receive the cart from PointOfSale (via the openCheckout event).
     */
    public function mountFromCart(array $payload): void
    {
        $this->authorize('create', SaleInvoice::class);

        $this->cartItems = $payload['items'] ?? [];
        $this->saleType = $payload['saleType'] ?? 'retail';
        $this->resetResultState();
    }

    /*
    |--------------------------------------------------------------------------
    | Customer
    |--------------------------------------------------------------------------
    */

    /**
     * Look up a customer by phone. If found, populate the id + name; if not,
     * mark for walk-in creation (CustomerService::findOrCreateByPhone at
     * completion time).
     */
    public function lookupCustomer(CustomerService $customerService)
    {
        $this->authorize('create', SaleInvoice::class);

        $phone = trim($this->customerPhone);
        $this->customerLookupDone = false;
        $this->foundCustomerName = null;
        $this->customerId = null;
        $this->completionError = '';

        if ($phone === '') {
            $this->addError('customerPhone', __('Enter a phone number to look up or create a customer.'));

            return;
        }

        /** @var User $viewer */
        $viewer = auth()->user();

        $matches = $customerService->search($phone, $viewer);
        if ($matches->isNotEmpty()) {
            /** @var Customer $customer */
            $customer = $matches->first();
            $this->customerId = $customer->id;
            $this->foundCustomerName = $customer->name ?? $customer->phone;
            $this->customerLookupDone = true;
        } else {
            // Not found — will be created as walk-in at completion.
            $this->customerLookupDone = true;
            $this->foundCustomerName = null;
        }
    }

    /**
     * Toggle "walk-in (no customer)" mode — clears the customer fields.
     * Per WF-3 the UI defaults to requiring a phone, but the service allows
     * null for true walk-in edge cases.
     */
    public function toggleWalkIn(): void
    {
        $this->authorize('create', SaleInvoice::class);

        $this->walkIn = ! $this->walkIn;
        if ($this->walkIn) {
            $this->customerPhone = '';
            $this->customerName = '';
            $this->customerId = null;
            $this->foundCustomerName = null;
            $this->customerLookupDone = false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    public function addPaymentMethod(): void
    {
        $this->authorize('create', SaleInvoice::class);

        $this->payments[] = ['method' => 'cash', 'amount' => '', 'reference' => null];
    }

    public function removePaymentMethod(int $index): void
    {
        $this->authorize('create', SaleInvoice::class);

        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
        if (empty($this->payments)) {
            $this->payments = [['method' => 'cash', 'amount' => '', 'reference' => null]];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Computed totals
    |--------------------------------------------------------------------------
    */

    public function cartTotal(): string
    {
        $sum = '0.00';
        foreach ($this->cartItems as $item) {
            $sum = bcadd($sum, (string) ($item['final_price'] ?? '0.00'), 2);
        }

        return $sum;
    }

    public function totalPaid(): string
    {
        $sum = '0.00';
        foreach ($this->payments as $p) {
            $amount = trim((string) ($p['amount'] ?? ''));
            if ($amount !== '' && is_numeric($amount)) {
                $sum = bcadd($sum, bcadd($amount, '0', 2), 2);
            }
        }

        return $sum;
    }

    public function changeDue(): string
    {
        $paid = $this->totalPaid();
        $total = $this->cartTotal();

        return bccomp($paid, $total, 2) > 0
            ? bcsub($paid, $total, 2)
            : '0.00';
    }

    public function isShort(): bool
    {
        return bccomp($this->totalPaid(), $this->cartTotal(), 2) < 0;
    }

    public function shortBy(): string
    {
        return bcsub($this->cartTotal(), $this->totalPaid(), 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Complete sale
    |--------------------------------------------------------------------------
    */

    public function completeSale(SaleService $saleService, CustomerService $customerService)
    {
        $this->authorizeComplete();

        $this->resetResultState();
        $this->completionError = '';

        if (empty($this->cartItems)) {
            $this->completionError = __('Cart is empty.');

            return;
        }

        // Resolve customer: walk-in toggle → null; otherwise phone is required
        // and resolves to an existing or new customer via findOrCreateByPhone.
        $customerId = null;
        if (! $this->walkIn) {
            $phone = trim($this->customerPhone);
            if ($phone === '') {
                $this->addError('customerPhone', __('Customer phone is required (or toggle Walk-in).'));

                return;
            }
            /** @var User $creator */
            $creator = auth()->user();
            $customer = $customerService->findOrCreateByPhone($phone, $creator, $this->customerName ?: null);
            $customerId = $customer->id;
        }

        // Build the items payload from the cart.
        $items = [];
        foreach ($this->cartItems as $item) {
            $items[] = [
                'garment_id' => (int) $item['garment_id'],
                'discount' => (float) ($item['applied_discount'] ?? 0),
            ];
        }

        // Build the payments payload. Validate inline.
        $payments = [];
        foreach ($this->payments as $index => $p) {
            $method = trim((string) ($p['method'] ?? ''));
            $amount = trim((string) ($p['amount'] ?? ''));

            if ($method === '') {
                $this->completionError = __('Payment method is required on line :n.', ['n' => $index + 1]);

                return;
            }
            if ($amount === '' || ! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
                $this->completionError = __('Payment amount must be greater than zero on line :n.', ['n' => $index + 1]);

                return;
            }

            $pm = PaymentMethod::findByName($method);
            if (! $pm || ! $pm->is_active) {
                $this->completionError = __('Payment method :method is not available.', ['method' => $method]);

                return;
            }
            if ($pm->requires_reference && blank($p['reference'] ?? null)) {
                $this->completionError = __('Payment method :method requires a reference number.', ['method' => $method]);

                return;
            }

            $payments[] = [
                'method' => $method,
                'amount' => (float) $amount,
                'reference' => $p['reference'] ?? null,
            ];
        }

        if (empty($payments)) {
            $this->completionError = __('At least one payment is required.');

            return;
        }

        // total_paid >= total_amount is enforced by SaleService, but surface
        // a friendly message early.
        if ($this->isShort()) {
            $this->completionError = __('Total paid is short by :amount.', ['amount' => $this->shortBy()]);

            return;
        }

        $branch = $this->activeBranch();
        if (! $branch) {
            $this->completionError = __('No active branch assigned to your account.');

            return;
        }

        /** @var User $salesperson */
        $salesperson = auth()->user();

        try {
            $invoice = $saleService->complete($salesperson, $branch, [
                'customer_id' => $customerId,
                'sale_type' => $this->saleType,
                'items' => $items,
                'payments' => $payments,
                'notes' => $this->notes ?: null,
            ]);

            $this->completedInvoiceId = $invoice->id;
            $this->completedInvoiceNumber = $invoice->invoice_number;
            $this->completionSuccess = __('Sale completed: :number', ['number' => $invoice->invoice_number]);

            // Clear the local cart + tell PointOfSale to clear its cart too.
            $this->cartItems = [];
            $this->dispatch('checkoutClosed', ['items' => []]);
        } catch (GarmentNotAvailableException $e) {
            $this->completionError = __('Garment :id is no longer available. Remove it from the cart and retry.', ['id' => $e->garmentId]);
        } catch (BelowMinimumPriceException $e) {
            $this->completionError = __('Discount on garment :id pushed the price below the minimum (:min). Adjust and retry.', [
                'id' => $e->garmentId,
                'min' => $e->minimumPrice,
            ]);
        } catch (ValidationException $e) {
            // Flatten the first error message for display.
            $first = collect($e->errors())->flatten()->first();
            $this->completionError = $first ?? __('The sale could not be completed.');
        } catch (AuthorizationException $e) {
            $this->completionError = $e->getMessage();
        }
    }

    /**
     * Reset back to the POS (after a completed sale or to abandon checkout).
     */
    public function backToPos(): void
    {
        $this->cartItems = [];
        $this->dispatch('checkoutClosed', ['items' => []]);
        $this->resetResultState();
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        $this->authorize('create', SaleInvoice::class);

        $paymentMethods = PaymentMethod::active()->orderBy('sort_order')->get();

        return view('livewire.sales.pos.checkout', [
            'paymentMethods' => $paymentMethods,
            'cartTotal' => $this->cartTotal(),
            'totalPaid' => $this->totalPaid(),
            'changeDue' => $this->changeDue(),
            'isShort' => $this->isShort(),
            'shortBy' => $this->shortBy(),
        ])->layout('layouts.app');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Authorization for completing a sale. pos.sale.complete is required; the
     * branch match is enforced by SaleService::complete() (which compares the
     * salesperson's assigned_branch_id to the sale branch). The policy's
     * complete() needs a persisted invoice, so the checkout (pre-invoice)
     * authorizes the permission + active-branch directly here.
     *
     * @throws AuthorizationException
     */
    protected function authorizeComplete(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if (! $user || ! $user->can('pos.sale.complete')) {
            throw new AuthorizationException(__('You do not have permission to complete a sale.'));
        }
        if (! $user->canSell()) {
            throw new AuthorizationException(__('Your account is not eligible to sell (inactive or no assigned branch).'));
        }
        if (! $this->activeBranch()) {
            throw new AuthorizationException(__('No active branch assigned to your account.'));
        }
    }

    protected function resetResultState(): void
    {
        $this->completedInvoiceId = null;
        $this->completedInvoiceNumber = null;
        $this->completionError = '';
        $this->completionSuccess = '';
    }

    protected function activeBranch(): ?Branch
    {
        $branch = app()->bound('active.branch') ? app('active.branch') : null;
        if ($branch) {
            return $branch;
        }

        return auth()->user()?->assignedBranch;
    }
}
