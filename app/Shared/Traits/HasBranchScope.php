<?php

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that are branch-sensitive.
 *
 * When applied to a model with a `branch_id` column, this trait automatically
 * filters all queries by the authenticated user's assigned branch:
 *
 * - Main Branch Admin: sees all branches (no filter applied)
 * - Branch Supervisor: sees only own branch
 * - Salesperson: sees only own branch
 * - Online channel users: sees only online channel
 *
 * To bypass the scope (e.g., for cross-branch stock visibility), use:
 *   Model::withoutBranchScope()->get();
 *
 * Usage:
 *   class SaleInvoice extends Model
 *   {
 *       use HasBranchScope;
 *   }
 *
 * The model must have a `branch_id` column.
 */
trait HasBranchScope
{
    /**
     * Boot the trait.
     *
     * Registers the global scope that filters by the user's branch.
     */
    public static function bootHasBranchScope(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            $user = Auth::user();

            // Skip scope for guests (e.g., during testing without auth)
            if (! $user) {
                return;
            }

            // Main Branch Admin sees all branches — no filter
            if ($user->hasRole('main_branch_admin')) {
                return;
            }

            // All other users see only their assigned branch
            if ($user->assigned_branch_id) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $user->assigned_branch_id);
            }
        });
    }

    /**
     * Get a new query builder without the branch scope.
     *
     * Use this for cross-branch queries (e.g., stock visibility
     * across all branches, which is allowed per the BRD).
     */
    public static function withoutBranchScope(): Builder
    {
        return static::withoutGlobalScope('branch');
    }

    /**
     * Get a query builder that includes all branches (alias).
     */
    public static function allBranches(): Builder
    {
        return static::withoutGlobalScope('branch');
    }

    /**
     * Get a query builder scoped to a specific branch.
     */
    public static function forBranch(int $branchId): Builder
    {
        return static::withoutGlobalScope('branch')->where('branch_id', $branchId);
    }
}
