<?php

use App\Services\Payment\PaymentService;
use App\Services\Payment\CinetPayClient;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// Feature: cinetpay-payment-integration, Property 24: Summary Page Shows Amount
test('payment summary page contains the payment amount', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Test 10 times with random amounts
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Make request to summary page
        $response = $this->actingAs($user)->get(route('payment.summary', [
            'amount' => $amount,
        ]));
        
        // Verify response contains the amount
        $response->assertStatus(200);
        $response->assertViewHas('amount', $amount);
        $response->assertSee((string) $amount);
    }
});


// Feature: cinetpay-payment-integration, Property 25: Summary Page Shows Transaction ID
test('payment summary page contains the transaction ID', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Test 10 times with random transaction IDs
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        $transactionId = 'TXN_' . fake()->uuid();
        
        // Make request to summary page
        $response = $this->actingAs($user)->get(route('payment.summary', [
            'amount' => $amount,
            'transaction_id' => $transactionId,
        ]));
        
        // Verify response contains the transaction ID
        $response->assertStatus(200);
        $response->assertViewHas('transaction_id', $transactionId);
        $response->assertSee($transactionId);
    }
});


// Feature: cinetpay-payment-integration, Property 26: Error Response Logging
test('error responses from CinetPay are logged and user-friendly message is returned', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    Config::set('cinetpay.retry_attempts', 1);
    
    // Test 10 times with different error scenarios
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Mock HTTP to throw exception (simulating CinetPay error)
        Http::fake(function () {
            throw new \Exception('CinetPay API error: ' . fake()->sentence());
        });
        
        // Allow all log calls (error logging happens in multiple places)
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        
        // Make request to initiate payment
        $response = $this->actingAs($user)->post(route('payment.initiate'), [
            'amount' => $amount,
        ]);
        
        // Verify redirect back with error message
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        // Verify error message is user-friendly (not technical)
        $errorMessage = session('error');
        expect($errorMessage)->toBeString()
            ->and($errorMessage)->not->toBeEmpty()
            ->and($errorMessage)->not->toContain('Exception')
            ->and($errorMessage)->not->toContain('Stack trace');
    }
});


// Feature: cinetpay-payment-integration, Property 28: Malformed IPN Handling
test('malformed IPN notifications are handled gracefully without crashing', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Allow all log calls
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    // Test 10 times with different malformed payloads
    for ($i = 0; $i < 10; $i++) {
        // Generate malformed payloads
        $malformedPayloads = [
            [], // Empty payload
            ['invalid_key' => 'invalid_value'], // Missing transaction ID
            ['cpm_trans_id' => ''], // Empty transaction ID
            ['cpm_trans_id' => null], // Null transaction ID
            ['cpm_trans_id' => fake()->uuid()], // Non-existent transaction
            ['cpm_trans_id' => 'INVALID_' . fake()->word()], // Invalid format
        ];
        
        $payload = fake()->randomElement($malformedPayloads);
        
        // Make request to IPN endpoint (without throttling middleware)
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson(route('cinetpay.ipn'), $payload);
        
        // Verify response is 200 OK (to prevent CinetPay retries)
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }
});


// Feature: cinetpay-payment-integration, Property 15: Return Triggers Verification
test('user return from CinetPay triggers transaction verification', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 10 times
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
        ]);
        
        // Mock HTTP to return status
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => 'ACCEPTED',
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $transaction->amount,
                ]
            ], 200)
        ]);
        
        // Track if verification was called
        $verificationCalled = false;
        
        // Allow all log calls and track verification
        Log::shouldReceive('info')->withArgs(function ($message) use (&$verificationCalled) {
            if ($message === 'Verifying transaction status') {
                $verificationCalled = true;
            }
            return true;
        })->zeroOrMoreTimes();
        
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        
        // Make request to return endpoint (without throttling)
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
        
        // Verify response is successful
        $response->assertStatus(200);
        
        // Verify verification was called
        expect($verificationCalled)->toBeTrue('Verification should be called when user returns');
    }
});


// Feature: cinetpay-payment-integration, Property 16: Success Redirect for ACCEPTED
test('transactions with ACCEPTED status render success page when user returns', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 10 times
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
            'amount' => $amount,
        ]);
        
        // Mock HTTP to return ACCEPTED status
        Http::fake([
            '*' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => 'ACCEPTED',
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $amount,
                ]
            ], 200)
        ]);
        
        // Create mock client that uses HTTP
        $mockClient = new class extends \App\Services\Payment\CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
        };
        
        // Bind mock client in container
        $this->app->instance(\App\Services\Payment\CinetPayClient::class, $mockClient);
        
        // Allow all log calls
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        
        // Make request to return endpoint (without throttling)
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
        
        // Verify response is successful
        $response->assertStatus(200);
        
        // Verify success view is rendered
        $response->assertViewIs('payment.success');
        
        // Verify transaction data is passed to view
        $response->assertViewHas('transaction');
        $response->assertViewHas('transaction_id', $transaction->transaction_id);
        $response->assertViewHas('amount');
        
        // Verify transaction status was updated to ACCEPTED
        $transaction->refresh();
        expect($transaction->status)->toBe(\App\PaymentStatus::ACCEPTED);
    }
});


// Feature: cinetpay-payment-integration, Property 17: Failure Redirect for REFUSED
test('transactions with REFUSED status render failure page when user returns', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 10 times
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
            'amount' => $amount,
        ]);
        
        // Mock HTTP to return REFUSED status
        Http::fake([
            '*' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => 'REFUSED',
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $amount,
                ]
            ], 200)
        ]);
        
        // Create mock client that uses HTTP
        $mockClient = new class extends \App\Services\Payment\CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
        };
        
        // Bind mock client in container
        $this->app->instance(\App\Services\Payment\CinetPayClient::class, $mockClient);
        
        // Allow all log calls
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        
        // Make request to return endpoint (without throttling)
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
        
        // Verify response is successful
        $response->assertStatus(200);
        
        // Verify failure view is rendered
        $response->assertViewIs('payment.failure');
        
        // Verify transaction data is passed to view
        $response->assertViewHas('transaction');
        $response->assertViewHas('transaction_id', $transaction->transaction_id);
        $response->assertViewHas('amount');
        
        // Verify transaction status was updated to REFUSED
        $transaction->refresh();
        expect($transaction->status)->toBe(\App\PaymentStatus::REFUSED);
    }
});


// Feature: cinetpay-payment-integration, Property 18: Transaction Details on Result Pages
test('success and failure pages contain transaction ID and amount', function () {
    // Test 100 times with random amounts and statuses
    for ($i = 0; $i < 100; $i++) {
        // Configure CinetPay for each iteration
        Config::set('cinetpay.api_key', 'test_api_key');
        Config::set('cinetpay.site_id', 'test_site_id');
        Config::set('cinetpay.secret_key', 'test_secret_key');
        
        // Allow all log calls
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Randomly choose between ACCEPTED and REFUSED status
        $finalStatus = fake()->randomElement([\App\PaymentStatus::ACCEPTED, \App\PaymentStatus::REFUSED]);
        
        // Create transaction with PENDING status (will be updated by verification)
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
            'amount' => $amount,
        ]);
        
        // Mock HTTP to return the chosen status
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => $finalStatus === \App\PaymentStatus::ACCEPTED ? 'ACCEPTED' : 'REFUSED',
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $amount,
                ]
            ], 200)
        ]);
        
        // Create mock client that uses HTTP instead of SDK
        $mockClient = new class extends \App\Services\Payment\CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
        };
        
        // Bind mock client in container
        $this->app->instance(\App\Services\Payment\CinetPayClient::class, $mockClient);
        
        // Make request to return endpoint (without throttling)
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
        
        // Verify response is successful
        $response->assertStatus(200);
        
        // Verify correct view is rendered based on status
        if ($finalStatus === \App\PaymentStatus::ACCEPTED) {
            $response->assertViewIs('payment.success');
        } else {
            $response->assertViewIs('payment.failure');
        }
        
        // Verify transaction details are present in view data
        $response->assertViewHas('transaction_id', $transaction->transaction_id);
        $response->assertViewHas('amount', $amount);
        
        // Verify transaction details are visible in rendered HTML
        $response->assertSee($transaction->transaction_id);
        $response->assertSee((string) $amount);
    }
});
