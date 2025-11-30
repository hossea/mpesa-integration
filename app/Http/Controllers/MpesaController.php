<?php
// app/Http/Controllers/MpesaController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MpesaService;
use App\Models\MpesaTransaction;
use App\Models\Merchant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MpesaController extends Controller
{
    /**
     * Display the payment form
     */
    public function index()
    {
        return view('welcome');
    }

    /**
     * Process payment from web form
     */
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:send,till,paybill',
            'phone' => 'required|regex:/^[0-9]{9,12}$/',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $phone = $this->formatPhone($request->phone);
        $amount = $request->amount;

        $merchant = Merchant::first();
        if (!$merchant) {
            return back()->with('error', 'Merchant configuration not found');
        }

        try {
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->stkPush(
                $phone,
                $amount,
                "Payment-" . time(),
                ucfirst($request->type) . " Payment"
            );

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                return back()->with('success', 'Payment initiated! Please check your phone.');
            }

            return back()->with('error', $response['errorMessage'] ?? 'Payment failed');
        } catch (\Exception $e) {
            Log::error('payment_process_error', ['error' => $e->getMessage()]);
            return back()->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * API: Initiate STK Push (for external systems)
     */
    public function stkPush(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required',
                'amount' => 'required|numeric|min:1',
                'account_ref' => 'nullable|string',
                'description' => 'nullable|string',
                'merchant_id' => 'nullable|exists:merchants,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $phone = $this->formatPhone($request->phone);
            $merchantId = $request->merchant_id ?? Merchant::first()?->id;

            if (!$merchantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No merchant found. Please run: php artisan db:seed'
                ], 404);
            }

            $merchant = Merchant::find($merchantId);

            // Create transaction record
            $txn = MpesaTransaction::create([
                'merchant_id' => $merchant->id,
                'type' => 'stk',
                'phone' => $phone,
                'amount' => $request->amount,
                'request_payload' => $request->all(),
                'status' => 'pending',
            ]);

            // Initiate STK push
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->stkPush(
                $phone,
                $request->amount,
                $request->account_ref ?? "TXN-{$txn->id}",
                $request->description ?? "Payment"
            );

            // Update transaction with response
            $txn->update([
                'response_payload' => $response,
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                'status' => isset($response['ResponseCode']) && $response['ResponseCode'] == '0' ? 'processing' : 'failed'
            ]);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                return response()->json([
                    'success' => true,
                    'message' => 'STK Push initiated successfully',
                    'data' => [
                        'transaction_id' => $txn->id,
                        'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                        'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['errorMessage'] ?? 'STK Push failed',
                'data' => $response
            ], 400);

        } catch (\Exception $e) {
            Log::error('stk_push_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check STK Push Status
     */
    public function stkStatus(Request $request, $checkoutRequestId)
    {
        try {
            $merchantId = $request->merchant_id ?? Merchant::first()?->id;
            $merchant = Merchant::find($merchantId);

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }

            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->stkQuery($checkoutRequestId);

            // Also check database
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'api_response' => $response,
                    'database_record' => $transaction
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('stk_status_error', [
                'error' => $e->getMessage(),
                'checkout_request_id' => $checkoutRequestId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking status'
            ], 500);
        }
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request)
    {
        try {
            $query = MpesaTransaction::query();

            // Filter by merchant
            if ($request->merchant_id) {
                $query->where('merchant_id', $request->merchant_id);
            }

            // Filter by type
            if ($request->type) {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->status) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->from_date) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $perPage = $request->per_page ?? 20;
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('transactions_error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single transaction details
     */
    public function transactionDetail($id)
    {
        $transaction = MpesaTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Format phone number to 254XXXXXXXXX
     */
    private function formatPhone($phone)
    {
        $p = preg_replace('/\D/', '', $phone);

        if (strlen($p) == 9) {
            return '254' . $p;
        }

        if (str_starts_with($p, '0')) {
            return '254' . substr($p, 1);
        }

        if (str_starts_with($p, '+254')) {
            return substr($p, 1);
        }

        if (str_starts_with($p, '254')) {
            return $p;
        }

        return '254' . $p;
    }

    /**
     * Health check endpoint
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'M-Pesa API is running',
            'timestamp' => now()->toDateTimeString(),
            'merchants_count' => Merchant::count(),
            'api_clients_count' => \App\Models\ApiClient::count()
        ]);
    }
}
