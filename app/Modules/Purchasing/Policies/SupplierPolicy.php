<?php

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class SupplierPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of suppliers.
     *
     * Only Main Branch Admin can view suppliers (per BRD §4.1):
     * "Only Main Branch communicates with suppliers. Other branches must not
     * see supplier details, supplier contacts, or purchase costs."
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can view a specific supplier.
     * Admin only.
     */
    public function view(User $user, Supplier $supplier): Response
    {
        return $user->hasRole('main_branch_admin')
            ? Response::allow()
            : Response::deny(__('You do not have permission to view supplier information.'));
    }

    /**
     * Determine whether the user can create suppliers.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a supplier.
     * Admin only.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can activate/deactivate a supplier.
     * Admin only.
     */
    public function toggleStatus(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a supplier.
     * Suppliers are never deleted — they are deactivated.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return false;
    }
}
