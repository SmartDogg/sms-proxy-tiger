<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SmsProxyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// SMS Proxy API endpoints
Route::middleware(['throttle:60,1', 'api.performance'])->group(function () {
    Route::get('/', [SmsProxyController::class, 'handle']);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String()
    ]);
});