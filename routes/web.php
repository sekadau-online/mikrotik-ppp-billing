<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerPaymentController;

Route::get('/', function () {
    return view('welcome');
});

