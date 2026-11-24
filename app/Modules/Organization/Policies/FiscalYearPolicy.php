<?php

namespace App\Modules\Organization\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\FiscalYear;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FiscalYearPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of fiscal years.
     * Admin and supervisors can view. Salespersons cannot.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor']);
    }

    /**
     * Determine whether the user can view a specific fiscal year.
     */
    public function view(User $user, FiscalYear $fiscalYear): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        // Supervisors can view fiscal years (they need to know the current FY)
        if ($user->hasRole(['branch_supervisor', 'online_supervisor'])) {
            return Response::allow();
        }

        return Response::deny(__('You are not authorized to view fiscal years.'));
    }

    /**
     * Determine whether the user can create fiscal years.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can close a fiscal year.
     * Admin only.
     */
    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can activate a fiscal year.
     * Admin only.
     */
    public function activate(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a fiscal year.
     * Admin only. (Fiscal years are generally immutable once created,
     * but admin may need to fix metadata.)
     */
    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a fiscal year.
     * Fiscal years are never deleted.
     */
    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        return false;
    }
}
