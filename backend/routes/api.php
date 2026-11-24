<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetLookupController;
use App\Http\Controllers\AssetRequestController;
use App\Http\Controllers\AssetSummaryReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\MaintenanceTaskController;
use App\Http\Controllers\MaintenanceUserController;
use App\Http\Controllers\NotificationController;
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
    Route::get('/account', [AccountController::class, 'show']);
    Route::put('/account', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'changePassword']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::middleware('role:admin,maintenance')->group(function (): void {
        Route::get('/maintenance-tasks', [MaintenanceTaskController::class, 'index']);
        Route::get('/maintenance-tasks/{maintenanceTask}', [MaintenanceTaskController::class, 'show']);
    });

    Route::prefix('end-user')->name('end-user.')->middleware('role:end_user')->group(function (): void {
        Route::get('/dashboard', [EndUserController::class, 'dashboard']);
        Route::get('/equipment', [EndUserController::class, 'equipment']);
        Route::get('/equipment/{asset}', [EndUserController::class, 'showEquipment']);
        Route::get('/categories', [EndUserController::class, 'categories']);
        Route::get('/locations', [EndUserController::class, 'locations']);
        Route::post('/reports', [EndUserController::class, 'storeReport']);
        Route::get('/reports', [EndUserController::class, 'reports']);
        Route::delete('/reports/{assetRequest}', [EndUserController::class, 'destroyReport']);
        Route::get('/reports/{assetRequest}/photo', [EndUserController::class, 'reportPhoto'])
            ->name('reports.photo');
        Route::get('/reports/{assetRequest}', [EndUserController::class, 'showReport']);
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/admin/dashboard', AdminDashboardController::class);
        Route::get('/asset-requests', [AssetRequestController::class, 'index']);
        Route::patch('/asset-requests/{assetRequest}', [AssetRequestController::class, 'update']);
        Route::delete('/asset-requests/{assetRequest}', [AssetRequestController::class, 'destroy']);
        Route::get('/asset-requests/{assetRequest}/photo', [AssetRequestController::class, 'photo'])
            ->name('admin.asset-requests.photo');
        Route::get('/reports/asset-summary', AssetSummaryReportController::class);
        Route::get('/assets', [AssetController::class, 'index']);
        Route::get('/assets/{asset}', [AssetController::class, 'show']);
        Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
        Route::get('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'show']);
        Route::get('/maintenance-types', [MaintenanceLogController::class, 'types']);
        Route::get('/assets/{asset}/maintenance-logs', [MaintenanceLogController::class, 'assetIndex']);
        Route::get('/asset-options', [AssetLookupController::class, 'options']);
        Route::get('/categories', [AssetLookupController::class, 'categories']);
        Route::get('/locations', [AssetLookupController::class, 'locations']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::patch('/locations/{location}', [LocationController::class, 'update']);
        Route::get('/users', [MaintenanceUserController::class, 'index']);
        Route::patch('/users/{user}', [MaintenanceUserController::class, 'update']);
        Route::delete('/users/{user}', [MaintenanceUserController::class, 'destroy']);
        Route::post('/assets/{asset}/maintenance-logs', [MaintenanceLogController::class, 'store']);
        Route::put('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
        Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
        Route::delete('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'destroy']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::patch('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('/users', [MaintenanceUserController::class, 'store']);
        Route::post('/maintenance-tasks', [MaintenanceTaskController::class, 'store']);
        Route::delete('/maintenance-tasks/{maintenanceTask}', [MaintenanceTaskController::class, 'destroy']);
    });

    Route::middleware('role:admin,maintenance')->put(
        '/maintenance-tasks/{maintenanceTask}',
        [MaintenanceTaskController::class, 'update'],
    );
    Route::middleware('role:admin,maintenance')->patch(
        '/maintenance-tasks/{maintenanceTask}',
        [MaintenanceTaskController::class, 'update'],
    );
});
