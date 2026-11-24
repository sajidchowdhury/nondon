<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Http\Livewire\Auth\Login;
use App\Modules\Identity\Http\Livewire\Auth\Dashboard;
use App\Modules\Identity\Http\Livewire\Auth\RoleSwitcher;
use App\Modules\Identity\Http\Livewire\Users\UserList;
use App\Modules\Identity\Http\Livewire\Users\UserCreate;
use App\Modules\Identity\Http\Livewire\Users\UserEdit;
use App\Modules\Identity\Http\Livewire\Users\UserView;

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

    // User Management routes (Session 1.1)
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', UserList::class)->name('index');
        Route::get('/create', UserCreate::class)->name('create');
        Route::get('/{user}/edit', UserEdit::class)->name('edit');
        Route::get('/{user}', UserView::class)->name('show');
    });
});

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
