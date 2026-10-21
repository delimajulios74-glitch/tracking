
<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Laravel API  bords is delims working!',
    ]);
});
