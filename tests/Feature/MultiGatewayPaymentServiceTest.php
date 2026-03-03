<?php

use App\Services\Payment\PaymentService;
use App\Services\Payment\GatewayFactory;
use App\Models\User;
use App\Models\Transaction;
use App\GatewayType;
use App\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * Multi-Gateway Payment Service Tests
 * 
 * Tests PaymentService with both CinetPay and Tranzak gateways
 * to ensure the service layer works correctly with multiple gateways.
 */

beforeEach(function () {
    // Configure both gateways
    Config::set('cinetpay.api_key', 'test_cinetpay_api_key');
    Config::set('cinetpay.site_id', 'test_cinetpay_site_id');
    Config::set('cinetpay.secret_key', 'test_cinetpay_secret_key');
    Config::set('cinetpay.currency', 'XOF');
    
    Config::set('payment.gateways.cinetpay', [
        'api_key' => 'test_cinetpay_api_key',
        'site_id' => 'test_cinetpay_site_id',
        'secret_key' => 'test_cinetpay_secret_key',
        'currency' => 'XOF',
    ]);
    
    Config::set('payment.gateways.tranzak', [
        'api_key' => 'test_tranzak_api_key',
        'app_id' => 'test_tranzak_app_id',
        'currency' => 'XAF',
        'base_url' => 'https://dsapi.tranzak.me',
    ]);
    
    // Define routes that will be created in later tasks (task 14)
    \Illuminate\Support\Facades\Route::get('/payment/return/{transactionId}', function () {})
        ->name('payment.return');
    \Illuminate\Support\Facades\Route::post('/api/cinetpay/callback', function () {})
        ->name('payment.callback.cinetpay');
    \Illuminate\Support\Facades\Route::post('/api/tranzak/callback', function () {})
        ->name('payment.callback.tranzak');
});

test('PaymentService can initialize payment with CinetPay gateway', function () {
    // Mock CinetPay API response
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
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    $amount = 1000.00;
    
    $transaction = $service->initializePayment($amount, $user->id, GatewayType::CINETPAY);
    
    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->gateway_type)->toBe(GatewayType::CINETPAY)
        ->and($transaction->amount)->toBe('1000.00')
        ->and($transaction->status)->toBe(PaymentStatus::PENDING)
        ->and($transaction->gateway_payment_id)->toBe('cp_payment_123');
});

test('PaymentService can initialize payment with Tranzak gateway', function () {
    // Mock Tranzak API response (POST /xp021/v1/request/create)
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'data' => [
                'requestId' => 'req_tranzak_123',
                'links' => [
                    'paymentAuthUrl' => 'https://pay.tranzak.me/payment/req_tranzak_123'
                ],
                'status' => 'PENDING',
            ],
            'success' => true,
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    $amount = 2000.00;
    
    $transaction = $service->initializePayment($amount, $user->id, GatewayType::TRANZAK);
    
    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->gateway_type)->toBe(GatewayType::TRANZAK)
        ->and($transaction->amount)->toBe('2000.00')
        ->and($transaction->status)->toBe(PaymentStatus::PENDING)
        ->and($transaction->gateway_payment_id)->toBe('req_tranzak_123')
        ->and($transaction->currency)->toBe('XAF');
});

test('PaymentService logs gateway information when initializing CinetPay payment', function () {
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Transaction initiated' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    $service->initializePayment(1000, $user->id, GatewayType::CINETPAY);
    
    expect($loggedGateway)->toBe('cinetpay');
});

test('PaymentService logs gateway information when initializing Tranzak payment', function () {
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'data' => [
                'requestId' => 'req_123',
                'links' => ['paymentAuthUrl' => 'https://pay.tranzak.me/payment/req_123'],
            ],
            'success' => true,
        ], 200)
    ]);
    
    Log::shouldReceive('error')->zeroOrMoreTimes();
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Transaction initiated' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    $service->initializePayment(2000, $user->id, GatewayType::TRANZAK);
    
    expect($loggedGateway)->toBe('tranzak');
});

test('PaymentService can verify CinetPay transaction status', function () {
    // Create a CinetPay transaction
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock CinetPay status check
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'ACCEPTED',
                'payment_method' => 'ORANGE_MONEY',
                'amount' => $transaction->amount,
            ]
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $status = $service->verifyTransactionStatus($transaction->transaction_id);
    
    expect($status)->toBe(PaymentStatus::ACCEPTED);
});

test('PaymentService can verify Tranzak transaction status', function () {
    // Create a Tranzak transaction with gateway_payment_id (requestId) - required for API call
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::TRANZAK,
        'gateway_payment_id' => 'req_123',
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock Tranzak status check (GET /xp021/v1/request/details)
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_123',
                'status' => 'SUCCESSFUL',
                'amount' => 2000,
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $status = $service->verifyTransactionStatus($transaction->transaction_id);
    
    expect($status)->toBe(PaymentStatus::ACCEPTED);
});

test('PaymentService logs gateway information when verifying CinetPay transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => ['status' => 'ACCEPTED', 'amount' => $transaction->amount]
        ], 200)
    ]);
    
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Verifying transaction status' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $service->verifyTransactionStatus($transaction->transaction_id);
    
    expect($loggedGateway)->toBe('cinetpay');
});

test('PaymentService logs gateway information when verifying Tranzak transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::TRANZAK,
        'gateway_payment_id' => 'req_123',
        'status' => PaymentStatus::PENDING,
    ]);
    
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_123',
                'status' => 'SUCCESSFUL',
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Verifying transaction status' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $service->verifyTransactionStatus($transaction->transaction_id);
    
    expect($loggedGateway)->toBe('tranzak');
});

test('PaymentService can process CinetPay callback', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock verification call
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => ['status' => 'ACCEPTED', 'amount' => $transaction->amount]
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $payload = [
        'cpm_trans_id' => $transaction->transaction_id,
        'cpm_amount' => $transaction->amount,
        'cpm_payment_status' => 'ACCEPTED',
    ];
    
    $result = $service->processCallback(GatewayType::CINETPAY, $payload);
    
    expect($result)->toBeTrue();
    
    $updatedTransaction = Transaction::find($transaction->id);
    expect($updatedTransaction->status)->toBe(PaymentStatus::ACCEPTED);
});

test('PaymentService can process Tranzak callback', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::TRANZAK,
        'gateway_payment_id' => 'req_123',
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock verification call (validateCallback + verifyTransactionStatus)
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_123',
                'status' => 'SUCCESSFUL',
                'mchTransactionRef' => $transaction->transaction_id,
                'amount' => 2000,
            ],
            'success' => true,
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $payload = [
        'request_id' => 'req_123',
        'mchTransactionRef' => $transaction->transaction_id,
        'status' => 'SUCCESSFUL',
        'amount' => 2000,
    ];
    
    $result = $service->processCallback(GatewayType::TRANZAK, $payload);
    
    expect($result)->toBeTrue();
    
    $updatedTransaction = Transaction::find($transaction->id);
    expect($updatedTransaction->status)->toBe(PaymentStatus::ACCEPTED);
});

test('PaymentService logs gateway information when processing CinetPay callback', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => ['status' => 'ACCEPTED', 'amount' => $transaction->amount]
        ], 200)
    ]);
    
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Callback notification received' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $payload = [
        'cpm_trans_id' => $transaction->transaction_id,
        'cpm_amount' => $transaction->amount,
    ];
    
    $service->processCallback(GatewayType::CINETPAY, $payload);
    
    expect($loggedGateway)->toBe('cinetpay');
});

test('PaymentService logs gateway information when processing Tranzak callback', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::TRANZAK,
        'gateway_payment_id' => 'req_123',
        'status' => PaymentStatus::PENDING,
    ]);
    
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_123',
                'status' => 'SUCCESSFUL',
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $loggedGateway = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use (&$loggedGateway) {
            if ($message === 'Callback notification received' && isset($context['gateway'])) {
                $loggedGateway = $context['gateway'];
            }
            return true;
        })
        ->zeroOrMoreTimes();
    
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $payload = [
        'request_id' => 'req_123',
        'mchTransactionRef' => $transaction->transaction_id,
        'status' => 'SUCCESSFUL',
    ];
    
    $service->processCallback(GatewayType::TRANZAK, $payload);
    
    expect($loggedGateway)->toBe('tranzak');
});

test('PaymentService returns available gateways', function () {
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $available = $service->getAvailableGateways();
    
    expect($available)->toBeArray()
        ->and($available)->toContain(GatewayType::CINETPAY)
        ->and($available)->toContain(GatewayType::TRANZAK);
});

test('PaymentService uses correct gateway client based on transaction gateway_type', function () {
    // Create transactions with different gateways
    $user = User::factory()->create();
    
    $cinetpayTransaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    $tranzakTransaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::TRANZAK,
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock both APIs
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => ['status' => 'ACCEPTED', 'amount' => $cinetpayTransaction->amount]
        ], 200),
        'https://dsapi.tranzak.me/v1/payment/status/*' => Http::response([
            'request_id' => 'req_123',
            'status' => 'SUCCESSFUL',
            'mchTransactionRef' => $tranzakTransaction->transaction_id,
        ], 200)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    // Verify CinetPay transaction uses CinetPay client
    $cinetpayStatus = $service->verifyTransactionStatus($cinetpayTransaction->transaction_id);
    expect($cinetpayStatus)->toBe(PaymentStatus::ACCEPTED);
    
    // Verify Tranzak transaction uses Tranzak client
    $tranzakStatus = $service->verifyTransactionStatus($tranzakTransaction->transaction_id);
    expect($tranzakStatus)->toBe(PaymentStatus::ACCEPTED);
});

test('PaymentService handles errors gracefully for CinetPay', function () {
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([], 500)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    
    expect(fn() => $service->initializePayment(1000, $user->id, GatewayType::CINETPAY))
        ->toThrow(\App\Exceptions\Payment\PaymentException::class);
});

test('PaymentService handles errors gracefully for Tranzak', function () {
    Http::fake([
        'https://dsapi.tranzak.me/v1/payment/request' => Http::response([], 500)
    ]);
    
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $user = User::factory()->create();
    
    expect(fn() => $service->initializePayment(2000, $user->id, GatewayType::TRANZAK))
        ->toThrow(\App\Exceptions\Payment\PaymentException::class);
});
