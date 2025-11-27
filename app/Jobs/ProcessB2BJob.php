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

class ProcessB2BJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300];

    protected $receiverShortcode;
    protected $amount;
    protected $commandId;
    protected $remarks;
    protected $merchantId;
    protected $referenceId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $receiverShortcode,
        float $amount,
        string $commandId = 'BusinessPayBill',
        string $remarks = 'Payment',
        ?int $merchantId = null,
        ?string $referenceId = null
    ) {
        $this->receiverShortcode = $receiverShortcode;
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
            Log::info('Processing B2B payment', [
                'receiver' => $this->receiverShortcode,
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
                'type' => 'b2b',
                'amount' => $this->amount,
                'request_payload' => [
                    'receiver_shortcode' => $this->receiverShortcode,
                    'amount' => $this->amount,
                    'command_id' => $this->commandId,
                    'remarks' => $this->remarks,
                    'reference_id' => $this->referenceId,
                ],
                'status' => 'pending',
            ]);

            // Initiate B2B payment
            $mpesa = MpesaService::forMerchant($merchant);
            $response = $mpesa->b2b(
                $this->receiverShortcode,
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
                Log::info('B2B payment initiated successfully', [
                    'transaction_id' => $txn->id,
                    'conversation_id' => $response['ConversationID'] ?? null
                ]);
            } else {
                $errorMessage = $response['errorMessage'] ?? $response['ResponseDescription'] ?? 'Unknown error';
                Log::error('B2B payment failed', [
                    'transaction_id' => $txn->id,
                    'error' => $errorMessage,
                    'response' => $response
                ]);

                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('B2B job failed', [
                'receiver' => $this->receiverShortcode,
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
        Log::error('B2B job permanently failed', [
            'receiver' => $this->receiverShortcode,
            'amount' => $this->amount,
            'error' => $exception->getMessage()
        ]);

        // You can send notifications here
        // Notification::route('mail', config('app.admin_email'))
        //     ->notify(new B2BPaymentFailed($this->receiverShortcode, $this->amount, $exception->getMessage()));
    }
}
