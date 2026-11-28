<?php

use App\Modules\Audit\Http\Middleware\LogDataAccess;
use App\Modules\Identity\Http\Middleware\EnsureActiveBranch;
use App\Modules\Identity\Http\Middleware\ForcePasswordChangeMiddleware;
use App\Modules\Identity\Http\Middleware\SetActiveBranchScope;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (adjust for production)
        $middleware->trustProxies(at: '*');

        // Branch scope middleware - applies to all authenticated routes
        $middleware->append(SetActiveBranchScope::class);

        // Force password change middleware - redirects to password change page
        // when force_password_change flag is true (after admin reset or first login).
        // Added to web middleware group so it runs after authentication but before
        // route handling. Exempt routes: password.force-change, logout, login.
        $middleware->web(append: [
            ForcePasswordChangeMiddleware::class,
        ]);

        // Alias middleware for role-based checks
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active.branch' => EnsureActiveBranch::class,
            'force.password' => ForcePasswordChangeMiddleware::class,
            'log.data-access' => LogDataAccess::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Phase 0: No scheduled jobs yet. Jobs will be added in later phases:
        // - Hourly: Reconcile stock projection against movement ledger
        // - Daily: Generate daily sales summary, send scheduled reports
        // - Weekly: Database backup
        // - Monthly: Create next month's partition
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
