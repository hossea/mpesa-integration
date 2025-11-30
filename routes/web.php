<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mpesa', [MpesaController::class, 'index'])->name('mpesa.home');

Route::post('/mpesa/send-money', [MpesaController::class, 'sendMoney'])->name('mpesa.send');
Route::post('/mpesa/paybill', [MpesaController::class, 'payBill'])->name('mpesa.paybill');
Route::post('/mpesa/till', [MpesaController::class, 'tillPayment'])->name('mpesa.till');
Route::post('/mpesa/process', [MpesaController::class, 'process'])->name('mpesa.process');

Route::get('/mpesa/status/{checkoutRequestId}', [MpesaController::class, 'stkStatus'])->name('mpesa.status');
Route::get('/mpesa/transactions', [MpesaController::class, 'transactions'])->name('mpesa.transactions');
