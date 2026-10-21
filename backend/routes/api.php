
<?php

use App\Http\Controllers\AuthController;
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

    Route::middleware('role:admin')->post('/users', [MaintenanceUserController::class, 'store']);
});
