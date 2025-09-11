<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verifyCode', [AuthController::class, 'verifyCode']);
    Route::post('verifyTwoAuthCode', [AuthController::class, 'verifyTwoAuthCode']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('validate-token', [AuthController::class, 'validateToken']);
    Route::post('test-token', [AuthController::class, 'test']);
});

Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::put('update', [AuthController::class, 'updateUser']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('userPermissions/{user}', [AuthController::class, 'getUserPermissions']);
    Route::post('twoFactor', [AuthController::class, 'setTwoFactor']);
});
