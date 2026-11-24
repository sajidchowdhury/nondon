<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Http\Livewire\Auth\Login;
use App\Modules\Identity\Http\Livewire\Auth\Dashboard;
use App\Modules\Identity\Http\Livewire\Auth\RoleSwitcher;
use App\Modules\Identity\Http\Livewire\Users\UserList;
use App\Modules\Identity\Http\Livewire\Users\UserCreate;
use App\Modules\Identity\Http\Livewire\Users\UserEdit;
use App\Modules\Identity\Http\Livewire\Users\UserView;
use App\Modules\Identity\Http\Livewire\Roles\PermissionMatrix;
use App\Modules\Identity\Http\Livewire\Password\PasswordChange;
use App\Modules\Identity\Http\Livewire\Password\ForcePasswordChange;
use App\Modules\Organization\Http\Livewire\Branches\BranchList;
use App\Modules\Organization\Http\Livewire\Branches\BranchCreate;
use App\Modules\Organization\Http\Livewire\Branches\BranchEdit;
use App\Modules\Organization\Http\Livewire\FiscalYears\FiscalYearList;
use App\Modules\Organization\Http\Livewire\FiscalYears\FiscalYearCreate;
use App\Modules\Organization\Http\Livewire\FiscalYears\FiscalYearView;
use App\Modules\Catalog\Http\Livewire\Products\ProductList;
use App\Modules\Catalog\Http\Livewire\Products\ProductCreate;
use App\Modules\Catalog\Http\Livewire\Products\ProductEdit;
use App\Modules\Catalog\Http\Livewire\Products\ProductView;

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

    // Role & Permission Management routes (Session 1.2)
    Route::get('/roles/matrix', PermissionMatrix::class)->name('roles.matrix');

    // Password Management routes (Session 1.3)
    Route::get('/password/change', PasswordChange::class)->name('password.change');
    Route::get('/password/force-change', ForcePasswordChange::class)->name('password.force-change');

    // Branch Management routes (Session 1.5)
    Route::prefix('branches')->name('branches.')->group(function () {
        Route::get('/', BranchList::class)->name('index');
        Route::get('/create', BranchCreate::class)->name('create');
        Route::get('/{branch}/edit', BranchEdit::class)->name('edit');
        Route::get('/{branch}', BranchEdit::class)->name('show');
    });

    // Fiscal Year Management routes (Session 1.6)
    Route::prefix('fiscal-years')->name('fiscal-years.')->group(function () {
        Route::get('/', FiscalYearList::class)->name('index');
        Route::get('/create', FiscalYearCreate::class)->name('create');
        Route::get('/{fiscalYear}', FiscalYearView::class)->name('show');
    });

    // Product Management routes (Session 1.7)
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', ProductList::class)->name('index');
        Route::get('/create', ProductCreate::class)->name('create');
        Route::get('/{product}/edit', ProductEdit::class)->name('edit');
        Route::get('/{product}', ProductView::class)->name('show');
    });
});

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
