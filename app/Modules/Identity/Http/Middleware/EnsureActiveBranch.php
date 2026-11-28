<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures the user has an active branch set.
 *
 * Used for routes that require branch context (POS, transfers, cash, etc.).
 * Online channel users are exempt — they use the online channel as their context.
 */
class EnsureActiveBranch
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Online channel users don't need a physical branch
        if ($user->is_online_channel) {
            return $next($request);
        }

        // Physical branch users must have an assigned branch
        if (! $user->assigned_branch_id) {
            return redirect()->route('dashboard')
                ->with('error', __('No branch assigned. Contact administrator.'));
        }

        return $next($request);
    }
}
