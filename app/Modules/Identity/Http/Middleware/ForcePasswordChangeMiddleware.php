<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that redirects users to the forced password change page
 * when force_password_change is true.
 *
 * This middleware ensures that:
 * - After admin password reset → user must change password on next login
 * - After first login with default credentials → user must change password
 * - When admin forces password change → user must change before accessing system
 *
 * The middleware allows access to:
 * - The forced password change page itself
 * - The logout route
 * - The health check endpoint
 * - Livewire component calls from the force-change page
 *
 * All other routes are redirected to the force-change page.
 */
class ForcePasswordChangeMiddleware
{
    /**
     * Routes that are exempt from the force-password-change redirect.
     */
    protected const EXEMPT_ROUTE_NAMES = [
        'password.force-change',
        'logout',
        'login',
        'role.select',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip for guests
        if (! $user) {
            return $next($request);
        }

        // Skip if force_password_change is not set
        if (! $user->force_password_change) {
            return $next($request);
        }

        // Allow access to exempt routes
        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::EXEMPT_ROUTE_NAMES)) {
            return $next($request);
        }

        // Allow health check
        if ($request->path() === 'up' || $request->path() === 'api/health') {
            return $next($request);
        }

        // Allow Livewire calls from the force-change page
        // Livewire requests have a specific header and refer to the current page
        if ($request->hasHeader('X-Livewire')) {
            // Check if the Livewire component is the ForcePasswordChange component
            $snapshot = $request->input('components.0.snapshot');
            if ($snapshot) {
                $decoded = json_decode($snapshot, true);
                if (isset($decoded['fingerprint']['name']) &&
                    $decoded['fingerprint']['name'] === 'password.force-change') {
                    return $next($request);
                }
            }
        }

        // Redirect all other requests to the force-change page
        if (! $request->expectsJson()) {
            return redirect()->route('password.force-change');
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'force_password_change',
                'message' => __('You must change your password before continuing.'),
                'redirect' => route('password.force-change'),
            ],
        ], 403);
    }
}
