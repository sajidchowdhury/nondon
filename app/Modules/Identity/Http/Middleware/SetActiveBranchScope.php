<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that sets the active branch scope for the current user.
 *
 * This middleware runs on every request. It ensures that:
 * - If the user has an assigned branch, that branch is available globally.
 * - Branch-scoped queries use this branch for filtering.
 *
 * The actual branch scope enforcement is done via Eloquent global scopes
 * on branch-sensitive models.
 */
class SetActiveBranchScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = auth()->user()) {
            // Store the active branch in the application container
            // so models can access it via app('active.branch')
            if ($user->assigned_branch_id) {
                app()->instance('active.branch', $user->assignedBranch);
            }
        }

        return $next($request);
    }
}
