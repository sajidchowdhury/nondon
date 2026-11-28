<?php

namespace Database\Seeders;

use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the 5 core roles defined in the BRD and assigns base permissions.
     * All roles can sell (supervision/admin is an additional layer).
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions grouped by module
        $permissions = $this->getPermissionDefinitions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'] ?? null,
                    'module' => $permission['module'] ?? null,
                    'description' => $permission['description'] ?? null,
                    'guard_name' => 'web',
                ]
            );
        }

        // Define roles and their permissions
        $roles = $this->getRoleDefinitions();

        foreach ($roles as $roleName => $roleConfig) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                [
                    'display_name' => $roleConfig['display_name'],
                    'description' => $roleConfig['description'],
                    'guard_name' => 'web',
                ]
            );

            if (! empty($roleConfig['permissions'])) {
                $role->syncPermissions($roleConfig['permissions']);
            }
        }
    }

    /**
     * Get all permission definitions.
     */
    protected function getPermissionDefinitions(): array
    {
        return [
            // POS Sales
            ['name' => 'pos.sale.create', 'module' => 'sales', 'display_name' => 'Create POS Sale'],
            ['name' => 'pos.sale.complete', 'module' => 'sales', 'display_name' => 'Complete POS Sale'],
            ['name' => 'pos.discount.apply', 'module' => 'sales', 'display_name' => 'Apply Discount'],

            // Transfers
            ['name' => 'transfer.request.create', 'module' => 'transfers', 'display_name' => 'Create Transfer Request'],
            ['name' => 'transfer.approve', 'module' => 'transfers', 'display_name' => 'Approve Transfer'],
            ['name' => 'transfer.dispatch', 'module' => 'transfers', 'display_name' => 'Dispatch Transfer'],
            ['name' => 'transfer.receive', 'module' => 'transfers', 'display_name' => 'Receive Transfer'],

            // Returns
            ['name' => 'return.initiate', 'module' => 'returns', 'display_name' => 'Initiate Return'],
            ['name' => 'return.approve.limited', 'module' => 'returns', 'display_name' => 'Approve Return (within limit)'],
            ['name' => 'return.approve.unlimited', 'module' => 'returns', 'display_name' => 'Approve Return (above limit)'],

            // Cash
            ['name' => 'cash.handover.initiate', 'module' => 'cash', 'display_name' => 'Initiate Cash Handover'],
            ['name' => 'cash.handover.receive', 'module' => 'cash', 'display_name' => 'Receive Cash Handover'],
            ['name' => 'cash.closing.submit', 'module' => 'cash', 'display_name' => 'Submit Daily Cash Closing'],
            ['name' => 'cash.discrepancy.resolve', 'module' => 'cash', 'display_name' => 'Resolve Cash Discrepancy'],

            // Inventory
            ['name' => 'inventory.view.all_branches', 'module' => 'inventory', 'display_name' => 'View All Branches Stock'],
            ['name' => 'inventory.adjust.approve', 'module' => 'inventory', 'display_name' => 'Approve Stock Adjustment'],

            // Customers
            ['name' => 'customer.create', 'module' => 'customers', 'display_name' => 'Create Customer'],
            ['name' => 'customer.edit', 'module' => 'customers', 'display_name' => 'Edit Customer'],
            ['name' => 'customer.merge', 'module' => 'customers', 'display_name' => 'Merge Customers'],

            // Catalog & Pricing
            ['name' => 'product.create', 'module' => 'catalog', 'display_name' => 'Create Product'],
            ['name' => 'product.price.update', 'module' => 'catalog', 'display_name' => 'Update Prices'],

            // Purchasing
            ['name' => 'purchase.create', 'module' => 'purchasing', 'display_name' => 'Create Purchase'],
            ['name' => 'purchase.receive', 'module' => 'purchasing', 'display_name' => 'Receive Goods'],
            ['name' => 'purchase.cost.view', 'module' => 'purchasing', 'display_name' => 'View Purchase Cost'],
            ['name' => 'supplier.manage', 'module' => 'purchasing', 'display_name' => 'Manage Suppliers'],
            ['name' => 'supplier.payment.record', 'module' => 'purchasing', 'display_name' => 'Record Supplier Payment'],

            // Expenses
            ['name' => 'expense.submit', 'module' => 'expenses', 'display_name' => 'Submit Expense'],
            ['name' => 'expense.approve', 'module' => 'expenses', 'display_name' => 'Approve Expense'],

            // Online Orders
            ['name' => 'online.order.create', 'module' => 'online', 'display_name' => 'Create Online Order'],
            ['name' => 'online.order.confirm', 'module' => 'online', 'display_name' => 'Confirm Online Order'],
            ['name' => 'online.order.cancel', 'module' => 'online', 'display_name' => 'Cancel Online Order'],
            ['name' => 'online.order.dispatch', 'module' => 'online', 'display_name' => 'Dispatch Online Order'],
            ['name' => 'online.refund.approve.limited', 'module' => 'online', 'display_name' => 'Approve Online Refund (within limit)'],
            ['name' => 'online.reservation.release', 'module' => 'online', 'display_name' => 'Release Reservation'],
            ['name' => 'courier.settlement.reconcile', 'module' => 'courier', 'display_name' => 'Reconcile Courier Settlement'],

            // Stock Count
            ['name' => 'stock_count.initiate', 'module' => 'stock_count', 'display_name' => 'Initiate Stock Count'],
            ['name' => 'stock_count.freeze', 'module' => 'stock_count', 'display_name' => 'Apply Stock Count Freeze'],

            // Reporting
            ['name' => 'report.view.own_today', 'module' => 'reporting', 'display_name' => 'View Own Today Activity'],
            ['name' => 'report.view.branch_today', 'module' => 'reporting', 'display_name' => 'View Branch Today Reports'],
            ['name' => 'report.view.all_today', 'module' => 'reporting', 'display_name' => 'View All Branches Today Reports'],
            ['name' => 'report.view.historical', 'module' => 'reporting', 'display_name' => 'View Historical Reports'],
            ['name' => 'report.view.purchase_cost', 'module' => 'reporting', 'display_name' => 'View Purchase Cost Reports'],
            ['name' => 'report.view.profit', 'module' => 'reporting', 'display_name' => 'View Profit Reports'],
            ['name' => 'report.view.audit', 'module' => 'reporting', 'display_name' => 'View Audit Logs'],

            // Admin
            ['name' => 'employee.manage', 'module' => 'admin', 'display_name' => 'Manage Employees'],
            ['name' => 'fiscal_year.manage', 'module' => 'admin', 'display_name' => 'Manage Fiscal Years'],
            ['name' => 'system_settings.manage', 'module' => 'admin', 'display_name' => 'Manage System Settings'],
        ];
    }

    /**
     * Get role definitions with their permissions.
     *
     * Per BRD: All roles can sell. Supervision/admin is an additional layer.
     */
    protected function getRoleDefinitions(): array
    {
        return [
            'main_branch_admin' => [
                'display_name' => 'Main Branch Admin',
                'description' => 'Full business authority. All modules, all branches, all dates. Also sells.',
                'permissions' => Permission::all()->pluck('name')->toArray(),
            ],

            'branch_supervisor' => [
                'display_name' => 'Branch Supervisor',
                'description' => 'Own branch operations, approvals, cash management, today-only aggregate reports. Also sells.',
                'permissions' => [
                    // Selling (all roles can sell)
                    'pos.sale.create', 'pos.sale.complete', 'pos.discount.apply',
                    // Transfers
                    'transfer.request.create', 'transfer.approve', 'transfer.dispatch', 'transfer.receive',
                    // Returns
                    'return.initiate', 'return.approve.limited',
                    // Cash
                    'cash.handover.initiate', 'cash.handover.receive', 'cash.closing.submit',
                    // Inventory
                    'inventory.view.all_branches',
                    // Customers
                    'customer.create',
                    // Stock count
                    'stock_count.initiate', 'stock_count.freeze',
                    // Expenses
                    'expense.submit',
                    // Reporting (own branch, today only)
                    'report.view.own_today', 'report.view.branch_today', 'report.view.audit',
                ],
            ],

            'salesperson' => [
                'display_name' => 'Salesperson',
                'description' => 'Base selling, customer lookup, own cash, own today-only activity.',
                'permissions' => [
                    // Selling
                    'pos.sale.create', 'pos.sale.complete', 'pos.discount.apply',
                    // Inventory (view only)
                    'inventory.view.all_branches',
                    // Customers
                    'customer.create',
                    // Cash
                    'cash.handover.initiate',
                    // Reporting (own today only)
                    'report.view.own_today',
                ],
            ],

            'online_team' => [
                'display_name' => 'Online Sales Team',
                'description' => 'Online order management, refund initiation, courier operational tracking.',
                'permissions' => [
                    // Online orders
                    'online.order.create', 'online.order.confirm', 'online.order.cancel', 'online.order.dispatch',
                    // Inventory (view all branches for sourcing)
                    'inventory.view.all_branches',
                    // Customers
                    'customer.create',
                    // Reporting (own today only)
                    'report.view.own_today',
                ],
            ],

            'online_supervisor' => [
                'display_name' => 'Online Sales Supervisor',
                'description' => 'Full online-channel authority (branch-equivalent). Online refunds, courier settlement, cash closing.',
                'permissions' => [
                    // Online orders (same as team plus refund approval)
                    'online.order.create', 'online.order.confirm', 'online.order.cancel', 'online.order.dispatch',
                    'online.refund.approve.limited', 'online.reservation.release',
                    // Courier reconciliation
                    'courier.settlement.reconcile',
                    // Inventory
                    'inventory.view.all_branches',
                    // Customers
                    'customer.create',
                    // Cash
                    'cash.handover.receive', 'cash.closing.submit',
                    // Expenses
                    'expense.submit',
                    // Reporting (online channel, today only)
                    'report.view.own_today', 'report.view.branch_today',
                ],
            ],
        ];
    }
}
