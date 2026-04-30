<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ── Públicas — sin token ───────────────────────────────────────────────────
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code',     [AuthController::class, 'verifyCode']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// ── Protegidas — requieren token Sanctum ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Solo admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/roles',                 [UserController::class, 'roles']);
        Route::get('/usuarios',              [UserController::class, 'index']);
        Route::get('/usuarios/{usuario}',    [UserController::class, 'show']);
        Route::put('/usuarios/{usuario}',    [UserController::class, 'update']);
        Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy']);
    });

});