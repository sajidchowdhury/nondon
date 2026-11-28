<?php

namespace App\Modules\Sales\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\SaleInvoice;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * SaleInvoice authorization policy.
 *
 * Permission matrix (doc 05 §4.1, §5.1, ADR-007):
 *  - main_branch_admin: all invoices, all dates.
 *  - branch_supervisor: own branch invoices, today only (ADR-018).
 *  - salesperson: own invoices, today only.
 *  - online_team / online_supervisor: N/A (no POS sales).
 *
 * Date restrictions follow ADR-018: single-record lookup is allowed any
 * date for operational purposes (returns), but the aggregate report
 * endpoints enforce today-only for non-admin. Here, view() is single-record
 * lookup and applies the today-only rule for non-admin per doc 05 §5.1.
 */
class SaleInvoicePolicy
{
    use HandlesAuthorization;

    /**
     * View the sale-invoice list (own/branch/today-scoped per role).
     * Any role with pos.sale.create can see their own list; the service scopes rows.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sale.create');
    }

    /**
     * View a specific invoice.
     *  - admin: all.
     *  - branch_supervisor: own branch + today only.
     *  - salesperson: own invoice + today only.
     */
    public function view(User $user, SaleInvoice $invoice): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        if ($user->hasRole('branch_supervisor')) {
            return ($invoice->branch_id === $user->assigned_branch_id
                    && $invoice->completed_at?->isToday())
                ? Response::allow()
                : Response::deny(__('You can only view today\'s sales at your own branch.'));
        }

        if ($user->hasRole('salesperson')) {
            return ($invoice->salesperson_id === $user->id
                    && $invoice->completed_at?->isToday())
                ? Response::allow()
                : Response::deny(__('You can only view your own sales from today.'));
        }

        return Response::deny(__('You do not have permission to view this sale.'));
    }

    /**
     * Create a new sale (build the cart). Requires pos.sale.create and the
     * user must be able to sell (active + assigned branch).
     */
    public function create(User $user): bool
    {
        return $user->can('pos.sale.create') && $user->canSell();
    }

    /**
     * Complete (commit) a sale. Requires pos.sale.complete and the sale
     * must be at the user's assigned branch.
     */
    public function complete(User $user, SaleInvoice $invoice): bool
    {
        return $user->can('pos.sale.complete')
            && $invoice->branch_id === $user->assigned_branch_id;
    }

    /**
     * Apply a discount to a sale line. Requires pos.discount.apply.
     */
    public function applyDiscount(User $user): bool
    {
        return $user->can('pos.discount.apply');
    }

    /**
     * Void a completed sale. NOT supported in Phase 3 — completed invoices
     * are immutable; corrections go through the Returns module (Phase 5).
     */
    public function void(User $user, SaleInvoice $invoice): bool
    {
        return false;
    }

    /**
     * Sales invoices are never deleted.
     */
    public function delete(User $user, SaleInvoice $invoice): bool
    {
        return false;
    }
}
