<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

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
        $middleware->append(\App\Modules\Identity\Http\Middleware\SetActiveBranchScope::class);

        // Force password change middleware - redirects to password change page
        // when force_password_change flag is true (after admin reset or first login).
        // Added to web middleware group so it runs after authentication but before
        // route handling. Exempt routes: password.force-change, logout, login.
        $middleware->web(append: [
            \App\Modules\Identity\Http\Middleware\ForcePasswordChangeMiddleware::class,
        ]);

        // Alias middleware for role-based checks
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active.branch' => \App\Modules\Identity\Http\Middleware\EnsureActiveBranch::class,
            'force.password' => \App\Modules\Identity\Http\Middleware\ForcePasswordChangeMiddleware::class,
            'log.data-access' => \App\Modules\Audit\Http\Middleware\LogDataAccess::class,
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
