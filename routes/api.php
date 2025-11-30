<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\PaymentConfigController;

/*
|--------------------------------------------------------------------------
| Protected API Routes (Require API Key)
|--------------------------------------------------------------------------
*/
Route::middleware(['api_key.auth'])->group(function () {

    // M-PESA Payment Operations
    Route::post('/stk-push', [MpesaController::class, 'stkPush']);
    Route::get('/stk-push/status/{checkoutRequestId}', [MpesaController::class, 'stkStatus']);
    Route::post('/b2c', [MpesaController::class, 'b2c']);
    Route::post('/b2b', [MpesaController::class, 'b2b']);
    Route::post('/register-c2b', [MpesaController::class, 'registerC2B']);
    Route::post('/transaction-status', [MpesaController::class, 'transactionStatus']);

    // Payment Configuration Management
    Route::prefix('payment-configs')->group(function () {
        Route::get('/', [PaymentConfigController::class, 'index']);
        Route::post('/', [PaymentConfigController::class, 'store']);
        Route::get('/{id}', [PaymentConfigController::class, 'show']);
        Route::put('/{id}', [PaymentConfigController::class, 'update']);
        Route::delete('/{id}', [PaymentConfigController::class, 'destroy']);
        Route::post('/{id}/set-default', [PaymentConfigController::class, 'setDefault']);
        Route::get('/default/{type}', [PaymentConfigController::class, 'getDefault']);
        Route::get('/type/tills', [PaymentConfigController::class, 'getTills']);
        Route::get('/type/paybills', [PaymentConfigController::class, 'getPaybills']);
    });

    // Transaction History & Reports
    Route::get('/transactions', [MpesaController::class, 'transactions']);
    Route::get('/transactions/{id}', [MpesaController::class, 'transactionDetail']);

});

/*
|--------------------------------------------------------------------------
| Public Callback Routes (Safaricom calls these)
|--------------------------------------------------------------------------
*/

// STK Push Callback
Route::post('/mpesa/stk/callback', [CallbackController::class, 'stkCallback']);

// C2B Validation & Confirmation (cannot contain word "mpesa" in path)
Route::post('/callback/c2b/validation', [CallbackController::class, 'c2bValidation']);
Route::post('/callback/c2b/confirmation', [CallbackController::class, 'c2bConfirmation']);

// B2C Callbacks
Route::post('/mpesa/b2c/result', [CallbackController::class, 'b2cResult']);
Route::post('/mpesa/b2c/timeout', [CallbackController::class, 'b2cTimeout']);

// B2B Callbacks
Route::post('/mpesa/b2b/result', [CallbackController::class, 'b2bResult']);
Route::post('/mpesa/b2b/timeout', [CallbackController::class, 'b2bTimeout']);

// Public health check (no API key required)
Route::get('/health', [MpesaController::class, 'health']);
