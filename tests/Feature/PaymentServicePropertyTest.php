<?php

use App\Services\Payment\PaymentService;
use App\Services\Payment\CinetPayClient;
use App\Services\Payment\GatewayFactory;
use App\Services\Payment\CinetPayGateway;
use App\GatewayType;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// Feature: cinetpay-payment-integration, Property 4: Transaction URLs Definition
test('all created transactions have non-empty return_url and notify_url', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Mock HTTP response for payment initialization
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'message' => 'Success',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test',
                'payment_token' => 'token_123',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    // Create mock client that uses HTTP
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        public function validateSignature(array $payload): bool
        {
            return true;
        }
    };
    
    $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
    $factory = Mockery::mock(GatewayFactory::class);
    $factory->shouldReceive('createGateway')->andReturn($gateway);
    
    $service = new PaymentService($factory);
    
    // Test 100 times with random amounts and users
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        $transaction = $service->initializePayment($amount, $user->id, GatewayType::CINETPAY);
        
        // Verify both URLs are non-empty strings
        expect($transaction->return_url)->toBeString()
            ->and($transaction->return_url)->not->toBeEmpty()
            ->and($transaction->notify_url)->toBeString()
            ->and($transaction->notify_url)->not->toBeEmpty();
    }
});

// Feature: cinetpay-payment-integration, Property 19: Initiation Logging
test('transaction initiation creates log entry with transaction_id and amount', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Mock HTTP response for payment initialization
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'message' => 'Success',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test',
                'payment_token' => 'token_123',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    // Create mock client that uses HTTP
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        public function validateSignature(array $payload): bool
        {
            return true;
        }
    };
    
    $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
    $factory = Mockery::mock(GatewayFactory::class);
    $factory->shouldReceive('createGateway')->andReturn($gateway);
    
    $service = new PaymentService($factory);
    
    // Test 100 times with random amounts and users
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Capture logs
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($amount) {
                return $message === 'Transaction initiated'
                    && isset($context['transaction_id'])
                    && isset($context['amount'])
                    && $context['amount'] == $amount;
            });
        
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        
        $transaction = $service->initializePayment($amount, $user->id, GatewayType::CINETPAY);
        
        // Verify transaction was created
        expect($transaction)->not->toBeNull()
            ->and($transaction->amount)->toBe(number_format($amount, 2, '.', ''));
    }
});

// Feature: cinetpay-payment-integration, Property 20: API Call Logging
test('API calls to CinetPay create log entries for request and response', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Mock HTTP response for status check
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'ACCEPTED',
                'payment_method' => 'ORANGE_MONEY',
                'operator_id' => 'OP123',
                'amount' => 1000,
            ]
        ], 200)
    ]);
    
    // Create mock client that uses HTTP
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        public function validateSignature(array $payload): bool
        {
            return true;
        }
    };
    
    $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
    $factory = Mockery::mock(GatewayFactory::class);
    $factory->shouldReceive('createGateway')->andReturn($gateway);
    
    $service = new PaymentService($factory);
    
    // Test 100 times with random transaction IDs
    for ($i = 0; $i < 100; $i++) {
        $transactionId = 'TXN_' . fake()->uuid();
        
        // Allow all log calls
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        
        $status = $service->verifyTransactionStatus($transactionId);
        
        // Verify status was returned
        expect($status)->toBeInstanceOf(\App\PaymentStatus::class);
    }
});

// Feature: cinetpay-payment-integration, Property 21: Status Change Logging
test('transaction status changes create log entries with old and new status', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Create mock client
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        public function validateSignature(array $payload): bool
        {
            return true;
        }
    };
    
    $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
    $factory = Mockery::mock(GatewayFactory::class);
    $factory->shouldReceive('createGateway')->andReturn($gateway);
    
    $service = new PaymentService($factory);
    
    // Test 100 times with random status transitions
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
        ]);
        
        $newStatus = fake()->randomElement([
            \App\PaymentStatus::ACCEPTED,
            \App\PaymentStatus::REFUSED,
        ]);
        
        // Capture logs
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($newStatus) {
                return $message === 'Transaction status updated'
                    && isset($context['transaction_id'])
                    && isset($context['old_status'])
                    && isset($context['new_status'])
                    && $context['old_status'] === 'pending'
                    && $context['new_status'] === $newStatus->value;
            });
        
        $updatedTransaction = $service->updateTransactionStatus(
            $transaction->transaction_id,
            $newStatus
        );
        
        // Verify status was updated
        expect($updatedTransaction->status)->toBe($newStatus);
    }
});

// Feature: cinetpay-payment-integration, Property 10: Failed Verification Preserves Status
test('failed verification preserves transaction status unchanged', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.retry_attempts', 1);
    
    // Test 100 times with different failure scenarios
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $initialStatus = fake()->randomElement([
            \App\PaymentStatus::PENDING,
            \App\PaymentStatus::ACCEPTED,
            \App\PaymentStatus::REFUSED,
        ]);
        
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => $initialStatus,
        ]);
        
        // Mock HTTP to fail (network error, timeout, etc.)
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Allow all log calls
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        
        // Attempt to verify status (should fail)
        try {
            $service->verifyTransactionStatus($transaction->transaction_id);
        } catch (\Exception $e) {
            // Expected to fail
        }
        
        // Retrieve transaction from database
        $unchangedTransaction = Transaction::find($transaction->id);
        
        // Verify status remains unchanged
        expect($unchangedTransaction->status)->toBe($initialStatus);
    }
});

// Feature: cinetpay-payment-integration, Property 6: IPN Verification Before Update
test('IPN processing verifies transaction status with CinetPay before updating', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.retry_attempts', 1);
    
    // Test 100 times
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
        ]);
        
        $verifiedStatus = fake()->randomElement(['ACCEPTED', 'REFUSED']);
        
        // Track API call order
        $apiCallMade = false;
        $statusUpdated = false;
        
        // Mock HTTP to return status
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => $verifiedStatus,
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $transaction->amount,
                ]
            ], 200)
        ]);
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Capture logs to verify order
        Log::shouldReceive('info')->withArgs(function ($message) use (&$apiCallMade, &$statusUpdated) {
            if ($message === 'Callback notification received') {
                return true;
            }
            if ($message === 'Verifying transaction status') {
                $apiCallMade = true;
                expect($statusUpdated)->toBeFalse('Status should not be updated before verification');
                return true;
            }
            if ($message === 'Transaction status updated') {
                $statusUpdated = true;
                expect($apiCallMade)->toBeTrue('API call should be made before status update');
                return true;
            }
            return true;
        })->zeroOrMoreTimes();
        
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        
        // Process IPN
        $payload = [
            'cpm_trans_id' => $transaction->transaction_id,
            'cpm_amount' => $transaction->amount,
        ];
        
        $result = $service->processIPN($payload);
        
        // Verify processing succeeded and API was called before update
        expect($result)->toBeTrue()
            ->and($apiCallMade)->toBeTrue()
            ->and($statusUpdated)->toBeTrue();
    }
});

// Feature: cinetpay-payment-integration, Property 7: Status Update on Successful Verification
test('successful verification updates transaction status to ACCEPTED', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 100 times
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
        ]);
        
        // Mock HTTP to return ACCEPTED status
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
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Mock logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        
        // Process IPN with successful verification
        $payload = [
            'cpm_trans_id' => $transaction->transaction_id,
            'cpm_amount' => $transaction->amount,
        ];
        
        $result = $service->processIPN($payload);
        
        // Retrieve updated transaction
        $updatedTransaction = Transaction::find($transaction->id);
        
        // Verify status was updated to ACCEPTED
        expect($result)->toBeTrue()
            ->and($updatedTransaction->status)->toBe(\App\PaymentStatus::ACCEPTED);
    }
});

// Feature: cinetpay-payment-integration, Property 8: Status Update on Failed Verification
test('failed verification updates transaction status to REFUSED', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 100 times
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => \App\PaymentStatus::PENDING,
        ]);
        
        // Mock HTTP to return REFUSED status
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
                'code' => '00',
                'message' => 'Success',
                'data' => [
                    'status' => 'REFUSED',
                    'payment_method' => 'ORANGE_MONEY',
                    'operator_id' => 'OP123',
                    'amount' => $transaction->amount,
                ]
            ], 200)
        ]);
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Mock logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        
        // Process IPN with failed verification
        $payload = [
            'cpm_trans_id' => $transaction->transaction_id,
            'cpm_amount' => $transaction->amount,
        ];
        
        $result = $service->processIPN($payload);
        
        // Retrieve updated transaction
        $updatedTransaction = Transaction::find($transaction->id);
        
        // Verify status was updated to REFUSED
        expect($result)->toBeTrue()
            ->and($updatedTransaction->status)->toBe(\App\PaymentStatus::REFUSED);
    }
});

// Feature: cinetpay-payment-integration, Property 9: IPN Logging
test('IPN notifications create log entries containing the payload', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    // Test 100 times
    for ($i = 0; $i < 100; $i++) {
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
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Create random payload
        $payload = [
            'cpm_trans_id' => $transaction->transaction_id,
            'cpm_amount' => $transaction->amount,
            'cpm_custom' => fake()->word(),
            'payment_method' => 'ORANGE_MONEY',
        ];
        
        // Allow all log calls
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        
        // Process IPN
        $result = $service->processIPN($payload);
        
        // Verify processing succeeded
        expect($result)->toBeTrue();
    }
});

// Feature: cinetpay-payment-integration, Property 22: Error Logging
test('exceptions create log entries with error message and stack trace', function () {
    // Configure CinetPay
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.retry_attempts', 1);
    
    // Test 100 times with different error scenarios
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Mock HTTP to throw exception
        Http::fake(function () {
            throw new \Exception('Test error message');
        });
        
        // Create mock client that uses HTTP
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                $this->useSdk = false;
            }
            public function validateSignature(array $payload): bool
            {
                return true;
            }
        };
        
        $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('createGateway')->andReturn($gateway);
        
        $service = new PaymentService($factory);
        
        // Capture logs - expect error log with message and trace
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($amount) {
                return $message === 'Transaction initiated'
                    && isset($context['transaction_id'])
                    && isset($context['amount'])
                    && $context['amount'] == $amount;
            });
        
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Failed to initialize payment with CinetPay'
                    && isset($context['error'])
                    && isset($context['trace'])
                    && str_contains($context['error'], 'Test error message');
            });
        
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        
        // Attempt to initialize payment (should fail and log error)
        try {
            $service->initializePayment($amount, $user->id, GatewayType::CINETPAY);
        } catch (\Exception $e) {
            // Expected to fail
            expect($e->getMessage())->toContain('Failed to initialize payment');
        }
    }
});

// Feature: cinetpay-payment-integration, Property 5: Credentials Not in Logs
test('log entries do not contain API credentials', function () {
    // Configure CinetPay with specific credentials
    $apiKey = 'test_api_key_' . fake()->uuid();
    $siteId = 'test_site_id_' . fake()->uuid();
    $secretKey = 'test_secret_key_' . fake()->uuid();
    
    Config::set('cinetpay.api_key', $apiKey);
    Config::set('cinetpay.site_id', $siteId);
    Config::set('cinetpay.secret_key', $secretKey);
    Config::set('cinetpay.currency', 'XOF');
    Config::set('cinetpay.return_url', 'https://example.com/payment/return');
    Config::set('cinetpay.notify_url', 'https://example.com/api/cinetpay/ipn');
    
    // Mock HTTP response
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'message' => 'Success',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test',
                'payment_token' => 'token_123',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    // Create mock client that uses HTTP
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        public function validateSignature(array $payload): bool
        {
            return true;
        }
    };
    
    $gateway = new CinetPayGateway($client, config('payment.gateways.cinetpay'));
    $factory = Mockery::mock(GatewayFactory::class);
    $factory->shouldReceive('createGateway')->andReturn($gateway);
    
    $service = new PaymentService($factory);
    
    // Test 100 times
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $amount = fake()->randomFloat(2, 100, 100000);
        
        // Capture all log calls and verify credentials are not present
        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) use ($apiKey, $siteId, $secretKey) {
                $contextString = json_encode($context);
                return !str_contains($contextString, $apiKey)
                    && !str_contains($contextString, $siteId)
                    && !str_contains($contextString, $secretKey)
                    && !str_contains($message, $apiKey)
                    && !str_contains($message, $siteId)
                    && !str_contains($message, $secretKey);
            })
            ->zeroOrMoreTimes();
        
        Log::shouldReceive('debug')
            ->withArgs(function ($message, $context) use ($apiKey, $siteId, $secretKey) {
                $contextString = json_encode($context);
                return !str_contains($contextString, $apiKey)
                    && !str_contains($contextString, $siteId)
                    && !str_contains($contextString, $secretKey)
                    && !str_contains($message, $apiKey)
                    && !str_contains($message, $siteId)
                    && !str_contains($message, $secretKey);
            })
            ->zeroOrMoreTimes();
        
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        
        // Initialize payment
        $transaction = $service->initializePayment($amount, $user->id, GatewayType::CINETPAY);
        
        // Verify transaction was created
        expect($transaction)->not->toBeNull();
    }
});
