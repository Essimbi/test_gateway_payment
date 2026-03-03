<?php

use App\Services\Payment\CinetPayClient;
use App\Exceptions\Payment\CinetPayApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

// Feature: cinetpay-payment-integration, Property 11: API Call Uses Correct Transaction ID
test('API calls use the correct transaction ID for verification', function () {
    // Configure CinetPay credentials
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.timeout', 30);
    Config::set('cinetpay.retry_attempts', 1); // Reduce retries for testing
    
    // Mock HTTP response before creating client
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
    
    // Test 10 times with random transaction IDs
    for ($i = 0; $i < 10; $i++) {
        $transactionId = 'TXN_' . fake()->uuid();
        
        // Create a mock client that uses HTTP instead of SDK
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                // Force HTTP client usage by not initializing SDK
                $this->useSdk = false;
            }
        };
        
        $result = $client->checkTransactionStatus($transactionId);
        
        // Verify the HTTP request was made with the correct transaction ID
        Http::assertSent(function ($request) use ($transactionId) {
            $body = $request->data();
            return $request->url() === 'https://api-checkout.cinetpay.com/v2/payment/check'
                && isset($body['transaction_id'])
                && $body['transaction_id'] === $transactionId;
        });
        
        // Verify result is returned
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ACCEPTED');
    }
});


// Feature: cinetpay-payment-integration, Property 27: Network Timeout Retry
test('network timeout triggers exactly 3 retry attempts', function () {
    // Configure CinetPay credentials
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.timeout', 30);
    Config::set('cinetpay.retry_attempts', 3);
    Config::set('cinetpay.retry_delays', [1, 2, 4]);
    
    // Test 10 times to ensure consistency
    for ($i = 0; $i < 10; $i++) {
        $transactionId = 'TXN_' . fake()->uuid();
        $attemptCount = 0;
        
        // Mock HTTP to throw timeout exception
        Http::fake(function () use (&$attemptCount) {
            $attemptCount++;
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });
        
        // Create a mock client that uses HTTP instead of SDK
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                // Force HTTP client usage by not initializing SDK
                $this->useSdk = false;
            }
        };
        
        try {
            $client->checkTransactionStatus($transactionId);
            // Should not reach here
            expect(false)->toBeTrue('Expected CinetPayApiException to be thrown');
        } catch (CinetPayApiException $e) {
            // Verify exactly 3 attempts were made
            expect($attemptCount)->toBe(3)
                ->and($e->getMessage())->toContain('Failed to check transaction status');
        }
    }
});


// Feature: cinetpay-payment-integration, Property 23: SDK Exception Conversion
test('SDK exceptions are converted to application-specific exceptions', function () {
    // Configure CinetPay credentials
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.timeout', 30);
    Config::set('cinetpay.retry_attempts', 1);
    
    // Test 10 times with different exception types
    for ($i = 0; $i < 10; $i++) {
        $transactionId = 'TXN_' . fake()->uuid();
        
        // Mock HTTP to throw various exceptions
        $exceptionTypes = [
            new \Illuminate\Http\Client\ConnectionException('Connection failed'),
            new \Illuminate\Http\Client\RequestException(
                new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(500, [], 'Server error')
                )
            ),
            new \Exception('Generic exception'),
        ];
        
        $exception = $exceptionTypes[$i % count($exceptionTypes)];
        
        Http::fake(function () use ($exception) {
            throw $exception;
        });
        
        // Create a mock client that uses HTTP instead of SDK
        $client = new class extends CinetPayClient {
            protected function initializeSdk(): void
            {
                // Force HTTP client usage by not initializing SDK
                $this->useSdk = false;
            }
        };
        
        try {
            $client->checkTransactionStatus($transactionId);
            // Should not reach here
            expect(false)->toBeTrue('Expected CinetPayApiException to be thrown');
        } catch (CinetPayApiException $e) {
            // Verify exception was converted to CinetPayApiException
            expect($e)->toBeInstanceOf(CinetPayApiException::class)
                ->and($e->getMessage())->toContain('Failed to check transaction status');
        } catch (\Exception $e) {
            // Any other exception type is a failure
            expect(false)->toBeTrue('Expected CinetPayApiException but got ' . get_class($e));
        }
    }
});
