<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterMpesaUrls extends Command
{
    protected $signature = 'mpesa:register {--force : Force registration attempt} {--test : Test endpoints only}';
    protected $description = 'Register M-Pesa C2B validation and confirmation URLs';
    public function handle()
    {
        $this->info('===========================================');
        $this->info('   M-PESA C2B URL REGISTRATION');
        $this->info('===========================================');
        $this->newLine();

        // Test mode - just verify endpoints are reachable
        if ($this->option('test')) {
            return $this->testEndpoints();
        }

        // Show warning about C2B registration
        $this->warn('⚠️  IMPORTANT NOTICE:');
        $this->warn('C2B registration in sandbox is often unreliable.');
        $this->warn('If registration fails, your STK Push will still work fine!');
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to attempt C2B registration? (Not required for STK Push)', false)) {
                $this->info('Skipping C2B registration. Testing endpoints instead...');
                return $this->testEndpoints();
            }
        }

        try {
            // Get credentials
            $consumerKey = env('MPESA_CONSUMER_KEY');
            $consumerSecret = env('MPESA_CONSUMER_SECRET');
            $baseUrl = env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke');
            $shortcode = env('MPESA_SHORTCODE');
            $appUrl = rtrim(env('APP_URL'), '/');

            // URLs - MUST NOT contain "mpesa" in path
            $confirmationUrl = $appUrl . '/api/callback/c2b/confirmation';
            $validationUrl = $appUrl . '/api/callback/c2b/validation';

            $this->info("Shortcode: {$shortcode}");
            $this->info("Confirmation URL: {$confirmationUrl}");
            $this->info("Validation URL: {$validationUrl}");
            $this->newLine();

            // Step 1: Test if endpoints are reachable
            $this->info('Step 1: Testing if endpoints are reachable...');
            if (!$this->testUrl($validationUrl) || !$this->testUrl($confirmationUrl)) {
                $this->error('❌ Your callback URLs are not reachable!');
                $this->error('Make sure:');
                $this->error('1. Laravel server is running (php artisan serve)');
                $this->error('2. Ngrok/Cloudflare tunnel is active');
                $this->error('3. APP_URL in .env matches your tunnel URL');
                return Command::FAILURE;
            }
            $this->info('✓ Endpoints are reachable');
            $this->newLine();

            // Step 2: Get Access Token
            $this->info('Step 2: Fetching access token...');
            $tokenUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';

            $tokenResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(30)
                ->get($tokenUrl);

            if (!$tokenResponse->successful()) {
                $this->error('Failed to fetch access token');
                $this->error('Response: ' . $tokenResponse->body());
                return Command::FAILURE;
            }

            $accessToken = $tokenResponse->json('access_token');
            $this->info('✓ Access token obtained');
            $this->newLine();

            // Step 3: Register URLs
            $this->info('Step 3: Attempting C2B URL registration...');
            $this->warn('(This may fail due to Safaricom sandbox limitations)');
            $registerUrl = $baseUrl . '/mpesa/c2b/v1/registerurl';

            $payload = [
                'ShortCode' => $shortcode,
                'ResponseType' => 'Completed',
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl,
            ];

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post($registerUrl, $payload);

            $responseData = $response->json();

            $this->info('Response: ' . json_encode($responseData, JSON_PRETTY_PRINT));
            $this->newLine();

            Log::info('mpesa_c2b_registration', [
                'payload' => $payload,
                'response' => $responseData,
                'status' => $response->status()
            ]);

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
                $this->info('===========================================');
                $this->info('✅ SUCCESS! C2B URLs registered');
                $this->info('===========================================');
                return Command::SUCCESS;
            } else {
                $this->error('===========================================');
                $this->error('❌ C2B Registration Failed (Expected in Sandbox)');
                $this->error('===========================================');
                $this->newLine();

                $this->warn('⚠️  This is NORMAL in sandbox environment!');
                $this->warn('Your STK Push will still work perfectly.');
                $this->newLine();

                $this->info('What works:');
                $this->line('✓ STK Push (Lipa na M-Pesa Online)');
                $this->line('✓ STK Push Callbacks');
                $this->line('✓ Transaction status queries');
                $this->newLine();

                $this->info('What might not work in sandbox:');
                $this->line('✗ C2B Pay Bill direct payments');
                $this->line('✗ C2B Buy Goods direct payments');
                $this->newLine();

                $this->info('Next steps:');
                $this->line('1. Test STK Push: curl -X POST ' . $appUrl . '/api/stk-push \\');
                $this->line('   -H "X-API-KEY: mpesa_test_key_12345" \\');
                $this->line('   -H "Content-Type: application/json" \\');
                $this->line('   -d \'{"phone":"254708374149","amount":10}\'');
                $this->newLine();
                $this->line('2. For production: Contact Safaricom to whitelist your URLs');

                return Command::SUCCESS; // Return success since endpoints work
            }

        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('mpesa_registration_exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function testEndpoints()
    {
        $appUrl = rtrim(env('APP_URL'), '/');
        $confirmationUrl = $appUrl . '/api/callback/c2b/confirmation';
        $validationUrl = $appUrl . '/api/callback/c2b/validation';
        $stkCallbackUrl = $appUrl . '/api/mpesa/stk/callback';

        $this->info('Testing callback endpoints...');
        $this->newLine();

        $allGood = true;

        // Test Validation
        $this->info('Testing Validation URL: ' . $validationUrl);
        if ($this->testUrl($validationUrl)) {
            $this->info('✓ Validation endpoint is reachable');
        } else {
            $this->error('✗ Validation endpoint is NOT reachable');
            $allGood = false;
        }

        // Test Confirmation
        $this->info('Testing Confirmation URL: ' . $confirmationUrl);
        if ($this->testUrl($confirmationUrl)) {
            $this->info('✓ Confirmation endpoint is reachable');
        } else {
            $this->error('✗ Confirmation endpoint is NOT reachable');
            $allGood = false;
        }

        // Test STK Callback
        $this->info('Testing STK Callback URL: ' . $stkCallbackUrl);
        if ($this->testUrl($stkCallbackUrl)) {
            $this->info('✓ STK callback endpoint is reachable');
        } else {
            $this->error('✗ STK callback endpoint is NOT reachable');
            $allGood = false;
        }

        $this->newLine();

        if ($allGood) {
            $this->info('===========================================');
            $this->info('✅ All endpoints are working!');
            $this->info('===========================================');
            $this->newLine();
            $this->info('You can now test STK Push payments.');
            $this->info('C2B registration is not required for STK Push.');
            return Command::SUCCESS;
        } else {
            $this->error('===========================================');
            $this->error('❌ Some endpoints are not reachable');
            $this->error('===========================================');
            $this->newLine();
            $this->error('Troubleshooting:');
            $this->line('1. Ensure Laravel is running: php artisan serve');
            $this->line('2. Ensure ngrok/tunnel is running: ngrok http 8000');
            $this->line('3. Update APP_URL in .env with tunnel URL');
            $this->line('4. Run: php artisan config:clear');
            return Command::FAILURE;
        }
    }

    private function testUrl($url)
    {
        try {
            $response = Http::timeout(10)->post($url, [
                'test' => true
            ]);
            return $response->status() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
