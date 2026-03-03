<?php

use App\Services\Payment\TranzakClient;
use App\Services\Payment\TranzakGateway;
use App\GatewayType;
use App\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

test('TranzakGateway returns correct gateway name', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    expect($gateway->getGatewayName())->toBe('Tranzak');
});

test('TranzakGateway returns correct gateway type', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    expect($gateway->getGatewayType())->toBe(GatewayType::TRANZAK);
});

test('TranzakGateway can be instantiated', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    expect($gateway)->toBeInstanceOf(TranzakGateway::class);
});

test('TranzakGateway can initialize payment', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    // Mock successful HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'request_id' => 'req_123456',
            'links' => [
                'payment_url' => 'https://pay.tranzak.me/payment/req_123456'
            ],
            'status' => 'PENDING',
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $result = $gateway->initializePayment([
        'transaction_id' => 'TXN_123',
        'amount' => 1000,
        'currency' => 'XAF',
        'return_url' => 'https://example.com/return',
        'notify_url' => 'https://example.com/notify',
        'description' => 'Test payment',
    ]);
    
    expect($result)->toHaveKey('payment_url')
        ->and($result)->toHaveKey('payment_id')
        ->and($result['payment_url'])->toBe('https://pay.tranzak.me/payment/req_123456')
        ->and($result['payment_id'])->toBe('req_123456');
});

test('TranzakGateway can verify transaction with SUCCESSFUL status', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock successful HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/req_123456' => Http::response([
            'request_id' => 'req_123456',
            'status' => 'SUCCESSFUL',
            'amount' => 1000,
            'currencyCode' => 'XAF',
            'mchTransactionRef' => 'TXN_123',
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $result = $gateway->verifyTransaction('req_123456');
    
    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('transaction_id')
        ->and($result['status'])->toBe(PaymentStatus::ACCEPTED)
        ->and($result['transaction_id'])->toBe('TXN_123')
        ->and($result['amount'])->toBe(1000);
});

test('TranzakGateway can verify transaction with FAILED status', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock failed HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/req_123456' => Http::response([
            'request_id' => 'req_123456',
            'status' => 'FAILED',
            'amount' => 1000,
            'currencyCode' => 'XAF',
            'mchTransactionRef' => 'TXN_123',
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $result = $gateway->verifyTransaction('req_123456');
    
    expect($result['status'])->toBe(PaymentStatus::REFUSED);
});

test('TranzakGateway can verify transaction with PENDING status', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock pending HTTP response
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/req_123456' => Http::response([
            'request_id' => 'req_123456',
            'status' => 'PENDING',
            'amount' => 1000,
            'currencyCode' => 'XAF',
            'mchTransactionRef' => 'TXN_123',
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $result = $gateway->verifyTransaction('req_123456');
    
    expect($result['status'])->toBe(PaymentStatus::PENDING);
});

test('TranzakGateway can handle callback with mchTransactionRef', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    // Create a transaction in the database
    $transaction = \App\Models\Transaction::factory()->create([
        'transaction_id' => 'TXN_123',
        'gateway_type' => \App\GatewayType::TRANZAK,
        'status' => \App\PaymentStatus::PENDING,
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $payload = [
        'request_id' => 'req_123456',
        'mchTransactionRef' => 'TXN_123',
        'status' => 'SUCCESSFUL',
        'amount' => 1000,
    ];
    
    $result = $gateway->handleCallback($payload);
    
    expect($result)->toHaveKey('transaction_id')
        ->and($result)->toHaveKey('status')
        ->and($result)->toHaveKey('verified')
        ->and($result['transaction_id'])->toBe('TXN_123')
        ->and($result['status'])->toBe(PaymentStatus::ACCEPTED)
        ->and($result['verified'])->toBeTrue();
});

test('TranzakGateway can handle callback with only request_id', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    // Create a transaction in the database with gateway_payment_id
    $transaction = \App\Models\Transaction::factory()->create([
        'transaction_id' => 'TXN_456',
        'gateway_payment_id' => 'req_123456',
        'gateway_type' => \App\GatewayType::TRANZAK,
        'status' => \App\PaymentStatus::PENDING,
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $payload = [
        'request_id' => 'req_123456',
        'status' => 'SUCCESSFUL',
        'amount' => 1000,
    ];
    
    $result = $gateway->handleCallback($payload);
    
    expect($result)->toHaveKey('transaction_id')
        ->and($result['transaction_id'])->toBe('req_123456');
});

test('TranzakGateway validates callback with request_id', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    // Mock successful HTTP response for validation
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/req_123456' => Http::response([
            'request_id' => 'req_123456',
            'status' => 'SUCCESSFUL',
        ], 200)
    ]);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $validPayload = ['request_id' => 'req_123456', 'status' => 'SUCCESSFUL'];
    
    expect($gateway->validateCallback($validPayload))->toBeTrue();
});

test('TranzakGateway rejects callback without identifiers', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('warning')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    $invalidPayload = ['status' => 'SUCCESSFUL'];
    
    expect($gateway->validateCallback($invalidPayload))->toBeFalse();
});

test('TranzakGateway maps ACCEPTED status variations correctly', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    // Test ACCEPTED variations
    $acceptedStatuses = ['SUCCESSFUL', 'SUCCESS', 'COMPLETED'];
    foreach ($acceptedStatuses as $status) {
        Http::fake([
            'https://dsapi.tranzak.me/auth/token' => Http::response([
                'data' => ['token' => 'mocked_token']
            ], 200),
            '*' => Http::response([
                'request_id' => 'req_123',
                'status' => $status,
                'amount' => 1000,
            ], 200)
        ]);
        
        $result = $gateway->verifyTransaction('req_123');
        expect($result['status'])->toBe(PaymentStatus::ACCEPTED);
    }
});

test('TranzakGateway maps REFUSED status variations correctly', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    // Test REFUSED variations
    $refusedStatuses = ['FAILED', 'CANCELLED', 'CANCELED', 'REJECTED'];
    foreach ($refusedStatuses as $status) {
        Http::fake([
            'https://dsapi.tranzak.me/auth/token' => Http::response([
                'data' => ['token' => 'mocked_token']
            ], 200),
            '*' => Http::response([
                'request_id' => 'req_123',
                'status' => $status,
                'amount' => 1000,
            ], 200)
        ]);
        
        $result = $gateway->verifyTransaction('req_123');
        expect($result['status'])->toBe(PaymentStatus::REFUSED);
    }
});

test('TranzakGateway maps PENDING status variations correctly', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    // Test PENDING variations
    $pendingStatuses = ['PENDING', 'PROCESSING', 'INITIATED'];
    foreach ($pendingStatuses as $status) {
        Http::fake([
            'https://dsapi.tranzak.me/auth/token' => Http::response([
                'data' => ['token' => 'mocked_token']
            ], 200),
            '*' => Http::response([
                'request_id' => 'req_123',
                'status' => $status,
                'amount' => 1000,
            ], 200)
        ]);
        
        $result = $gateway->verifyTransaction('req_123');
        expect($result['status'])->toBe(PaymentStatus::PENDING);
    }
});

test('TranzakGateway maps unknown status to PENDING', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new TranzakClient('test_api_key', 'test_app_id');
    $gateway = new TranzakGateway($client, ['app_id' => 'test_app_id']);
    
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        '*' => Http::response([
            'request_id' => 'req_123',
            'status' => 'UNKNOWN_STATUS',
            'amount' => 1000,
        ], 200)
    ]);
    
    $result = $gateway->verifyTransaction('req_123');
    expect($result['status'])->toBe(PaymentStatus::PENDING);
});
