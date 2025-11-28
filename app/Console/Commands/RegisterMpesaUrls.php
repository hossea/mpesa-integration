<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterMpesaUrls extends Command
{
    protected $signature = 'mpesa:register {--shortcode= : Shortcode to register}';
    protected $description = 'Register M-Pesa C2B validation and confirmation URLs';

    public function handle()
    {
        $this->info('===========================================');
        $this->info('   M-PESA C2B URL REGISTRATION');
        $this->info('===========================================');
        $this->newLine();

        try {
            // Get credentials
            $consumerKey = env('MPESA_CONSUMER_KEY');
            $consumerSecret = env('MPESA_CONSUMER_SECRET');
            $baseUrl = env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke');
            $shortcode = $this->option('shortcode') ?? env('MPESA_SHORTCODE');
            $appUrl = env('APP_URL');

            // Validation URLs - MUST NOT contain "mpesa" in path
            $confirmationUrl = $appUrl . '/api/callback/c2b/confirmation';
            $validationUrl = $appUrl . '/api/callback/c2b/validation';

            $this->info("Shortcode: {$shortcode}");
            $this->info("Confirmation URL: {$confirmationUrl}");
            $this->info("Validation URL: {$validationUrl}");
            $this->newLine();

            // Step 1: Get Access Token
            $this->info('Step 1: Fetching access token...');
            $tokenUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
            
            $tokenResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(30)
                ->get($tokenUrl);

            if (!$tokenResponse->successful()) {
                $this->error('Failed to fetch access token');
                $this->error('Response: ' . $tokenResponse->body());
                Log::error('mpesa_token_error', ['response' => $tokenResponse->body()]);
                return Command::FAILURE;
            }

            $accessToken = $tokenResponse->json('access_token');
            $this->info('✓ Access token obtained successfully');
            $this->newLine();

            // Step 2: Register URLs
            $this->info('Step 2: Registering C2B URLs...');
            $registerUrl = $baseUrl . '/mpesa/c2b/v1/registerurl';

            $payload = [
                'ShortCode' => $shortcode,
                'ResponseType' => 'Completed',
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl,
            ];

            $this->info('Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));
            $this->newLine();

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post($registerUrl, $payload);

            $responseData = $response->json();

            $this->info('Response Code: ' . $response->status());
            $this->info('Response Body: ' . json_encode($responseData, JSON_PRETTY_PRINT));
            $this->newLine();

            Log::info('mpesa_c2b_registration', [
                'payload' => $payload,
                'response' => $responseData,
                'status' => $response->status()
            ]);

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
                $this->info('===========================================');
                $this->info('✓ SUCCESS! URLs registered successfully');
                $this->info('===========================================');
                return Command::SUCCESS;
            } else {
                $this->error('===========================================');
                $this->error('✗ FAILED! Registration unsuccessful');
                $this->error('===========================================');
                
                if (isset($responseData['errorMessage'])) {
                    $this->error('Error: ' . $responseData['errorMessage']);
                }
                
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Exception occurred: ' . $e->getMessage());
            Log::error('mpesa_registration_exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}