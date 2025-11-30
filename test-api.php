<?php

$baseUrl = 'https://016ef05f4b6b.ngrok-free.app';
$apiKey = 'sB9MfgleKYaX69VrLQmoy5CZxw9du28od5u2jv8av3zf2auEruAbweBeQbFSmbck';

echo "===========================================\n";
echo "   M-PESA GATEWAY API TEST\n";
echo "===========================================\n\n";

// Test 1: Health Check
echo "Test 1: Health Check\n";
echo "--------------------\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $apiKey,
    'Accept: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode !== 200) {
    echo " Health check failed! Check your server and API key.\n";
    exit(1);
}

echo "✅ Health check passed!\n\n";

// Test 2: STK Push
echo "Test 2: STK Push\n";
echo "--------------------\n";
$payload = [
    'phone' => '254714484762',
    'amount' => 10,
    'account_ref' => 'TEST-' . time(),
    'description' => 'Test Payment from Script'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/stk-push');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $httpCode\n";
echo "Response: $response\n\n";

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['success']) && $data['success']) {
    echo "STK Push initiated successfully!\n";
    echo "Checkout Request ID: " . ($data['data']['checkout_request_id'] ?? 'N/A') . "\n";
    echo "\n📱 Check your phone (254708374149) for the M-PESA prompt!\n\n";

    $checkoutRequestId = $data['data']['checkout_request_id'] ?? null;

    if ($checkoutRequestId) {
        // Wait a bit then check status
        echo "Waiting 5 seconds before checking status...\n";
        sleep(5);

        // Test 3: Check Status
        echo "\nTest 3: Check Transaction Status\n";
        echo "--------------------\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/stk-push/status/' . $checkoutRequestId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . $apiKey,
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "Status Code: $httpCode\n";
        echo "Response: " . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    echo "STK Push failed!\n";
    echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n\n";
}

// Test 4: Get Transactions
echo "Test 4: Get Recent Transactions\n";
echo "--------------------\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/transactions?per_page=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $apiKey,
    'Accept: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $httpCode\n";
$data = json_decode($response, true);
if (isset($data['data']['data'])) {
    echo "Total Transactions: " . count($data['data']['data']) . "\n";
    foreach ($data['data']['data'] as $txn) {
        echo "  - ID: {$txn['id']}, Status: {$txn['status']}, Amount: {$txn['amount']}\n";
    }
} else {
    echo "Response: $response\n";
}

echo "\n===========================================\n";
echo "   TEST COMPLETE\n";
echo "===========================================\n";
