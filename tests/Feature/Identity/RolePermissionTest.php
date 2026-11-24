<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserPermission;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('RoleAssignmentService — assign role', function () {
    it('allows admin to assign a role to a user', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'branch_supervisor')->first();

        $updated = $service->assignRole($user, $role);

        expect($updated->hasRole('branch_supervisor'))->toBeTrue();
        expect($updated->hasRole('salesperson'))->toBeTrue();
    });

    it('is idempotent — assigning an existing role is a no-op', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'salesperson')->first();

        $updated = $service->assignRole($user, $role);

        expect($updated->roles->count())->toBe(1);
    });

    it('sets active role automatically when user has one role', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');
        $user->update(['active_role_id' => null]);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'salesperson')->first();

        $updated = $service->assignRole($user, $role);

        expect($updated->active_role_id)->not->toBeNull();
    });
});

describe('RoleAssignmentService — remove role', function () {
    it('allows admin to remove a role from a user', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'branch_supervisor')->first();

        $updated = $service->removeRole($user, $role);

        expect($updated->hasRole('branch_supervisor'))->toBeFalse();
        expect($updated->hasRole('salesperson'))->toBeTrue();
    });

    it('prevents removing the user\'s only role', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'salesperson')->first();

        expect(fn() => $service->removeRole($user, $role))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('updates active role when the active role is removed', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $supervisorRole = Role::where('name', 'branch_supervisor')->first();
        $user->update(['active_role_id' => $supervisorRole->id]);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);

        $updated = $service->removeRole($user, $supervisorRole);

        // Active role should be reset to the remaining role
        expect($updated->active_role_id)->not->toBe($supervisorRole->id);
        expect($updated->active_role_id)->not->toBeNull();
    });
});

describe('RoleAssignmentService — sync roles', function () {
    it('allows admin to sync multiple roles', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $roleIds = Role::whereIn('name', ['salesperson', 'branch_supervisor'])->pluck('id')->toArray();

        $updated = $service->syncRoles($user, $roleIds);

        expect($updated->roles->count())->toBe(2);
        expect($updated->hasRole('salesperson'))->toBeTrue();
        expect($updated->hasRole('branch_supervisor'))->toBeTrue();
    });

    it('rejects syncing with empty roles array', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);

        expect(fn() => $service->syncRoles($user, []))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('updates active role when it is removed during sync', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $supervisorRole = Role::where('name', 'branch_supervisor')->first();
        $user->update(['active_role_id' => $supervisorRole->id]);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $salespersonRole = Role::where('name', 'salesperson')->first();

        $updated = $service->syncRoles($user, [$salespersonRole->id]);

        expect($updated->active_role_id)->toBe($salespersonRole->id);
    });
});

describe('RoleAssignmentService — active role', function () {
    it('sets the active role for a user', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $user->update(['active_role_id' => null]);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'branch_supervisor')->first();

        $updated = $service->setActiveRole($user, $role);

        expect($updated->active_role_id)->toBe($role->id);
    });

    it('rejects setting active role the user does not have', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);
        $role = Role::where('name', 'main_branch_admin')->first();

        expect(fn() => $service->setActiveRole($user, $role))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('checks if user has multiple roles', function () {
        $branch = Branch::where('code', 'B1')->first();
        $singleRoleUser = User::factory()->forBranch($branch)->create();
        $singleRoleUser->assignRole('salesperson');

        $multiRoleUser = User::factory()->forBranch($branch)->create();
        $multiRoleUser->assignRole(['salesperson', 'branch_supervisor']);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);

        expect($service->hasMultipleRoles($singleRoleUser))->toBeFalse();
        expect($service->hasMultipleRoles($multiRoleUser))->toBeTrue();
    });
});

describe('RoleSwitcher — role switching', function () {
    it('user with multiple roles can switch between them', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $salespersonRole = Role::where('name', 'salesperson')->first();
        $supervisorRole = Role::where('name', 'branch_supervisor')->first();
        $user->update(['active_role_id' => $salespersonRole->id]);

        $service = app(\App\Modules\Identity\Services\RoleAssignmentService::class);

        // Switch to supervisor
        $updated = $service->setActiveRole($user, $supervisorRole);
        expect($updated->active_role_id)->toBe($supervisorRole->id);

        // Switch back to salesperson
        $updated = $service->setActiveRole($user, $salespersonRole);
        expect($updated->active_role_id)->toBe($salespersonRole->id);
    });

    it('shows role selector page for multi-role users', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $user->update(['active_role_id' => null]);

        $response = $this->actingAs($user)->get(route('role.select'));

        $response->assertStatus(200);
        $response->assertSee('Select Active Role');
    });
});

describe('PermissionOverrideService — grant permission', function () {
    it('allows admin to grant a custom permission to a user', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'transfer.request.create')->first();

        $updated = $service->grantPermission($user, $permission);

        $override = UserPermission::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->first();

        expect($override)->not->toBeNull();
        expect($override->granted)->toBeTrue();
    });

    it('prevents granting admin-only permission to non-admin user', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'purchase.cost.view')->first();

        expect(fn() => $service->grantPermission($user, $permission))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('updates existing revoke to grant when re-granting', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'pos.discount.apply')->first();

        // First revoke
        $service->revokePermission($user, $permission);
        $override = UserPermission::where('user_id', $user->id)->where('permission_id', $permission->id)->first();
        expect($override->granted)->toBeFalse();

        // Then grant
        $service->grantPermission($user, $permission);
        $override = $override->fresh();
        expect($override->granted)->toBeTrue();
    });
});

describe('PermissionOverrideService — revoke permission', function () {
    it('allows admin to revoke a permission from a user', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'pos.sale.create')->first();

        // Salesperson has pos.sale.create via role
        expect($user->can('pos.sale.create'))->toBeTrue();

        // Revoke it
        $service->revokePermission($user, $permission);

        $override = UserPermission::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->first();

        expect($override)->not->toBeNull();
        expect($override->granted)->toBeFalse();
    });

    it('can remove an override entirely', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'pos.discount.apply')->first();

        $service->grantPermission($user, $permission);
        expect(UserPermission::where('user_id', $user->id)->where('permission_id', $permission->id)->exists())->toBeTrue();

        $service->removeOverride($user, $permission);
        expect(UserPermission::where('user_id', $user->id)->where('permission_id', $permission->id)->exists())->toBeFalse();
    });
});

describe('PermissionOverrideService — effective permissions', function () {
    it('effective permissions = role permissions + grants - revokes', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        // Grant a permission not in the salesperson role
        $grantedPermission = Permission::where('name', 'transfer.request.create')->first();
        $service->grantPermission($user, $grantedPermission);

        // Revoke a permission that is in the salesperson role
        $revokedPermission = Permission::where('name', 'pos.sale.create')->first();
        $service->revokePermission($user, $revokedPermission);

        $effective = $service->getEffectivePermissions($user);

        // Should have the granted permission
        expect($effective->contains('name', 'transfer.request.create'))->toBeTrue();
        // Should NOT have the revoked permission
        expect($effective->contains('name', 'pos.sale.create'))->toBeFalse();
    });

    it('hasEffectivePermission returns correct result', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        // Salesperson has pos.sale.create via role
        expect($service->hasEffectivePermission($user, 'pos.sale.create'))->toBeTrue();

        // Revoke it
        $permission = Permission::where('name', 'pos.sale.create')->first();
        $service->revokePermission($user, $permission);

        // Should no longer have it
        expect($service->hasEffectivePermission($user, 'pos.sale.create'))->toBeFalse();
    });

    it('admin-only permissions are always false for non-admin users', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('branch_supervisor');

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        // Supervisor should not have admin-only permissions
        expect($service->hasEffectivePermission($user, 'purchase.cost.view'))->toBeFalse();
        expect($service->hasEffectivePermission($user, 'report.view.historical'))->toBeFalse();
        expect($service->hasEffectivePermission($user, 'supplier.manage'))->toBeFalse();
    });

    it('admin-only permissions are true for admin users', function () {
        $admin = adminUser();
        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        expect($service->hasEffectivePermission($admin, 'purchase.cost.view'))->toBeTrue();
        expect($service->hasEffectivePermission($admin, 'supplier.manage'))->toBeTrue();
    });
});

describe('sensitive data restrictions', function () {
    it('purchase cost hidden from salesperson regardless of overrides', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        // Try to grant purchase.cost.view via override
        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = Permission::where('name', 'purchase.cost.view')->first();

        // Grant should be rejected
        expect(fn() => $service->grantPermission($user, $permission))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        // Even if we manually insert a grant record, effective permissions should filter it out
        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => true,
            'granted_by' => adminUser()->id,
        ]);

        expect($service->hasEffectivePermission($user, 'purchase.cost.view'))->toBeFalse();
    });

    it('purchase cost visible to admin', function () {
        $admin = adminUser();
        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        expect($service->hasEffectivePermission($admin, 'purchase.cost.view'))->toBeTrue();
    });

    it('sensitive restrictions apply regardless of role combination', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        // Give user both salesperson and supervisor roles
        $user->assignRole(['salesperson', 'branch_supervisor']);

        $service = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        // Should NOT have admin-only permissions despite having supervisor role
        expect($service->hasEffectivePermission($user, 'purchase.cost.view'))->toBeFalse();
        expect($service->hasEffectivePermission($user, 'supplier.manage'))->toBeFalse();
        expect($service->hasEffectivePermission($user, 'report.view.historical'))->toBeFalse();
    });
});

describe('permission matrix — UI access', function () {
    it('allows admin to view permission matrix', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('roles.matrix'));

        $response->assertStatus(200);
        $response->assertSee('Permission Matrix');
    });

    it('denies supervisor access to permission matrix', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('roles.matrix'));

        $response->assertForbidden();
    });

    it('denies salesperson access to permission matrix', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('roles.matrix'));

        $response->assertForbidden();
    });
});

describe('non-admin cannot manage roles and permissions', function () {
    it('supervisor cannot access role assignment', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($supervisor)->get(route('users.edit', $target));

        // Supervisor cannot edit users (denied by UserPolicy::update)
        $response->assertForbidden();
    });

    it('salesperson cannot access role assignment', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($salesperson)->get(route('users.edit', $target));

        $response->assertForbidden();
    });
});
