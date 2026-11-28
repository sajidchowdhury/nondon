<?php

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the customer list.
     *
     * Any authenticated user with the customer.create permission may view the
     * list (all selling roles). PII scope is applied at the query/service layer
     * (CustomerService::applyPiiScope), not here — the policy only authorizes
     * the action; the service decides which rows are visible.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('customer.create');
    }

    /**
     * Determine whether the user can view a specific customer.
     *
     * PII scope (Security §4.2, §7.4):
     *  - main_branch_admin: all customers.
     *  - online_team / online_supervisor: only is_online_customer customers.
     *  - branch_supervisor / salesperson: walk-in (retail) customers only.
     *
     * The "has a sale at the viewer's branch" refinement is added in
     * Session 3.10 once sale_invoices exist; in 3.1, branch roles see all
     * walk-in customers (any physical branch may serve a walk-in).
     */
    public function view(User $user, Customer $customer): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        if ($user->hasRole(['online_team', 'online_supervisor'])) {
            return $customer->is_online_customer
                ? Response::allow()
                : Response::deny(__('You do not have permission to view this customer.'));
        }

        if ($user->hasRole(['branch_supervisor', 'salesperson'])) {
            return ! $customer->is_online_customer
                ? Response::allow()
                : Response::deny(__('You do not have permission to view this customer.'));
        }

        return Response::deny(__('You do not have permission to view this customer.'));
    }

    /**
     * Determine whether the user can create a customer.
     *
     * All selling roles have customer.create (supervisor, salesperson, online
     * team, online supervisor) plus admin.
     */
    public function create(User $user): bool
    {
        return $user->can('customer.create');
    }

    /**
     * Determine whether the user can edit a customer.
     * Admin only (customer.edit permission, granted only to main_branch_admin).
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customer.edit');
    }

    /**
     * Determine whether the user can deactivate a customer.
     * Admin only.
     */
    public function deactivate(User $user, Customer $customer): bool
    {
        return $user->can('customer.edit');
    }

    /**
     * Determine whether the user can merge duplicate customers (WF-25).
     * Admin only (customer.merge permission).
     *
     * The merge UI itself is implemented in Session 3.10.
     */
    public function merge(User $user): bool
    {
        return $user->can('customer.merge');
    }

    /**
     * Customers are never deleted — they are deactivated or merged.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
