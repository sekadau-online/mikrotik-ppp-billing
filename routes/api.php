<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PppUserController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ppp-users')->group(function () {
    Route::get('/', [PppUserController::class, 'index']);
    Route::post('/', [PppUserController::class, 'store']);
    Route::get('by-username/{username}', [PppUserController::class, 'showByUsername']);
    Route::get('by-id/{id}', [PppUserController::class, 'showById']);
    Route::put('/{pppUser}', [PppUserController::class, 'update']);
    Route::delete('/{pppUser}', [PppUserController::class, 'destroy']);
    Route::get('overdue', [PppUserController::class, 'overdueUsers']);
});

Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::get('active', [PackageController::class, 'activePackages']);
    Route::post('/', [PackageController::class, 'store']);
    Route::get('/{package}', [PackageController::class, 'show']);
    Route::put('/{package}', [PackageController::class, 'update']);
    Route::delete('/{package}', [PackageController::class, 'destroy']);
});

Route::prefix('payments')->group(function () {
    Route::post('process', [PaymentController::class, 'processPayment']);
    Route::post('midtrans-callback', [PaymentController::class, 'handleMidtransCallback']);
    Route::get('history/{userId}', [PaymentController::class, 'paymentHistory']);
});

Route::post('mikrotik/sync-profiles', [PackageController::class, 'syncProfiles']);