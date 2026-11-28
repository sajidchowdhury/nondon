<?php

namespace App\Providers;

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Organization\OrganizationServiceProvider;
use App\Modules\Purchasing\PurchasingServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use App\Modules\Transfers\TransfersServiceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register module service providers
        $this->registerModules();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register all module service providers.
     *
     * Modules are self-contained domain packages. Each module has its own
     * ServiceProvider that registers its routes, migrations, views, and services.
     *
     * Module order matters for dependency resolution:
     * - Foundation modules (Identity, Organization) load first
     * - Business modules (Catalog, Inventory, Sales, etc.) load after
     */
    protected function registerModules(): void
    {
        $modules = [
            // Foundation modules
            IdentityServiceProvider::class,
            OrganizationServiceProvider::class,
            AuditServiceProvider::class,
            CatalogServiceProvider::class,
            InventoryServiceProvider::class,

            // Business modules
            PurchasingServiceProvider::class,
            CustomersServiceProvider::class,
            SalesServiceProvider::class,
            TransfersServiceProvider::class,
            // \App\Modules\OnlineOrders\OnlineOrdersServiceProvider::class,
            // \App\Modules\Returns\ReturnsServiceProvider::class,
            // \App\Modules\Cash\CashServiceProvider::class,
            // \App\Modules\CourierReconciliation\CourierReconciliationServiceProvider::class,
            // \App\Modules\StockCount\StockCountServiceProvider::class,
            // \App\Modules\DamagedStock\DamagedStockServiceProvider::class,
            // \App\Modules\Expenses\ExpensesServiceProvider::class,
            // \App\Modules\SupplierPayments\SupplierPaymentsServiceProvider::class,
            // \App\Modules\Reporting\ReportingServiceProvider::class,
            // \App\Modules\Audit\AuditServiceProvider::class,
            // \App\Modules\Notifications\NotificationsServiceProvider::class,
            // \App\Modules\SystemSettings\SystemSettingsServiceProvider::class,
        ];

        foreach ($modules as $provider) {
            $this->app->register($provider);
        }
    }
}
