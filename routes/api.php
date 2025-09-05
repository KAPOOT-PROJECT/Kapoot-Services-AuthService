<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
    Route::post('verifyCode', [\App\Http\Controllers\AuthController::class, 'verifyCode']);
    Route::post('verifyTwoAuthCode', [\App\Http\Controllers\AuthController::class, 'verifyTwoAuthCode']);
    Route::post('register', [\App\Http\Controllers\AuthController::class, 'register']);
    Route::post('refresh', [\App\Http\Controllers\AuthController::class, 'refresh']);
    Route::post('validate-token', [\App\Http\Controllers\AuthController::class, 'validateToken']);
});

Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::put('update', [\App\Http\Controllers\AuthController::class, 'updateUser']);
    Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('me', [\App\Http\Controllers\AuthController::class, 'me']);
    Route::get('userPermissions/{user}', [\App\Http\Controllers\AuthController::class, 'getUserPermissions']);
    Route::post('twoFactor', [\App\Http\Controllers\AuthController::class, 'setTwoFactor']);
});
