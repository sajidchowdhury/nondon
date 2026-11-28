<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\FiscalYear;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('login page', function () {
    it('can render the login page for guests', function () {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Username');
        $response->assertSee('Password');
    });

    it('redirects authenticated users from login page', function () {
        $user = adminUser();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    });
});

describe('successful login', function () {
    it('can log in with valid credentials', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('salesperson');

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    });

    it('records successful login attempt', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
        ]);
        $user->assignRole('salesperson');

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'username' => 'testuser',
            'user_id' => $user->id,
            'success' => true,
        ]);
    });

    it('resets failed login attempts on successful login', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'failed_login_attempts' => 3,
        ]);
        $user->assignRole('salesperson');

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        expect($user->fresh()->failed_login_attempts)->toBe(0);
        expect($user->fresh()->last_login_at)->not->toBeNull();
    });
});

describe('failed login', function () {
    it('rejects invalid username', function () {
        $response = $this->post('/login', [
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });

    it('rejects invalid password', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });

    it('increments failed login attempts', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'failed_login_attempts' => 0,
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        expect($user->fresh()->failed_login_attempts)->toBe(1);
    });

    it('locks account after five failed attempts', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'failed_login_attempts' => 4,
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        expect($user->fresh()->isLocked())->toBeTrue();
        expect($user->fresh()->failed_login_attempts)->toBe(5);
    });

    it('records failed login attempt in log', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'username' => 'testuser',
            'user_id' => $user->id,
            'success' => false,
            'failure_reason' => 'invalid_password',
        ]);
    });
});

describe('locked account', function () {
    it('prevents login for locked accounts', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'locked_at' => now(),
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });
});

describe('inactive account', function () {
    it('prevents login for inactive accounts', function () {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password_hash' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });
});

describe('logout', function () {
    it('can log out authenticated user', function () {
        $user = adminUser();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    });
});

describe('dashboard', function () {
    it('shows dashboard for authenticated users', function () {
        $user = adminUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Welcome');
    });

    it('redirects guests from dashboard', function () {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    });
});

describe('role switching', function () {
    it('shows role selector for multi-role users', function () {
        $user = User::factory()->create([
            'username' => 'multirole',
            'password_hash' => Hash::make('password123'),
        ]);
        $user->assignRole(['salesperson', 'branch_supervisor']);
        $user->update(['active_role_id' => null]);

        $response = $this->actingAs($user)->get(route('role.select'));

        $response->assertStatus(200);
        $response->assertSee('Select Active Role');
    });
});

describe('fiscal year serial generation', function () {
    it('generates correct serial format', function () {
        $fiscalYear = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 1208,
        ]);

        $serial = $fiscalYear->generateNextSerial();

        expect($serial)->toBe('25-01209');
    });

    it('generates first serial of a fiscal year', function () {
        $fiscalYear = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 0,
        ]);

        $serial = $fiscalYear->generateNextSerial();

        expect($serial)->toBe('25-00001');
    });

    it('increments sequence counter', function () {
        $fiscalYear = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 5,
        ]);

        $fiscalYear->incrementSequence();

        expect($fiscalYear->fresh()->current_serial_sequence)->toBe(6);
    });
});
