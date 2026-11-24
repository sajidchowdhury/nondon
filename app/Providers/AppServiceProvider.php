<?php

namespace App\Providers;

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
            \App\Modules\Identity\IdentityServiceProvider::class,
            \App\Modules\Organization\OrganizationServiceProvider::class,
            \App\Modules\Audit\AuditServiceProvider::class,
            \App\Modules\Catalog\CatalogServiceProvider::class,

            // Business modules (to be added in later phases)
            // \App\Modules\Purchasing\PurchasingServiceProvider::class,
            // \App\Modules\Inventory\InventoryServiceProvider::class,
            // \App\Modules\Transfers\TransfersServiceProvider::class,
            // \App\Modules\Sales\SalesServiceProvider::class,
            // \App\Modules\OnlineOrders\OnlineOrdersServiceProvider::class,
            // \App\Modules\Returns\ReturnsServiceProvider::class,
            // \App\Modules\Cash\CashServiceProvider::class,
            // \App\Modules\CourierReconciliation\CourierReconciliationServiceProvider::class,
            // \App\Modules\StockCount\StockCountServiceProvider::class,
            // \App\Modules\DamagedStock\DamagedStockServiceProvider::class,
            // \App\Modules\Expenses\ExpensesServiceProvider::class,
            // \App\Modules\SupplierPayments\SupplierPaymentsServiceProvider::class,
            // \App\Modules\Customers\CustomersServiceProvider::class,
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
