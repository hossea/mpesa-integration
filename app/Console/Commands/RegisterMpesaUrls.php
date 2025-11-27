<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterMpesaUrls extends Command
{
    protected $signature = 'mpesa:register';
    protected $description = 'Registers M-PESA C2B URLs with Safaricom Daraja';

    public function handle()
    {
        $this->info("Registering M-PESA URLs...");

        // Get Access Token
        $consumerKey = env('MPESA_CONSUMER_KEY');
        $consumerSecret = env('MPESA_CONSUMER_SECRET');

        $tokenUrl = env('MPESA_BASE_URL_SANDBOX') . '/oauth/v1/generate?grant_type=client_credentials';

        $tokenResponse = Http::withBasicAuth($consumerKey, $consumerSecret)->get($tokenUrl);

        if (!$tokenResponse->ok()) {
            $this->error("Failed to fetch access token");
            return;
        }

        $accessToken = $tokenResponse['access_token'];

        // Register URLs
        $url = env('MPESA_BASE_URL_SANDBOX') . '/mpesa/c2b/v1/registerurl';

        $response = Http::withToken($accessToken)
            ->post($url, [
                "ShortCode"       => env('MPESA_SHORTCODE'),
                "ResponseType"    => "Completed",
                "ConfirmationURL" => env('MPESA_C2B_CONFIRMATION_URL'),
                "ValidationURL"   => env('MPESA_C2B_VALIDATION_URL'),
            ]);

        $this->info("Response:");
        $this->info($response->body());
    }
}
