<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Phase 0 seeders:
     *   1. RolePermissionSeeder — creates 5 roles with base permissions
     *   2. BranchSeeder — creates Main, B1, B2, B3, Online branches
     *   3. FiscalYearSeeder — creates the current fiscal year
     *   4. AdminUserSeeder — creates the initial admin user
     *
     * Order matters: roles must exist before admin user can be assigned one,
     * branches must exist before admin user can be assigned to a branch.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BranchSeeder::class,
            FiscalYearSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
