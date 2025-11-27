<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MpesaService;
use App\Models\MpesaTransaction;
use App\Models\Merchant;
use Illuminate\Support\Facades\Log;

class ProcessB2CJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300]; // Retry after 1, 2, and 5 minutes

    protected $phone;
    protected $amount;
    protected $commandId;
    protected $remarks;
    protected $merchantId;
    protected $referenceId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $phone,
        float $amount,
        string $commandId = 'BusinessPayment',
        string $remarks = 'Payment',
        ?int $merchantId = null,
        ?string $referenceId = null
    ) {
        $this->phone = $phone;
        $this->amount = $amount;
        $this->commandId = $commandId;
        $this->remarks = $remarks;
        $this->merchantId = $merchantId;
        $this->referenceId = $referenceId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing B2C payment', [
                'phone' => $this->phone,
                'amount' => $this->amount,
                'attempt' => $this->attempts()
            ]);

            // Get merchant
            $merchant = $this->merchantId
                ? Merchant::find($this->merchantId)
                : Merchant::first();

            if (!$merchant) {
                throw new \Exception('Merchant not found');
            }

            // Create transaction record
            $txn = MpesaTransaction::create([
                'merchant_id' => $merchant->id,
                'type' => 'b2c',
                'phone' => $this->phone,
                'amount' => $this->amount,
                'request_payload' => [
                    'phone' => $this->phone,
                    'amount' => $this->amount,
                    'command_id' => $this->commandId,
                    'remarks' => $this->remarks,
                    'reference_id' => $this->referenceId,
                ],
                'status' => 'pending',
            ]);

            // Initiate B2C payment
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->b2c(
                $this->phone,
                $this->amount,
                $this->commandId,
                $this->remarks
            );

            // Update transaction with response
            $txn->update([
                'response_payload' => $response,
                'transaction_id' => $response['ConversationID'] ?? null,
                'merchant_request_id' => $response['OriginatorConversationID'] ?? null,
                'status' => isset($response['ResponseCode']) && $response['ResponseCode'] == '0'
                    ? 'processing'
                    : 'failed',
            ]);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                Log::info('B2C payment initiated successfully', [
                    'transaction_id' => $txn->id,
                    'conversation_id' => $response['ConversationID'] ?? null
                ]);
            } else {
                $errorMessage = $response['errorMessage'] ?? $response['ResponseDescription'] ?? 'Unknown error';
                Log::error('B2C payment failed', [
                    'transaction_id' => $txn->id,
                    'error' => $errorMessage,
                    'response' => $response
                ]);

                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('B2C job failed', [
                'phone' => $this->phone,
                'amount' => $this->amount,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // If we've exhausted all retries, mark as failed
            if ($this->attempts() >= $this->tries) {
                if (isset($txn)) {
                    $txn->update(['status' => 'failed']);
                }
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('B2C job permanently failed', [
            'phone' => $this->phone,
            'amount' => $this->amount,
            'error' => $exception->getMessage()
        ]);

        // You can send notifications here
        // Notification::route('mail', config('app.admin_email'))
        //     ->notify(new B2CPaymentFailed($this->phone, $this->amount, $exception->getMessage()));
    }
}
