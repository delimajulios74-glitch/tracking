
<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetLookupController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\MaintenanceUserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Laravel API  bords is delims working!',
    ]);
});

Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
    Route::get('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'show']);
    Route::get('/maintenance-types', [MaintenanceLogController::class, 'types']);
    Route::get('/assets/{asset}/maintenance-logs', [MaintenanceLogController::class, 'assetIndex']);
    Route::get('/asset-options', [AssetLookupController::class, 'options']);
    Route::get('/categories', [AssetLookupController::class, 'categories']);
    Route::get('/locations', [AssetLookupController::class, 'locations']);

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/users', [MaintenanceUserController::class, 'index']);
        Route::patch('/users/{user}', [MaintenanceUserController::class, 'update']);
        Route::post('/assets/{asset}/maintenance-logs', [MaintenanceLogController::class, 'store']);
        Route::put('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
        Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
        Route::delete('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'destroy']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::patch('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('/users', [MaintenanceUserController::class, 'store']);
    });

    Route::middleware('role:maintenance')->patch(
        '/assets/{asset}/condition',
        [AssetController::class, 'updateCondition'],
    );
});
