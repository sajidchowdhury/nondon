<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Http\Livewire\Auth\Login;
use App\Modules\Identity\Http\Livewire\Auth\Dashboard;
use App\Modules\Identity\Http\Livewire\Auth\RoleSwitcher;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| All web routes for the Nondon ERP application.
| The primary interface is Livewire + Blade (server-rendered).
|
*/

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function () {
        app(\App\Modules\Identity\Services\AuthService::class)->logout();
        return redirect()->route('login');
    })->name('logout');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Role switching (for multi-role users)
    Route::get('/role/select', RoleSwitcher::class)->name('role.select');
});

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Module Routes (loaded by module service providers)
|--------------------------------------------------------------------------
|
| Business module routes (POS, transfers, inventory, etc.) will be loaded
| by their respective service providers as modules are implemented
| in development phases 1-10.
|
*/
