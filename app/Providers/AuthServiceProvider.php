<?php

namespace App\Providers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Policies\PricePolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Policies\ProductSizePolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Policies\GarmentPolicy;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Models\StockLocation;
use App\Modules\Organization\Policies\BranchPolicy;
use App\Modules\Organization\Policies\FiscalYearPolicy;
use App\Modules\Organization\Policies\StockLocationPolicy;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Policies\PurchasePolicy;
use App\Modules\Purchasing\Policies\SupplierPolicy;
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
        User::class => UserPolicy::class,
        Branch::class => BranchPolicy::class,
        StockLocation::class => StockLocationPolicy::class,
        FiscalYear::class => FiscalYearPolicy::class,
        Product::class => ProductPolicy::class,
        ProductSize::class => ProductSizePolicy::class,
        ProductPrice::class => PricePolicy::class,
        Garment::class => GarmentPolicy::class,
        Supplier::class => SupplierPolicy::class,
        Purchase::class => PurchasePolicy::class,
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

        // Gate: Determine if user can view goods receipts. Admins see all
        // receipts. Main-Branch receiving staff (branch supervisors assigned
        // to the Main Branch) can view receipts — the records of their own
        // receiving workflow (WF-2). Supplier identity and purchase cost are
        // still stripped from these views for non-admins (Security §4.3), so
        // access to the receipts list does not expose sensitive data.
        Gate::define('view-goods-receipts', function ($user) {
            if ($user->hasRole('main_branch_admin')) {
                return true;
            }

            return $user->hasRole('branch_supervisor')
                && $user->assignedBranch?->type === 'main';
        });

        // Gate: Determine if user can print garment labels (admin + receiving
        // staff). Mirrors PurchasePolicy::receive — label printing is part of
        // the goods receiving workflow (WF-2), so the same staff can print.
        Gate::define('print-labels', function ($user) {
            return $user->hasRole(['main_branch_admin', 'branch_supervisor']);
        });

        // Gate: Determine if user can manage printer/barcode settings
        // (admin only — equivalent to the system_settings.manage permission).
        Gate::define('manage-printer-settings', function ($user) {
            return $user->hasRole('main_branch_admin');
        });
    }
}
