<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the initial Main Branch Admin user.
     * This user has full access to all modules and branches.
     *
     * Default credentials (change immediately after first login):
     *   Username: admin
     *   Password: Nondon@2025
     */
    public function run(): void
    {
        $mainBranch = Branch::where('code', 'M')->first();

        if (!$mainBranch) {
            $this->command->error('Main Branch not found. Run BranchSeeder first.');
            return;
        }

        $adminRole = Role::where('name', 'main_branch_admin')->first();

        if (!$adminRole) {
            $this->command->error('Main Branch Admin role not found. Run RolePermissionSeeder first.');
            return;
        }

        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'email' => 'admin@nondon.local',
                'full_name' => 'System Administrator',
                'password_hash' => Hash::make('Nondon@2025'),
                'assigned_branch_id' => $mainBranch->id,
                'is_online_channel' => false,
                'is_active' => true,
                'force_password_change' => true, // Force change on first login
            ]
        );

        $user->assignRole($adminRole);
        $user->update(['active_role_id' => $adminRole->id]);

        $this->command->info('Admin user created:');
        $this->command->info('  Username: admin');
        $this->command->info('  Password: Nondon@2025');
        $this->command->warn('  Please change the password immediately after first login.');
    }
}
