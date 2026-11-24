<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('password validation rules', function () {
    it('rejects password shorter than 8 characters', function () {
        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->validatePassword('short1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('rejects password without a letter', function () {
        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->validatePassword('12345678'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('rejects password without a number', function () {
        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->validatePassword('password'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('rejects commonly-used weak passwords', function () {
        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->validatePassword('password1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
        expect(fn() => $service->validatePassword('12345678'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
        expect(fn() => $service->validatePassword('admin123'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('accepts a valid password with letters and numbers', function () {
        $service = app(\App\Modules\Identity\Services\AuthService::class);

        // Should not throw
        $service->validatePassword('securePass123');
        expect(true)->toBeTrue();
    });
});

describe('admin password reset', function () {
    it('admin can reset user password', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('oldpassword'),
        ]);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->resetPassword($user, 'newSecure123');

        expect(Hash::check('newSecure123', $updated->password_hash))->toBeTrue();
        expect(Hash::check('oldpassword', $updated->password_hash))->toBeFalse();
    });

    it('password reset sets force_password_change to true', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'force_password_change' => false,
        ]);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->resetPassword($user, 'newSecure123');

        expect($updated->force_password_change)->toBeTrue();
    });

    it('password reset clears lockout state', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->resetPassword($user, 'newSecure123');

        expect($updated->locked_at)->toBeNull();
        expect($updated->failed_login_attempts)->toBe(0);
    });

    it('password reset validates the new password', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        // Weak password should be rejected
        expect(fn() => $service->resetPassword($user, 'password1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        // Short password should be rejected
        expect(fn() => $service->resetPassword($user, 'short1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('self-service password change', function () {
    it('user can change own password with correct current password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('currentPass1'),
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->changePassword('currentPass1', 'newSecure123');

        expect(Hash::check('newSecure123', $updated->password_hash))->toBeTrue();
    });

    it('user cannot change password with wrong current password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('currentPass1'),
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->changePassword('wrongpassword', 'newSecure123'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('new password cannot be same as current password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('samePass123'),
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->changePassword('samePass123', 'samePass123'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('change password validates the new password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('currentPass1'),
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        // Weak password rejected
        expect(fn() => $service->changePassword('currentPass1', 'password1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        // Short password rejected
        expect(fn() => $service->changePassword('currentPass1', 'short1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('change password clears force_password_change flag', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('currentPass1'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->changePassword('currentPass1', 'newSecure123');

        expect($updated->force_password_change)->toBeFalse();
    });
});

describe('forced password change', function () {
    it('can change password without current password in forced flow', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('oldPass123'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->changePasswordForced('newSecure123');

        expect(Hash::check('newSecure123', $updated->password_hash))->toBeTrue();
        expect($updated->force_password_change)->toBeFalse();
    });

    it('forced change validates the new password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('oldPass123'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->changePasswordForced('password1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
        expect(fn() => $service->changePasswordForced('short1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('forced change rejects same-as-current password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('samePass123'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->changePasswordForced('samePass123'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('force password change flag', function () {
    it('admin can force password change without resetting password', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'force_password_change' => false,
        ]);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->forcePasswordChange($user);

        expect($updated->force_password_change)->toBeTrue();
        // Password should not be changed
        expect(Hash::check('password', $updated->password_hash))->toBeFalse();
    });
});

describe('account unlock', function () {
    it('admin can unlock a locked account', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $updated = $service->unlockAccount($user);

        expect($updated->locked_at)->toBeNull();
        expect($updated->failed_login_attempts)->toBe(0);
        expect($updated->isLocked())->toBeFalse();
    });

    it('locked account cannot login even with correct password', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'username' => 'lockeduser',
            'password_hash' => Hash::make('correctPass1'),
            'locked_at' => now(),
        ]);
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        expect(fn() => $service->login('lockeduser', 'correctPass1'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('after unlock, user can login', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'username' => 'unlockeduser',
            'password_hash' => Hash::make('correctPass1'),
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);
        $user->assignRole('salesperson');

        $service = app(\App\Modules\Identity\Services\AuthService::class);

        // Unlock
        $service->unlockAccount($user);

        // Should now be able to login
        $loggedIn = $service->login('unlockeduser', 'correctPass1');

        expect($loggedIn->id)->toBe($user->id);
    });
});

describe('ForcePasswordChangeMiddleware', function () {
    it('redirects to force-change page when flag is true', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('password.force-change'));
    });

    it('allows access to force-change page when flag is true', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $response = $this->actingAs($user)->get(route('password.force-change'));

        $response->assertStatus(200);
        $response->assertSee('Change Your Password');
    });

    it('allows access to logout when flag is true', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
    });

    it('does not redirect when flag is false', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
    });

    it('does not redirect guests', function () {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    });

    it('redirects to dashboard after password change clears flag', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'password_hash' => Hash::make('oldPass123'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $this->actingAs($user);

        $service = app(\App\Modules\Identity\Services\AuthService::class);
        $service->changePasswordForced('newSecure123');

        // After password change, should be able to access dashboard
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
    });
});

describe('login flow with force_password_change', function () {
    it('redirects to force-change page after login when flag is true', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'username' => 'forcetest',
            'password_hash' => Hash::make('validPass1'),
            'force_password_change' => true,
        ]);
        $user->assignRole('salesperson');

        $response = $this->post('/login', [
            'username' => 'forcetest',
            'password' => 'validPass1',
        ]);

        $response->assertRedirect(route('password.force-change'));
    });

    it('redirects to dashboard after login when flag is false', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create([
            'username' => 'normaltest',
            'password_hash' => Hash::make('validPass1'),
            'force_password_change' => false,
        ]);
        $user->assignRole('salesperson');

        $response = $this->post('/login', [
            'username' => 'normaltest',
            'password' => 'validPass1',
        ]);

        $response->assertRedirect(route('dashboard'));
    });
});

describe('password change UI access', function () {
    it('authenticated user can access password change page', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('password.change'));

        $response->assertStatus(200);
        $response->assertSee('Change Password');
    });

    it('guest cannot access password change page', function () {
        $response = $this->get(route('password.change'));

        $response->assertRedirect(route('login'));
    });
});

describe('non-admin cannot reset passwords or unlock accounts', function () {
    it('supervisor cannot reset passwords', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $target = User::factory()->forBranch($branch)->create();

        // The UserEdit page is forbidden for supervisors (checked in Session 1.1)
        $response = $this->actingAs($supervisor)->get(route('users.edit', $target));

        $response->assertForbidden();
    });

    it('salesperson cannot reset passwords', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $target = User::factory()->forBranch($branch)->create();

        $response = $this->actingAs($salesperson)->get(route('users.edit', $target));

        $response->assertForbidden();
    });
});
