<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('user list — viewAny', function () {
    it('allows admin to view user list', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee('User Management');
    });

    it('allows supervisor to view user list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('users.index'));

        $response->assertStatus(200);
    });

    it('allows online supervisor to view user list', function () {
        $onlineSupervisor = onlineSupervisor();

        $response = $this->actingAs($onlineSupervisor)->get(route('users.index'));

        $response->assertStatus(200);
    });

    it('denies salesperson access to user list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('users.index'));

        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('user create', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('users.create'));

        $response->assertStatus(200);
        $response->assertSee('Create User');
    });

    it('denies supervisor access to create form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('users.create'));

        $response->assertForbidden();
    });

    it('allows admin to create user with valid data', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();

        $response = $this->actingAs($admin)->post('/livewire/update', [
            // This is a Livewire component, so we test via the service instead
        ]);

        // Test via the service directly
        $userService = app(UserService::class);
        $user = $userService->create([
            'username' => 'newuser',
            'full_name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'assigned_branch_id' => $branch->id,
            'is_online_channel' => false,
            'roles' => ['salesperson'],
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->username)->toBe('newuser');
        expect($user->full_name)->toBe('New User');
        expect(Hash::check('password123', $user->password_hash))->toBeTrue();
        expect($user->assigned_branch_id)->toBe($branch->id);
        expect($user->hasRole('salesperson'))->toBeTrue();
    });

    it('rejects duplicate username', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();

        $userService = app(UserService::class);
        $userService->create([
            'username' => 'existinguser',
            'full_name' => 'Existing',
            'password' => 'password123',
            'assigned_branch_id' => $branch->id,
            'roles' => ['salesperson'],
        ]);

        expect(fn () => $userService->create([
            'username' => 'existinguser',
            'full_name' => 'Another',
            'password' => 'password123',
            'assigned_branch_id' => $branch->id,
            'roles' => ['salesperson'],
        ]))->toThrow(ValidationException::class);
    });

    it('requires branch for physical branch users', function () {
        $userService = app(UserService::class);

        expect(fn () => $userService->create([
            'username' => 'nobranch',
            'full_name' => 'No Branch',
            'password' => 'password123',
            'is_online_channel' => false,
            'assigned_branch_id' => null,
        ]))->toThrow(ValidationException::class);
    });

    it('allows online channel user without physical branch', function () {
        $onlineBranch = Branch::where('is_online_channel', true)->first();
        $userService = app(UserService::class);

        $user = $userService->create([
            'username' => 'onlineuser',
            'full_name' => 'Online User',
            'password' => 'password123',
            'is_online_channel' => true,
            'assigned_branch_id' => $onlineBranch->id,
            'roles' => ['online_team'],
        ]);

        expect($user->is_online_channel)->toBeTrue();
        expect($user->hasRole('online_team'))->toBeTrue();
    });

    it('hashes the password correctly', function () {
        $branch = Branch::where('code', 'B1')->first();
        $userService = app(UserService::class);

        $user = $userService->create([
            'username' => 'hashtest',
            'full_name' => 'Hash Test',
            'password' => 'mypassword',
            'assigned_branch_id' => $branch->id,
        ]);

        expect($user->password_hash)->not->toBe('mypassword');
        expect(Hash::check('mypassword', $user->password_hash))->toBeTrue();
    });
});

describe('user update', function () {
    it('allows admin to update user details', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();

        $userService = app(UserService::class);
        $updated = $userService->update($user, [
            'username' => $user->username,
            'full_name' => 'Updated Name',
            'email' => $user->email,
        ]);

        expect($updated->full_name)->toBe('Updated Name');
    });

    it('allows admin to change username', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create(['username' => 'oldname']);

        $userService = app(UserService::class);
        $updated = $userService->update($user, ['username' => 'newname']);

        expect($updated->username)->toBe('newname');
    });

    it('rejects duplicate username on update', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user1 = User::factory()->forBranch($branch)->create(['username' => 'user1']);
        $user2 = User::factory()->forBranch($branch)->create(['username' => 'user2']);

        $userService = app(UserService::class);

        expect(fn () => $userService->update($user2, ['username' => 'user1']))
            ->toThrow(ValidationException::class);
    });

    it('allows admin to sync roles', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $user->assignRole('salesperson');

        $userService = app(UserService::class);
        $updated = $userService->update($user, [
            'username' => $user->username,
            'roles' => ['salesperson', 'branch_supervisor'],
        ]);

        expect($updated->hasRole('salesperson'))->toBeTrue();
        expect($updated->hasRole('branch_supervisor'))->toBeTrue();
    });
});

describe('activate / deactivate', function () {
    it('allows admin to activate user', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create(['is_active' => false]);

        $userService = app(UserService::class);
        $activated = $userService->activate($user);

        expect($activated->is_active)->toBeTrue();
    });

    it('allows admin to deactivate user', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create(['is_active' => true]);

        $userService = app(UserService::class);
        $deactivated = $userService->deactivate($user);

        expect($deactivated->is_active)->toBeFalse();
    });

    it('prevents user from deactivating themselves', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $userService = app(UserService::class);

        expect(fn () => $userService->deactivate($admin))
            ->toThrow(ValidationException::class);
    });
});

describe('branch assignment', function () {
    it('allows admin to assign user to different branch', function () {
        $admin = adminUser();
        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();
        $user = User::factory()->forBranch($branchA)->create();

        $userService = app(UserService::class);
        $updated = $userService->assignToBranch($user, $branchB);

        expect($updated->assigned_branch_id)->toBe($branchB->id);
    });

    it('prevents assigning physical branch user to online channel', function () {
        $admin = adminUser();
        $branchB1 = Branch::where('code', 'B1')->first();
        $onlineBranch = Branch::where('is_online_channel', true)->first();
        $user = User::factory()->forBranch($branchB1)->create(['is_online_channel' => false]);

        $userService = app(UserService::class);

        expect(fn () => $userService->assignToBranch($user, $onlineBranch))
            ->toThrow(ValidationException::class);
    });

    it('prevents assigning online channel user to physical branch', function () {
        $onlineBranch = Branch::where('is_online_channel', true)->first();
        $branchB1 = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($onlineBranch)->create(['is_online_channel' => true]);

        $userService = app(UserService::class);

        expect(fn () => $userService->assignToBranch($user, $branchB1))
            ->toThrow(ValidationException::class);
    });
});

describe('user search and list', function () {
    it('returns paginated list of users', function () {
        User::factory()->count(30)->create();

        $userService = app(UserService::class);
        $result = $userService->getPaginatedList(['per_page' => 25]);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->count())->toBeLessThanOrEqual(25);
    });

    it('searches by username', function () {
        User::factory()->create(['username' => 'johnsmith']);
        User::factory()->create(['username' => 'janedoe']);

        $userService = app(UserService::class);
        $results = $userService->search('john');

        expect($results)->toHaveCount(1);
        expect($results->first()->username)->toBe('johnsmith');
    });

    it('searches by full name', function () {
        User::factory()->create(['full_name' => 'Alice Johnson']);
        User::factory()->create(['full_name' => 'Bob Smith']);

        $userService = app(UserService::class);
        $results = $userService->search('alice');

        expect($results)->toHaveCount(1);
        expect($results->first()->full_name)->toBe('Alice Johnson');
    });

    it('filters by branch', function () {
        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();
        User::factory()->count(3)->forBranch($branchA)->create();
        User::factory()->count(2)->forBranch($branchB)->create();

        $userService = app(UserService::class);
        $results = $userService->getPaginatedList(['branch_id' => $branchA->id, 'per_page' => 100]);

        expect($results->total())->toBe(3);
    });

    it('filters by status', function () {
        User::factory()->create(['is_active' => true, 'locked_at' => null]);
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['locked_at' => now()]);

        $userService = app(UserService::class);

        $active = $userService->getPaginatedList(['status' => 'active', 'per_page' => 100]);
        $inactive = $userService->getPaginatedList(['status' => 'inactive', 'per_page' => 100]);
        $locked = $userService->getPaginatedList(['status' => 'locked', 'per_page' => 100]);

        expect($active->total())->toBe(1);
        expect($inactive->total())->toBe(1);
        expect($locked->total())->toBe(1);
    });
});

describe('user view', function () {
    it('allows admin to view any user', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($admin)->get(route('users.show', $target));

        $response->assertStatus(200);
        $response->assertSee($target->full_name);
    });

    it('allows supervisor to view users in own branch', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($supervisor)->get(route('users.show', $target));

        $response->assertStatus(200);
    });

    it('denies supervisor viewing users in other branches', function () {
        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();
        $supervisor = supervisor($branchA);
        $target = User::factory()->forBranch($branchB)->create();

        $response = $this->actingAs($supervisor)->get(route('users.show', $target));

        $response->assertForbidden();
    });

    it('allows user to view own profile', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('users.show', $salesperson));

        $response->assertStatus(200);
    });
});

describe('user edit — authorization', function () {
    it('allows admin to view edit form', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($admin)->get(route('users.edit', $target));

        $response->assertStatus(200);
        $response->assertSee('Edit User');
    });

    it('denies supervisor access to edit form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($supervisor)->get(route('users.edit', $target));

        $response->assertForbidden();
    });
});

describe('user model scopes', function () {
    it('scopeActive returns only active non-locked users', function () {
        User::factory()->create(['is_active' => true, 'locked_at' => null]);
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['locked_at' => now()]);

        expect(User::active()->count())->toBe(1);
    });

    it('scopeForBranch returns only users in that branch', function () {
        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();
        User::factory()->count(2)->forBranch($branchA)->create();
        User::factory()->count(3)->forBranch($branchB)->create();

        expect(User::forBranch($branchA->id)->count())->toBe(2);
        expect(User::forBranch($branchB->id)->count())->toBe(3);
    });

    it('scopeOnlineChannel returns only online channel users', function () {
        $physicalBranch = Branch::where('code', 'B1')->first();
        $onlineBranch = Branch::where('is_online_channel', true)->first();

        User::factory()->forBranch($physicalBranch)->create(['is_online_channel' => false]);
        User::factory()->forBranch($onlineBranch)->create(['is_online_channel' => true]);

        expect(User::onlineChannel()->count())->toBe(1);
    });
});

describe('password security', function () {
    it('password_hash is hidden from serialization', function () {
        $user = User::factory()->create();

        $array = $user->toArray();

        expect($array)->not->toHaveKey('password_hash');
        expect($array)->not->toHaveKey('remember_token');
    });
});
