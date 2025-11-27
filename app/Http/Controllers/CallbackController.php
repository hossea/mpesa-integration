<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\MpesaTransaction;

class CallbackController extends Controller
{
    /**
     * Handle STK Push Callback
     */
    public function stkCallback(Request $request)
    {
        $payload = $request->all();
        Log::info('mpesa_stk_callback', $payload);

        try {
            if (isset($payload['Body']['stkCallback'])) {
                $cb = $payload['Body']['stkCallback'];
                $checkoutRequestId = $cb['CheckoutRequestID'] ?? null;
                $merchantRequestId = $cb['MerchantRequestID'] ?? null;
                $resultCode = $cb['ResultCode'] ?? null;
                $resultDesc = $cb['ResultDesc'] ?? 'Unknown';

                // Find transaction by CheckoutRequestID
                $txn = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

                if ($txn) {
                    $updateData = [
                        'merchant_request_id' => $merchantRequestId,
                        'status' => $resultCode == 0 ? 'success' : 'failed',
                        'callback_payload' => $cb,
                    ];

                    // If successful, extract payment details
                    if ($resultCode == 0 && isset($cb['CallbackMetadata']['Item'])) {
                        $items = $cb['CallbackMetadata']['Item'];
                        foreach ($items as $item) {
                            if ($item['Name'] === 'MpesaReceiptNumber') {
                                $updateData['transaction_id'] = $item['Value'];
                            }
                            if ($item['Name'] === 'Amount') {
                                $updateData['amount'] = $item['Value'];
                            }
                            if ($item['Name'] === 'PhoneNumber') {
                                $updateData['phone'] = $item['Value'];
                            }
                        }
                    }

                    $txn->update($updateData);
                } else {
                    // Create new transaction record if not found
                    $newTxnData = [
                        'type' => 'stk',
                        'checkout_request_id' => $checkoutRequestId,
                        'merchant_request_id' => $merchantRequestId,
                        'status' => $resultCode == 0 ? 'success' : 'failed',
                        'callback_payload' => $cb,
                    ];

                    if ($resultCode == 0 && isset($cb['CallbackMetadata']['Item'])) {
                        $items = $cb['CallbackMetadata']['Item'];
                        foreach ($items as $item) {
                            if ($item['Name'] === 'MpesaReceiptNumber') {
                                $newTxnData['transaction_id'] = $item['Value'];
                            }
                            if ($item['Name'] === 'Amount') {
                                $newTxnData['amount'] = $item['Value'];
                            }
                            if ($item['Name'] === 'PhoneNumber') {
                                $newTxnData['phone'] = $item['Value'];
                            }
                        }
                    }

                    MpesaTransaction::create($newTxnData);
                }
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch (\Exception $e) {
            Log::error('stk_callback_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * C2B Validation Endpoint
     */
    public function c2bValidation(Request $request)
    {
        Log::info('c2b_validation', $request->all());

        // You can add validation logic here
        // Return 0 to accept, non-zero to reject

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }

    /**
     * C2B Confirmation Endpoint
     */
    public function c2bConfirmation(Request $request)
    {
        $payload = $request->all();
        Log::info('c2b_confirmation', $payload);

        try {
            MpesaTransaction::create([
                'type' => 'c2b',
                'transaction_id' => $payload['TransID'] ?? null,
                'phone' => $payload['MSISDN'] ?? null,
                'amount' => $payload['TransAmount'] ?? null,
                'merchant_request_id' => $payload['BillRefNumber'] ?? null,
                'callback_payload' => $payload,
                'status' => 'success'
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('c2b_confirmation_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * B2C Result Endpoint
     */
    public function b2cResult(Request $request)
    {
        $payload = $request->all();
        Log::info('b2c_result', $payload);

        try {
            if (isset($payload['Result'])) {
                $result = $payload['Result'];
                $conversationId = $result['ConversationID'] ?? null;
                $originatorConvId = $result['OriginatorConversationID'] ?? null;

                $txnData = [
                    'type' => 'b2c',
                    'transaction_id' => $conversationId,
                    'merchant_request_id' => $originatorConvId,
                    'status' => $result['ResultCode'] == 0 ? 'success' : 'failed',
                    'callback_payload' => $result,
                ];

                // Extract result parameters if successful
                if (isset($result['ResultParameters']['ResultParameter'])) {
                    foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                        if ($param['Key'] === 'TransactionReceipt') {
                            $txnData['transaction_id'] = $param['Value'];
                        }
                        if ($param['Key'] === 'TransactionAmount') {
                            $txnData['amount'] = $param['Value'];
                        }
                        if ($param['Key'] === 'ReceiverPartyPublicName') {
                            $txnData['phone'] = $param['Value'];
                        }
                    }
                }

                MpesaTransaction::create($txnData);
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('b2c_result_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * B2C Timeout Endpoint
     */
    public function b2cTimeout(Request $request)
    {
        $payload = $request->all();
        Log::warning('b2c_timeout', $payload);

        try {
            if (isset($payload['Result'])) {
                $result = $payload['Result'];
                MpesaTransaction::create([
                    'type' => 'b2c',
                    'transaction_id' => $result['ConversationID'] ?? null,
                    'merchant_request_id' => $result['OriginatorConversationID'] ?? null,
                    'status' => 'timeout',
                    'callback_payload' => $result,
                ]);
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('b2c_timeout_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * B2B Result Endpoint
     */
    public function b2bResult(Request $request)
    {
        $payload = $request->all();
        Log::info('b2b_result', $payload);

        try {
            if (isset($payload['Result'])) {
                $result = $payload['Result'];
                $txnData = [
                    'type' => 'b2b',
                    'transaction_id' => $result['ConversationID'] ?? null,
                    'merchant_request_id' => $result['OriginatorConversationID'] ?? null,
                    'status' => $result['ResultCode'] == 0 ? 'success' : 'failed',
                    'callback_payload' => $result,
                ];

                if (isset($result['ResultParameters']['ResultParameter'])) {
                    foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                        if ($param['Key'] === 'TransactionReceipt') {
                            $txnData['transaction_id'] = $param['Value'];
                        }
                        if ($param['Key'] === 'TransactionAmount') {
                            $txnData['amount'] = $param['Value'];
                        }
                    }
                }

                MpesaTransaction::create($txnData);
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('b2b_result_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * B2B Timeout Endpoint
     */
    public function b2bTimeout(Request $request)
    {
        $payload = $request->all();
        Log::warning('b2b_timeout', $payload);

        try {
            if (isset($payload['Result'])) {
                $result = $payload['Result'];
                MpesaTransaction::create([
                    'type' => 'b2b',
                    'transaction_id' => $result['ConversationID'] ?? null,
                    'merchant_request_id' => $result['OriginatorConversationID'] ?? null,
                    'status' => 'timeout',
                    'callback_payload' => $result,
                ]);
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('b2b_timeout_error', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }
}
