<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MpesaService;
use App\Models\MpesaTransaction;
use App\Models\UserPaymentConfig;
use App\Models\Merchant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\ApiClient;

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
            'phone' => 'required|regex:/^254[0-9]{9}$/',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $phone = $this->formatPhone($request->phone);
        $amount = $request->amount;
        $type = $request->type;

        $merchant = Merchant::first();
        if (!$merchant) {
            return back()->with('error', 'Merchant configuration not found');
        }

        $mpesa = MpesaService::forMerchant($merchant);

        try {
            $response = $mpesa->stkPush(
                $phone,
                $amount,
                "Payment-" . time(),
                ucfirst($type) . " Payment"
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

        try {
            $phone = $this->formatPhone($request->phone);
            $merchantId = $request->merchant_id ?? Merchant::first()?->id;
            $merchant = Merchant::find($merchantId);

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }

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
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

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
     * API: B2C Payment (Business to Customer)
     */
    public function b2c(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'amount' => 'required|numeric|min:1',
            'command_id' => 'nullable|in:BusinessPayment,SalaryPayment,PromotionPayment',
            'remarks' => 'nullable|string',
            'merchant_id' => 'nullable|exists:merchants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $phone = $this->formatPhone($request->phone);
            $merchantId = $request->merchant_id ?? Merchant::first()?->id;
            $merchant = Merchant::find($merchantId);

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }

            // Create transaction record
            $txn = MpesaTransaction::create([
                'merchant_id' => $merchant->id,
                'type' => 'b2c',
                'phone' => $phone,
                'amount' => $request->amount,
                'request_payload' => $request->all(),
                'status' => 'pending',
            ]);

            // Initiate B2C
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->b2c(
                $phone,
                $request->amount,
                $request->command_id ?? 'BusinessPayment',
                $request->remarks ?? "B2C Payment"
            );

            // Update transaction
            $txn->update([
                'response_payload' => $response,
                'transaction_id' => $response['ConversationID'] ?? null,
                'merchant_request_id' => $response['OriginatorConversationID'] ?? null,
            ]);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                return response()->json([
                    'success' => true,
                    'message' => 'B2C payment initiated successfully',
                    'data' => [
                        'transaction_id' => $txn->id,
                        'conversation_id' => $response['ConversationID'] ?? null,
                        'originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['errorMessage'] ?? 'B2C payment failed',
                'data' => $response
            ], 400);

        } catch (\Exception $e) {
            Log::error('b2c_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * API: B2B Payment (Business to Business)
     */
    public function b2b(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_shortcode' => 'required',
            'amount' => 'required|numeric|min:1',
            'command_id' => 'nullable|in:BusinessPayBill,BusinessBuyGoods,DisburseFundsToBusiness,BusinessToBusinessTransfer,MerchantToMerchantTransfer',
            'remarks' => 'nullable|string',
            'merchant_id' => 'nullable|exists:merchants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $merchantId = $request->merchant_id ?? Merchant::first()?->id;
            $merchant = Merchant::find($merchantId);

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }

            // Create transaction record
            $txn = MpesaTransaction::create([
                'merchant_id' => $merchant->id,
                'type' => 'b2b',
                'amount' => $request->amount,
                'request_payload' => $request->all(),
                'status' => 'pending',
            ]);

            // Initiate B2B
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->b2b(
                $request->receiver_shortcode,
                $request->amount,
                $request->command_id ?? 'BusinessPayBill',
                $request->remarks ?? "B2B Payment"
            );

            // Update transaction
            $txn->update([
                'response_payload' => $response,
                'transaction_id' => $response['ConversationID'] ?? null,
                'merchant_request_id' => $response['OriginatorConversationID'] ?? null,
            ]);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                return response()->json([
                    'success' => true,
                    'message' => 'B2B payment initiated successfully',
                    'data' => [
                        'transaction_id' => $txn->id,
                        'conversation_id' => $response['ConversationID'] ?? null,
                        'originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['errorMessage'] ?? 'B2B payment failed',
                'data' => $response
            ], 400);

        } catch (\Exception $e) {
            Log::error('b2b_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * API: Register C2B URLs
     */
    public function registerC2B(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shortcode' => 'required',
            'merchant_id' => 'nullable|exists:merchants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

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
            $response = $mpesa->registerC2BUrls($request->shortcode);

            return response()->json([
                'success' => isset($response['ResponseCode']) && $response['ResponseCode'] == '0',
                'message' => $response['ResponseDescription'] ?? 'C2B registration failed',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('c2b_register_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * API: Query Transaction Status
     */
    public function transactionStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required',
            'merchant_id' => 'nullable|exists:merchants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

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
            $response = $mpesa->transactionStatus($request->transaction_id);

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('transaction_status_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'nullable|exists:merchants,id',
            'type' => 'nullable|in:stk,c2b,b2c,b2b',
            'status' => 'nullable|in:pending,processing,success,failed,timeout',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = MpesaTransaction::query();

        // Filter by merchant
        if ($request->merchant_id) {
            $query->where('merchant_id', $request->merchant_id);
        } else {
            $merchant = Merchant::first();
            if ($merchant) {
                $query->where('merchant_id', $merchant->id);
            }
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
     * Process payment with user's saved configuration
     */
    public function processWithConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'config_id' => 'required|exists:user_payment_configs,id',
            'phone' => 'required',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $config = UserPaymentConfig::find($request->config_id);
            $phone = $this->formatPhone($request->phone);

            $merchant = Merchant::first();
            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant configuration not found'
                ], 404);
            }

            // Create transaction record
            $txn = MpesaTransaction::create([
                'merchant_id' => $merchant->id,
                'type' => 'stk',
                'phone' => $phone,
                'amount' => $request->amount,
                'request_payload' => [
                    'config_id' => $config->id,
                    'config_name' => $config->config_name,
                    'shortcode' => $config->shortcode,
                    'account_number' => $config->account_number,
                ],
                'status' => 'pending',
            ]);

            // Initiate STK push
            $mpesa = MpesaService::forMerchant($merchant);

            $accountRef = $config->isPaybill()
                ? $config->account_number
                : "TXN-{$txn->id}";

            $description = "{$config->config_name} Payment";

            $response = $mpesa->stkPush($phone, $request->amount, $accountRef, $description);

            // Update transaction with response
            $txn->update([
                'response_payload' => $response,
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            ]);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'data' => [
                        'transaction_id' => $txn->id,
                        'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                        'config' => [
                            'name' => $config->config_name,
                            'type' => $config->type,
                            'identifier' => $config->getFullIdentifier()
                        ]
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['errorMessage'] ?? 'Payment initiation failed',
                'data' => $response
            ], 400);

        } catch (\Exception $e) {
            Log::error('process_with_config_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
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

        return $p;
    }

    public function testStkPush()
{
    $timestamp = date('YmdHis');
    $password  = base64_encode(env('MPESA_SHORTCODE') . env('MPESA_PASSKEY') . $timestamp);

    $curl_post_data = [
        'BusinessShortCode' => env('MPESA_SHORTCODE'),
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => 1,
        'PartyA' => '254714484762',   // Your real phone number
        'PartyB' => env('MPESA_SHORTCODE'),
        'PhoneNumber' => '254714484762',
        'CallBackURL' => env('MPESA_STKPUSH_CALLBACK'),
        'AccountReference' => 'TestRef',
        'TransactionDesc' => 'Test Payment'
    ];

    $merchant = Merchant::first();
    $mpesa = MpesaService::forMerchant($merchant);
    $access_token = $mpesa->getToken(); // Use the correct method to get the access token

    $response = Http::withToken($access_token)
        ->post(env('MPESA_BASE_URL_SANDBOX') . '/mpesa/stkpush/v1/processrequest', $curl_post_data);

    return $response->json();
}

}
