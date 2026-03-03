<?php

use App\Services\Payment\TranzakClient;
use App\Exceptions\Payment\PaymentConfigurationException;
use App\Exceptions\Payment\TranzakApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

test('TranzakClient can be instantiated with valid credentials', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient(
        'test_api_key',
        'test_app_id',
        'https://dsapi.tranzak.me'
    );
    
    expect($client)->toBeInstanceOf(TranzakClient::class);
});

test('TranzakClient throws exception with missing API key', function () {
    new TranzakClient('', 'test_app_id');
})->throws(PaymentConfigurationException::class);

test('TranzakClient throws exception with missing App ID', function () {
    new TranzakClient('test_api_key', '');
})->throws(PaymentConfigurationException::class);

test('TranzakClient can create payment successfully', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock successful HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'data' => [
                'requestId' => 'req_123456',
                'links' => [
                    'paymentAuthUrl' => 'https://pay.tranzak.me/payment/req_123456'
                ],
                'status' => 'PENDING',
            ],
            'success' => true
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $result = $client->createPayment([
        'amount' => 1000,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'return_url' => 'https://example.com/return',
        'cancel_url' => 'https://example.com/cancel',
        'callback_url' => 'https://example.com/callback',
        'app_id' => 'test_app_id',
    ]);
    
    expect($result)->toHaveKey('requestId')
        ->and($result)->toHaveKey('links')
        ->and($result['links'])->toHaveKey('paymentAuthUrl')
        ->and($result['requestId'])->toBe('req_123456');
});

test('TranzakClient throws exception on failed payment creation', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('error')->andReturn(null);
    
    // Mock failed HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'message' => 'Invalid amount',
        ], 400)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $client->createPayment([
        'amount' => -100,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'return_url' => 'https://example.com/return',
        'callback_url' => 'https://example.com/callback',
        'app_id' => 'test_app_id',
    ]);
})->throws(TranzakApiException::class);

test('TranzakClient can get payment status successfully', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock successful HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details?requestId=req_123456' => Http::response([
            'data' => [
                'requestId' => 'req_123456',
                'status' => 'SUCCESSFUL',
                'amount' => 1000,
                'currencyCode' => 'XAF',
                'mchTransactionRef' => 'TXN_123',
            ],
            'success' => true
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $result = $client->getPaymentStatus('req_123456');
    
    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('requestId')
        ->and($result['status'])->toBe('SUCCESSFUL')
        ->and($result['requestId'])->toBe('req_123456');
});

test('TranzakClient throws exception on failed status check', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('error')->andReturn(null);
    
    // Mock failed HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/invalid_id' => Http::response([
            'message' => 'Payment not found',
        ], 404)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $client->getPaymentStatus('invalid_id');
})->throws(TranzakApiException::class);

test('TranzakClient retries on transient failures', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('warning')->andReturn(null);
    
    $attemptCount = 0;
    
    // Mock HTTP to fail twice then succeed (for both auth and payment)
    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$attemptCount) {
        if ($request->url() === 'https://dsapi.tranzak.me/auth/token') {
            return Http::response(['data' => ['token' => 'mocked_token']], 200);
        }
        
        $attemptCount++;
        
        if ($attemptCount < 3) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        }
        
        return Http::response([
            'data' => [
                'requestId' => 'req_123456',
                'links' => [
                    'paymentAuthUrl' => 'https://pay.tranzak.me/payment/req_123456'
                ],
                'status' => 'PENDING',
            ],
            'success' => true
        ], 200);
    });
    
    $client = new TranzakClient(
        'test_api_key',
        'test_app_id',
        'https://dsapi.tranzak.me',
        30,
        3,
        [0, 0, 0] // No delay for faster test
    );
    
    $result = $client->createPayment([
        'amount' => 1000,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'return_url' => 'https://example.com/return',
        'callback_url' => 'https://example.com/callback',
        'app_id' => 'test_app_id',
    ]);
    
    expect($result)->toHaveKey('requestId')
        ->and($attemptCount)->toBe(3);
});

test('TranzakClient throws exception after max retries', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('warning')->andReturn(null);
    Log::shouldReceive('error')->andReturn(null);
    
    // Mock HTTP to always fail
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if ($request->url() === 'https://dsapi.tranzak.me/auth/token') {
            return Http::response(['data' => ['token' => 'mocked_token']], 200);
        }
        throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
    });
    
    $client = new TranzakClient(
        'test_api_key',
        'test_app_id',
        'https://dsapi.tranzak.me',
        30,
        2,
        [0, 0] // No delay for faster test
    );
    
    $client->createPayment([
        'amount' => 1000,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'return_url' => 'https://example.com/return',
        'callback_url' => 'https://example.com/callback',
        'app_id' => 'test_app_id',
    ]);
})->throws(TranzakApiException::class);

test('TranzakClient validates response structure for payment creation', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('error')->andReturn(null);
    
    // Mock response with missing required fields
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'data' => [
                'requestId' => 'req_123456',
                // Missing 'links' field
            ],
            'success' => true
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $client->createPayment([
        'amount' => 1000,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'return_url' => 'https://example.com/return',
        'callback_url' => 'https://example.com/callback',
        'app_id' => 'test_app_id',
    ]);
})->throws(TranzakApiException::class);

test('TranzakClient validates response structure for status check', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('error')->andReturn(null);
    
    // Mock response with missing required fields
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details?requestId=req_123456' => Http::response([
            'data' => [
                'requestId' => 'req_123456',
                // Missing 'status' field
            ],
            'success' => true
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    
    $client->getPaymentStatus('req_123456');
})->throws(TranzakApiException::class);
