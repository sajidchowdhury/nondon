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
use App\Modules\Inventory\Http\Livewire\Garments\GarmentList;
use App\Modules\Inventory\Http\Livewire\Garments\GarmentView;
use App\Modules\Inventory\Http\Livewire\Garments\GarmentLookup;
use App\Modules\Inventory\Http\Livewire\Stock\StockOverview;
use App\Modules\Inventory\Http\Livewire\Stock\StockAvailability;
use App\Modules\Inventory\Http\Livewire\Stock\GarmentHistory;
use App\Modules\Inventory\Http\Livewire\Stock\ReservationBoard;
use App\Modules\Audit\Http\Livewire\AuditLogList;
use App\Modules\Audit\Http\Livewire\AuditLogView;
use App\Modules\Purchasing\Http\Livewire\Suppliers\SupplierList;
use App\Modules\Purchasing\Http\Livewire\Suppliers\SupplierCreate;
use App\Modules\Purchasing\Http\Livewire\Suppliers\SupplierEdit;
use App\Modules\Purchasing\Http\Livewire\Suppliers\SupplierView;

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

    // Traditional POST login endpoint (mirrors the Livewire Login component logic).
    // Used by HTTP-level feature tests and any non-Livewire clients. The actual
    // business logic lives in AuthService::login().
    Route::post('/login', function (\Illuminate\Http\Request $request, \App\Modules\Identity\Services\AuthService $authService) {
        $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $user = $authService->login(
                username: $request->input('username'),
                password: $request->input('password'),
                remember: $request->boolean('remember'),
            );

            if ($user->force_password_change) {
                return redirect()->route('password.force-change');
            }

            if ($user->roles->count() > 1 && !$user->active_role_id) {
                return redirect()->route('role.select');
            }

            return redirect()->route('dashboard');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => __('An error occurred during login. Please try again.'),
            ]);
        }
    });
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

    // Garment routes (Session 1.9)
    Route::prefix('garments')->name('garments.')->group(function () {
        Route::get('/', GarmentList::class)->name('index');
        Route::get('/lookup', GarmentLookup::class)->name('lookup');
        Route::get('/{garment}/history', GarmentHistory::class)->name('history');
        Route::get('/{garment}', GarmentView::class)->name('show');
    });

    // Stock visibility routes (Session 1.11)
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', StockAvailability::class)->name('availability');
        Route::get('/overview', StockOverview::class)->name('overview');
        Route::get('/reservations', ReservationBoard::class)->name('reservations');
    });

    // Audit Log routes (Session 1.12)
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', AuditLogList::class)->name('index');
        Route::get('/{log}', AuditLogView::class)->name('show');
    });

    // Supplier Management routes (Session 2.1)
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', SupplierList::class)->name('index');
        Route::get('/create', SupplierCreate::class)->name('create');
        Route::get('/{supplier}/edit', SupplierEdit::class)->name('edit');
        Route::get('/{supplier}', SupplierView::class)->name('show');
    });
});

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
