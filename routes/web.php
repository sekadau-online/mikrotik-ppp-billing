<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerPaymentController;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/pelanggan/payment', [CustomerPaymentController::class, 'index']);
Route::post('/pelanggan/pay', [CustomerPaymentController::class, 'pay']);
