<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<string, string>
     */
    protected $policies = [
        \App\Modules\Identity\Models\User::class => \App\Modules\Identity\Policies\UserPolicy::class,
        \App\Modules\Organization\Models\Branch::class => \App\Modules\Organization\Policies\BranchPolicy::class,
        \App\Modules\Organization\Models\StockLocation::class => \App\Modules\Organization\Policies\StockLocationPolicy::class,
        \App\Modules\Organization\Models\FiscalYear::class => \App\Modules\Organization\Policies\FiscalYearPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate: Determine if user can view purchase cost (admin only)
        Gate::define('view-purchase-cost', function ($user) {
            return $user->hasRole('main_branch_admin');
        });

        // Gate: Determine if user can view supplier information (admin only)
        Gate::define('view-supplier-info', function ($user) {
            return $user->hasRole('main_branch_admin');
        });

        // Gate: Determine if user can view all-branch historical reports (admin only)
        Gate::define('view-historical-reports', function ($user) {
            return $user->hasRole('main_branch_admin');
        });

        // Gate: Determine if user can view profit reports (admin only)
        Gate::define('view-profit-reports', function ($user) {
            return $user->hasRole('main_branch_admin');
        });
    }
}
