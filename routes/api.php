<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PppUserController;
use App\Http\Controllers\Api\PackageController;

Route::prefix('ppp-users')->group(function () {
    Route::get('/', [PppUserController::class, 'index']);
    Route::post('/', [PppUserController::class, 'store']);
    Route::get('/{pppUser}', [PppUserController::class, 'show']);
    Route::put('/{pppUser}', [PppUserController::class, 'update']);
    Route::delete('/{pppUser}', [PppUserController::class, 'destroy']);
    Route::post('/{pppUser}/payments', [PppUserController::class, 'processPayment']);
    Route::get('/overdue', [PppUserController::class, 'overdueUsers']);
});

Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::post('/', [PackageController::class, 'store']);
    Route::get('/{package}', [PackageController::class, 'show']);
    Route::put('/{package}', [PackageController::class, 'update']);
    Route::delete('/{package}', [PackageController::class, 'destroy']);
});

Route::post('/packages/sync-mikrotik', [PackageController::class, 'syncProfiles']);