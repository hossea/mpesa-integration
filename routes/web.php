<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\MpesaController;

Route::get('/mpesa', [MpesaController::class, 'index'])->name('mpesa.home');

Route::post('/mpesa/send-money', [MpesaController::class, 'sendMoney'])->name('mpesa.send');
Route::post('/mpesa/paybill', [MpesaController::class, 'payBill'])->name('mpesa.paybill');
Route::post('/mpesa/till', [MpesaController::class, 'tillPayment'])->name('mpesa.till');
Route::post('/mpesa/process', [MpesaController::class, 'process'])->name('mpesa.process');
