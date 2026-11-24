<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes for the Nondon ERP. Used for:
| - Offline POS sync (/api/sync/*)
| - Future mobile app or third-party integration
| - Courier/payment webhooks (future)
|
| Authentication: Sanctum token-based
| All endpoints enforce the same authorization as the Livewire interface.
|
*/

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0',
        ]);
    });

    // Authentication
    Route::post('/auth/login', [\App\Modules\Identity\Http\Controllers\Api\AuthController::class, 'login']);

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [\App\Modules\Identity\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('/auth/me', [\App\Modules\Identity\Http\Controllers\Api\AuthController::class, 'me']);
        Route::post('/auth/role-switch', [\App\Modules\Identity\Http\Controllers\Api\AuthController::class, 'switchRole']);

        // Offline POS sync (Phase 8)
        // Route::prefix('sync')->group(function () {
        //     Route::post('/push', [\App\Modules\Sales\Http\Controllers\Api\SyncController::class, 'push']);
        //     Route::get('/pull', [\App\Modules\Sales\Http\Controllers\Api\SyncController::class, 'pull']);
        //     Route::get('/status', [\App\Modules\Sales\Http\Controllers\Api\SyncController::class, 'status']);
        // });

        // Module API routes will be added as modules are implemented
        // Catalog, Inventory, POS, Transfers, OnlineOrders, Returns, Cash, etc.
    });
});
