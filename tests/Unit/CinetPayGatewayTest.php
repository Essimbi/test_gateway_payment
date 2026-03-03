<?php

use App\Services\Payment\CinetPayClient;
use App\Services\Payment\CinetPayGateway;
use App\GatewayType;
use App\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Configure CinetPay for tests
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    Config::set('cinetpay.currency', 'XOF');
});

test('CinetPayGateway returns correct gateway name', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    expect($gateway->getGatewayName())->toBe('CinetPay');
});

test('CinetPayGateway returns correct gateway type', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    expect($gateway->getGatewayType())->toBe(GatewayType::CINETPAY);
});

test('CinetPayGateway can initialize payment', function () {
    // Mock HTTP response
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'message' => 'Success',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test123',
                'payment_token' => 'token_abc123',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $result = $gateway->initializePayment([
        'transaction_id' => 'TXN_123',
        'amount' => 1000,
        'currency' => 'XOF',
        'return_url' => 'https://example.com/return',
        'notify_url' => 'https://example.com/notify',
        'description' => 'Test payment',
    ]);
    
    expect($result)->toHaveKey('payment_url')
        ->and($result)->toHaveKey('payment_id')
        ->and($result['payment_url'])->toBe('https://checkout.cinetpay.com/payment/test123');
});

test('CinetPayGateway can verify transaction with ACCEPTED status', function () {
    // Mock HTTP response
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
    
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $result = $gateway->verifyTransaction('TXN_123');
    
    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('transaction_id')
        ->and($result['status'])->toBe(PaymentStatus::ACCEPTED)
        ->and($result['transaction_id'])->toBe('TXN_123')
        ->and($result['amount'])->toBe(1000);
});

test('CinetPayGateway can verify transaction with REFUSED status', function () {
    // Mock HTTP response
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'REFUSED',
                'payment_method' => 'ORANGE_MONEY',
                'operator_id' => 'OP123',
                'amount' => 1000,
            ]
        ], 200)
    ]);
    
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $result = $gateway->verifyTransaction('TXN_123');
    
    expect($result['status'])->toBe(PaymentStatus::REFUSED);
});

test('CinetPayGateway can verify transaction with PENDING status', function () {
    // Mock HTTP response
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'PENDING',
                'payment_method' => 'ORANGE_MONEY',
                'operator_id' => 'OP123',
                'amount' => 1000,
            ]
        ], 200)
    ]);
    
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $result = $gateway->verifyTransaction('TXN_123');
    
    expect($result['status'])->toBe(PaymentStatus::PENDING);
});

test('CinetPayGateway can handle callback', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
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
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $payload = [
        'cpm_trans_id' => 'TXN_123',
        'cpm_amount' => 1000,
        'cpm_payment_status' => 'ACCEPTED',
    ];
    
    $result = $gateway->handleCallback($payload);
    
    expect($result)->toHaveKey('transaction_id')
        ->and($result)->toHaveKey('status')
        ->and($result)->toHaveKey('verified')
        ->and($result['transaction_id'])->toBe('TXN_123')
        ->and($result['status'])->toBe(PaymentStatus::ACCEPTED)
        ->and($result['verified'])->toBeTrue();
});

test('CinetPayGateway validates callback signature', function () {
    Log::shouldReceive('debug')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
        
        public function validateSignature(array $payload): bool
        {
            return isset($payload['cpm_trans_id']);
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    $validPayload = ['cpm_trans_id' => 'TXN_123'];
    $invalidPayload = [];
    
    expect($gateway->validateCallback($validPayload))->toBeTrue()
        ->and($gateway->validateCallback($invalidPayload))->toBeFalse();
});

test('CinetPayGateway maps ACCEPTED status variations correctly', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    // Test ACCEPTED variations
    $acceptedStatuses = ['ACCEPTED', 'SUCCESSFUL', 'SUCCESS'];
    foreach ($acceptedStatuses as $status) {
        Http::fake([
            '*' => Http::response([
                'code' => '00',
                'data' => ['status' => $status, 'amount' => 1000]
            ], 200)
        ]);
        
        $result = $gateway->verifyTransaction('TXN_123');
        expect($result['status'])->toBe(PaymentStatus::ACCEPTED);
    }
});

test('CinetPayGateway maps REFUSED status variations correctly', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    // Test REFUSED variations
    $refusedStatuses = ['REFUSED', 'FAILED', 'CANCELLED'];
    foreach ($refusedStatuses as $status) {
        Http::fake([
            '*' => Http::response([
                'code' => '00',
                'data' => ['status' => $status, 'amount' => 1000]
            ], 200)
        ]);
        
        $result = $gateway->verifyTransaction('TXN_123');
        expect($result['status'])->toBe(PaymentStatus::REFUSED);
    }
});

test('CinetPayGateway maps unknown status to PENDING', function () {
    Log::shouldReceive('debug')->andReturn(null);
    Log::shouldReceive('info')->andReturn(null);
    
    $client = new class extends CinetPayClient {
        protected function initializeSdk(): void
        {
            $this->useSdk = false;
        }
    };
    
    $gateway = new CinetPayGateway($client, ['site_id' => 'test_site_id']);
    
    Http::fake([
        '*' => Http::response([
            'code' => '00',
            'data' => ['status' => 'UNKNOWN_STATUS', 'amount' => 1000]
        ], 200)
    ]);
    
    $result = $gateway->verifyTransaction('TXN_123');
    expect($result['status'])->toBe(PaymentStatus::PENDING);
});
