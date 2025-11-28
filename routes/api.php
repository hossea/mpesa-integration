<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\CallbackController;


/*
|--------------------------------------------------------------------------
| Protected API Routes (Require API Key)
|--------------------------------------------------------------------------
*/
Route::middleware(['api_key.auth'])->group(function () {
    Route::post('/stk-push', [MpesaController::class, 'stkPush']);
    Route::post('/b2c', [MpesaController::class, 'b2c']);
    Route::post('/b2b', [MpesaController::class, 'b2b']);
    Route::post('/register-c2b', [MpesaController::class, 'registerC2B']);
    Route::post('/transaction-status', [MpesaController::class, 'transactionStatus']);
});


/*
|--------------------------------------------------------------------------
| Public Callback Routes (Safaricom calls these)
|--------------------------------------------------------------------------
| NOTE:
| STK, B2C, and B2B CAN contain "mpesa" in the URL. That is allowed.
|
| ONLY C2B Register URLs MUST NOT contain "mpesa" in the path.
|--------------------------------------------------------------------------
*/

/* ---- STK Push Callback ---- */
Route::post('/mpesa/stk/callback', [CallbackController::class, 'stkCallback']);


/* ---- C2B Validation & Confirmation (cannot contain word mpesa) ---- */
Route::post('/callback/c2b/validation', [CallbackController::class, 'c2bValidation']);
Route::post('/callback/c2b/confirmation', [CallbackController::class, 'c2bConfirmation']);


/* ---- B2C Callbacks ---- */
Route::post('/b2c/result', [CallbackController::class, 'b2cResult']);
Route::post('/b2c/timeout', [CallbackController::class, 'b2cTimeout']);


/* ---- B2B Callbacks ---- */
Route::post('/b2b/result', [CallbackController::class, 'b2bResult']);
Route::post('/b2b/timeout', [CallbackController::class, 'b2bTimeout']);


/* ---- Legacy testing endpoints (optional) ---- */
Route::post('/validate', [CallbackController::class, 'validation']);
Route::post('/confirm', [CallbackController::class, 'confirmation']);
Route::post('/stk-callback', [CallbackController::class, 'stkCallback']);
Route::post('/b2c-callback', [CallbackController::class, 'b2cResult']);
Route::post('/b2b-callback', [CallbackController::class, 'b2bResult']);

Route::middleware('api_key.auth')->get('/payments', [MpesaController::class, 'index']);

