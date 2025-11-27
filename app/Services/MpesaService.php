<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Merchant;
use App\Services\MpesaTokenService;

class MpesaService
{
    protected $base;
    protected MpesaTokenService $tokenService;
    protected Merchant $merchant;

    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;
        $env = env('MPESA_ENVIRONMENT', 'sandbox');

        $this->base = $env === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $this->tokenService = new MpesaTokenService(
            $this->base,
            $this->merchant->consumer_key ?? env('MPESA_CONSUMER_KEY'),
            $this->merchant->consumer_secret ?? env('MPESA_CONSUMER_SECRET')
        );
    }

    public static function forMerchant(Merchant $merchant): MpesaService
    {
        return new self($merchant);
    }

    public static function forDefaultMerchant(): ?MpesaService
    {
        $merchant = Merchant::first();
        return $merchant ? new self($merchant) : null;
    }

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    protected function getToken(): string
    {
        return $this->tokenService->getToken();
    }

    /**
     * STK Push
     */
    public function stkPush(string $phone, int|float $amount, string $accountRef = 'Ref', string $desc = 'Payment'): array
    {
        try {
            $token = $this->getToken();
            $timestamp = now()->format('YmdHis');
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');
            $passkey = $this->merchant->passkey ?? env('MPESA_PASSKEY');
            $password = base64_encode($shortcode . $passkey . $timestamp);

            $payload = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $amount,
                'PartyA' => $phone,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => env('APP_URL') . '/api/mpesa/stk/callback',
                'AccountReference' => $accountRef,
                'TransactionDesc' => $desc,
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/stkpush/v1/processrequest', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('stk_push_service_error', [
                'error' => $e->getMessage(),
                'phone' => $phone,
                'amount' => $amount
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * STK Push Query
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        try {
            $token = $this->getToken();
            $timestamp = now()->format('YmdHis');
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');
            $passkey = $this->merchant->passkey ?? env('MPESA_PASSKEY');
            $password = base64_encode($shortcode . $passkey . $timestamp);

            $payload = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/stkpushquery/v1/query', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('stk_query_service_error', [
                'error' => $e->getMessage(),
                'checkout_request_id' => $checkoutRequestId
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * B2C
     */
    public function b2c(string $phone, int|float $amount, string $commandId = 'BusinessPayment', string $remarks = 'Payment'): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');

            $payload = [
                'InitiatorName' => $this->merchant->initiator_name ?? env('MPESA_INITIATOR_NAME'),
                'SecurityCredential' => $this->merchant->security_credential ?? env('MPESA_SECURITY_CREDENTIAL'),
                'CommandID' => $commandId,
                'Amount' => (int) $amount,
                'PartyA' => $shortcode,
                'PartyB' => $phone,
                'Remarks' => $remarks,
                'QueueTimeOutURL' => env('APP_URL') . '/api/mpesa/b2c/timeout',
                'ResultURL' => env('APP_URL') . '/api/mpesa/b2c/result',
                'Occasion' => '',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/b2c/v1/paymentrequest', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('b2c_service_error', [
                'error' => $e->getMessage(),
                'phone' => $phone,
                'amount' => $amount
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * B2B
     */
    public function b2b(string $receiverShortcode, int|float $amount, string $commandId = 'BusinessPayBill', string $remarks = 'Payment'): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');

            $payload = [
                'Initiator' => $this->merchant->initiator_name ?? env('MPESA_INITIATOR_NAME'),
                'SecurityCredential' => $this->merchant->security_credential ?? env('MPESA_SECURITY_CREDENTIAL'),
                'CommandID' => $commandId,
                'SenderIdentifierType' => '4',
                'ReceiverIdentifierType' => '4', // FIXED
                'Amount' => (int) $amount,
                'PartyA' => $shortcode,
                'PartyB' => $receiverShortcode,
                'AccountReference' => 'Ref',
                'Remarks' => $remarks,
                'QueueTimeOutURL' => env('APP_URL') . '/api/mpesa/b2b/timeout',
                'ResultURL' => env('APP_URL') . '/api/mpesa/b2b/result',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/b2b/v1/paymentrequest', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('b2b_service_error', [
                'error' => $e->getMessage(),
                'receiver' => $receiverShortcode,
                'amount' => $amount
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * C2B Register URLs
     */
    public function registerC2BUrls(string $shortcode = null): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $shortcode ?? ($this->merchant->shortcode ?? env('MPESA_SHORTCODE'));

            $payload = [
                'ShortCode' => $shortcode,
                'ResponseType' => 'Completed',
                'ConfirmationURL' => env('APP_URL') . '/api/mpesa/c2b/confirmation',
                'ValidationURL' => env('APP_URL') . '/api/mpesa/c2b/validation',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/c2b/v1/registerurl', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('c2b_register_service_error', [
                'error' => $e->getMessage(),
                'shortcode' => $shortcode
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Transaction Status Query
     */
    public function transactionStatus(string $transactionId, string $remarks = 'Status Query'): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');

            $payload = [
                'Initiator' => $this->merchant->initiator_name ?? env('MPESA_INITIATOR_NAME'),
                'SecurityCredential' => $this->merchant->security_credential ?? env('MPESA_SECURITY_CREDENTIAL'),
                'CommandID' => 'TransactionStatusQuery',
                'TransactionID' => $transactionId,
                'PartyA' => $shortcode,
                'IdentifierType' => '4',
                'ResultURL' => env('APP_URL') . '/api/mpesa/transaction-status/result',
                'QueueTimeOutURL' => env('APP_URL') . '/api/mpesa/transaction-status/timeout',
                'Remarks' => $remarks,
                'Occasion' => '',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/transactionstatus/v1/query', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('transaction_status_service_error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Account Balance
     */
    public function accountBalance(string $remarks = 'Balance Query'): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');

            $payload = [
                'Initiator' => $this->merchant->initiator_name ?? env('MPESA_INITIATOR_NAME'),
                'SecurityCredential' => $this->merchant->security_credential ?? env('MPESA_SECURITY_CREDENTIAL'),
                'CommandID' => 'AccountBalance',
                'PartyA' => $shortcode,
                'IdentifierType' => '4',
                'Remarks' => $remarks,
                'QueueTimeOutURL' => env('APP_URL') . '/api/mpesa/balance/timeout',
                'ResultURL' => env('APP_URL') . '/api/mpesa/balance/result',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/accountbalance/v1/query', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('account_balance_service_error', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Reversal
     */
    public function reversal(string $transactionId, int|float $amount, string $remarks = 'Reversal'): array
    {
        try {
            $token = $this->getToken();
            $shortcode = $this->merchant->shortcode ?? env('MPESA_SHORTCODE');

            $payload = [
                'Initiator' => $this->merchant->initiator_name ?? env('MPESA_INITIATOR_NAME'),
                'SecurityCredential' => $this->merchant->security_credential ?? env('MPESA_SECURITY_CREDENTIAL'),
                'CommandID' => 'TransactionReversal',
                'TransactionID' => $transactionId,
                'Amount' => (int) $amount,
                'ReceiverParty' => $shortcode,
                'ReceiverIdentifierType' => '11', // FIXED
                'ResultURL' => env('APP_URL') . '/api/mpesa/reversal/result',
                'QueueTimeOutURL' => env('APP_URL') . '/api/mpesa/reversal/timeout',
                'Remarks' => $remarks,
                'Occasion' => '',
            ];

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->base . '/mpesa/reversal/v1/request', $payload);

            return $response->json() ?? ['error' => 'Empty response'];

        } catch (\Exception $e) {
            Log::error('reversal_service_error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}
