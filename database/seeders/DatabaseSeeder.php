<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Merchant;
use App\Models\ApiClient;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default merchant
        $merchant = Merchant::firstOrCreate(
            ['shortcode' => env('MPESA_SHORTCODE', '174379')],
            [
                'name' => 'Default Merchant',
                'shortcode' => env('MPESA_SHORTCODE', '174379'),
                'consumer_key' => env('MPESA_CONSUMER_KEY'),
                'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
                'passkey' => env('MPESA_PASSKEY'),
                'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
                'meta' => [
                    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
                    'created_at' => now()->toDateTimeString(),
                ],
            ]
        );

        $this->command->info("✅ Default merchant created: {$merchant->name}");

        // Create default API client
        $apiKey = Str::random(64);
        $apiClient = ApiClient::firstOrCreate(
            ['name' => 'Default API Client'],
            [
                'name' => 'Default API Client',
                'api_key' => $apiKey,
                'allowed_domains' => json_encode(['localhost', '127.0.0.1', '*.ngrok.io']),
            ]
        );

        $this->command->info("✅ API Client created: {$apiClient->name}");
        $this->command->warn("📋 API Key: {$apiClient->api_key}");
        $this->command->warn("⚠️  IMPORTANT: Save this API key securely! You'll need it for API requests.");

        // Create test merchant for multi-tenant testing (optional)
        if (env('APP_ENV') === 'local') {
            $testMerchant = Merchant::firstOrCreate(
                ['shortcode' => '600000'],
                [
                    'name' => 'Test Merchant',
                    'shortcode' => '600000',
                    'consumer_key' => 'test_consumer_key',
                    'consumer_secret' => 'test_consumer_secret',
                    'passkey' => 'test_passkey',
                    'security_credential' => 'test_credential',
                    'meta' => [
                        'environment' => 'sandbox',
                        'is_test' => true,
                    ],
                ]
            );

            $this->command->info("✅ Test merchant created: {$testMerchant->name}");
        }

        $this->command->info("\n🎉 Database seeding completed successfully!");
        $this->command->info("\n📝 Next steps:");
        $this->command->info("1. Update your .env file with M-Pesa credentials");
        $this->command->info("2. Start the server: php artisan serve");
        $this->command->info("3. Test STK Push at: http://localhost:8000");
        $this->command->info("4. Use the API key above for API requests\n");
    }
}
