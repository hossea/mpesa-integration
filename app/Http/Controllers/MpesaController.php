<?php

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
}
