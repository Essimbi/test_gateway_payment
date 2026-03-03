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
 * Complete Payment Flow Integration Tests
 * 
 * Tests complete end-to-end payment flows for both CinetPay and Tranzak gateways.
 * Validates: Task 16 - Checkpoint testing complete payment flows
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
    
    // Force refresh GatewayFactory with the new config
    app()->forgetInstance(GatewayFactory::class);
    
    // Allow all log calls
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
});

/**
 * Test complete CinetPay payment flow
 * Flow: selection → payment → callback → verification
 */
test('complete CinetPay payment flow from selection to verification', function () {
    $user = User::factory()->create();
    $amount = 5000.00;
    
    // Step 1: Gateway Selection
    $response = $this->actingAs($user)->get(route('payment.select-gateway', [
        'amount' => $amount,
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.select-gateway');
    $response->assertViewHas('availableGateways');
    $response->assertSee('CinetPay');
    
    // Step 2: Payment Summary
    $response = $this->actingAs($user)->get(route('payment.summary', [
        'amount' => $amount,
        'gateway_type' => 'cinetpay',
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.summary');
    $response->assertViewHas('gateway');
    $response->assertViewHas('gatewayName', 'CinetPay');
    
    // Step 3: Initialize Payment
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'message' => 'Success',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test123',
                'payment_token' => 'token_123',
                'payment_id' => 'cp_payment_123',
            ]
        ], 200)
    ]);
    
    $response = $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => $amount,
        'gateway_type' => 'cinetpay',
        'metadata' => [
            'description' => 'Test payment',
        ],
    ]);
    
    $response->assertRedirect();
    $response->assertRedirectContains('checkout.cinetpay.com');
    
    // Verify transaction was created with correct gateway type
    $transaction = Transaction::where('user_id', $user->id)
        ->where('gateway_type', GatewayType::CINETPAY)
        ->latest()
        ->first();
    
    expect($transaction)->not->toBeNull()
        ->and($transaction->gateway_type)->toBe(GatewayType::CINETPAY)
        ->and($transaction->status)->toBe(PaymentStatus::PENDING)
        ->and($transaction->amount)->toBe('5000.00')
        ->and($transaction->gateway_payment_id)->toBe('cp_payment_123');
    
    // Step 4: Callback from CinetPay
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'ACCEPTED',
                'payment_method' => 'ORANGE_MONEY',
                'amount' => $amount,
            ]
        ], 200)
    ]);
    
    $callbackPayload = [
        'cpm_trans_id' => $transaction->transaction_id,
        'cpm_amount' => $amount,
        'cpm_payment_status' => 'ACCEPTED',
        'signature' => hash('sha256', $transaction->transaction_id . $amount . 'test_cinetpay_secret_key'),
    ];
    
    $response = $this->postJson(route('payment.callback.cinetpay'), $callbackPayload);
    
    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);
    
    // Step 5: Verification - Check transaction status was updated
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::ACCEPTED);
    
    // Step 6: User Return - Verify user sees success page
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'ACCEPTED',
                'amount' => $amount,
            ]
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.success');
    $response->assertViewHas('transaction');
    $response->assertViewHas('gatewayName', 'CinetPay');
    $response->assertSee($transaction->transaction_id);
    $response->assertSee(number_format($amount, 0, ',', ' ') . ' FCFA');
});

/**
 * Test complete Tranzak payment flow
 * Flow: selection → payment → callback → verification
 */
test('complete Tranzak payment flow from selection to verification', function () {
    $user = User::factory()->create();
    $amount = 10000.00;
    
    // Step 1: Gateway Selection
    $response = $this->actingAs($user)->get(route('payment.select-gateway', [
        'amount' => $amount,
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.select-gateway');
    $response->assertViewHas('availableGateways');
    $response->assertSee('Tranzak');
    
    // Step 2: Payment Summary
    $response = $this->actingAs($user)->get(route('payment.summary', [
        'amount' => $amount,
        'gateway_type' => 'tranzak',
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.summary');
    $response->assertViewHas('gateway');
    $response->assertViewHas('gatewayName', 'Tranzak');
    
    // Step 3: Initialize Payment
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'request_id' => 'req_tranzak_456',
            'links' => [
                'payment_url' => 'https://pay.tranzak.me/payment/req_tranzak_456'
            ],
            'status' => 'PENDING',
        ], 200)
    ]);
    
    $response = $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => $amount,
        'gateway_type' => 'tranzak',
        'metadata' => [
            'description' => 'Test Tranzak payment',
        ],
    ]);
    
    $response->assertRedirect();
    $response->assertRedirectContains('pay.tranzak.me');
    
    // Verify transaction was created with correct gateway type
    $transaction = Transaction::where('user_id', $user->id)
        ->where('gateway_type', GatewayType::TRANZAK)
        ->latest()
        ->first();
    
    expect($transaction)->not->toBeNull()
        ->and($transaction->gateway_type)->toBe(GatewayType::TRANZAK)
        ->and($transaction->status)->toBe(PaymentStatus::PENDING)
        ->and($transaction->amount)->toBe('10000.00')
        ->and($transaction->gateway_payment_id)->toBe('req_tranzak_456')
        ->and($transaction->currency)->toBe('XAF');
    
    // Step 4: Callback from Tranzak (validateCallback + verifyTransactionStatus use xp021/v1/request/details)
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_tranzak_456',
                'status' => 'SUCCESSFUL',
                'amount' => $amount,
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $callbackPayload = [
        'request_id' => 'req_tranzak_456',
        'mchTransactionRef' => $transaction->transaction_id,
        'status' => 'SUCCESSFUL',
        'amount' => $amount,
    ];
    
    $response = $this->postJson(route('payment.callback.tranzak'), $callbackPayload);
    
    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);
    
    // Step 5: Verification - Check transaction status was updated
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::ACCEPTED);
    
    // Step 6: User Return - Verify user sees success page
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_tranzak_456',
                'status' => 'SUCCESSFUL',
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.success');
    $response->assertViewHas('transaction');
    $response->assertViewHas('gatewayName', 'Tranzak');
    $response->assertSee($transaction->transaction_id);
    $response->assertSee(number_format($amount, 0, ',', ' '));
});

/**
 * Test backward compatibility with existing CinetPay transactions
 * Ensures existing CinetPay transactions still work after multi-gateway changes
 */
test('backward compatibility with existing CinetPay transactions', function () {
    $user = User::factory()->create();
    
    // Create an "existing" CinetPay transaction (simulating pre-migration data)
    $existingTransaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
        'amount' => 3000.00,
        'currency' => 'XOF',
    ]);
    
    // Verify the transaction has CinetPay gateway type
    expect($existingTransaction->gateway_type)->toBe(GatewayType::CINETPAY);
    
    // Test 1: Verify transaction status using CinetPay gateway
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'message' => 'Success',
            'data' => [
                'status' => 'ACCEPTED',
                'payment_method' => 'MTN_MONEY',
                'amount' => 3000.00,
            ]
        ], 200)
    ]);
    
    $config = config('payment.gateways');
    $factory = new GatewayFactory($config);
    $service = new PaymentService($factory);
    
    $status = $service->verifyTransactionStatus($existingTransaction->transaction_id);
    
    expect($status)->toBe(PaymentStatus::ACCEPTED);
    
    // Test 2: Process callback for existing transaction
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'ACCEPTED',
                'amount' => 3000.00,
            ]
        ], 200)
    ]);
    
    $callbackPayload = [
        'cpm_trans_id' => $existingTransaction->transaction_id,
        'cpm_amount' => $existingTransaction->amount,
        'cpm_payment_status' => 'ACCEPTED',
        'signature' => hash('sha256', $existingTransaction->transaction_id . $existingTransaction->amount . 'test_cinetpay_secret_key'),
    ];
    
    $result = $service->processCallback(GatewayType::CINETPAY, $callbackPayload);
    
    expect($result)->toBeTrue();
    
    $existingTransaction->refresh();
    expect($existingTransaction->status)->toBe(PaymentStatus::ACCEPTED);
    
    // Test 3: User return for existing transaction
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'ACCEPTED',
                'amount' => 3000.00,
            ]
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', [
        'transactionId' => $existingTransaction->transaction_id
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.success');
    $response->assertViewHas('gatewayName', 'CinetPay');
    
    // Test 4: Legacy IPN endpoint still works
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'ACCEPTED',
                'amount' => 3000.00,
            ]
        ], 200)
    ]);
    
    $ipnPayload = [
        'cpm_trans_id' => $existingTransaction->transaction_id,
        'cpm_amount' => 3000.00,
    ];
    
    $response = $this->postJson(route('cinetpay.ipn'), $ipnPayload);
    
    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);
});

/**
 * Test failed payment flow for CinetPay
 */
test('complete CinetPay failed payment flow', function () {
    $user = User::factory()->create();
    $amount = 2000.00;
    
    // Initialize payment
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test',
                'payment_id' => 'cp_payment_fail',
            ]
        ], 200)
    ]);
    
    $response = $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => $amount,
        'gateway_type' => 'cinetpay',
    ]);
    
    $transaction = Transaction::where('user_id', $user->id)->latest()->first();
    
    // Simulate failed payment callback
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'REFUSED',
                'amount' => $amount,
            ]
        ], 200)
    ]);
    
    $callbackPayload = [
        'cpm_trans_id' => $transaction->transaction_id,
        'cpm_amount' => $amount,
        'cpm_payment_status' => 'REFUSED',
        'signature' => hash('sha256', $transaction->transaction_id . $amount . 'test_cinetpay_secret_key'),
    ];
    
    $this->postJson(route('payment.callback.cinetpay'), $callbackPayload);
    
    // Verify transaction status is REFUSED
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::REFUSED);
    
    // User return shows failure page
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'REFUSED',
                'amount' => $amount,
            ]
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.failure');
    $response->assertViewHas('gatewayName', 'CinetPay');
});

/**
 * Test failed payment flow for Tranzak
 */
test('complete Tranzak failed payment flow', function () {
    $user = User::factory()->create();
    $amount = 5000.00;
    
    // Initialize payment
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'request_id' => 'req_fail',
            'links' => ['payment_url' => 'https://pay.tranzak.me/payment/req_fail'],
        ], 200)
    ]);
    
    $response = $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => $amount,
        'gateway_type' => 'tranzak',
    ]);
    
    $transaction = Transaction::where('user_id', $user->id)->latest()->first();
    
    // Simulate failed payment callback
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_fail',
                'status' => 'FAILED',
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $callbackPayload = [
        'request_id' => 'req_fail',
        'mchTransactionRef' => $transaction->transaction_id,
        'status' => 'FAILED',
    ];
    
    $this->postJson(route('payment.callback.tranzak'), $callbackPayload);
    
    // Verify transaction status is REFUSED
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::REFUSED);
    
    // User return shows failure page
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/details*' => Http::response([
            'data' => [
                'requestId' => 'req_fail',
                'status' => 'FAILED',
                'mchTransactionRef' => $transaction->transaction_id,
            ],
            'success' => true,
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.failure');
    $response->assertViewHas('gatewayName', 'Tranzak');
});

/**
 * Test pending payment flow (payment not yet completed)
 */
test('pending payment flow shows pending page', function () {
    $user = User::factory()->create();
    
    // Create pending transaction
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'gateway_type' => GatewayType::CINETPAY,
        'status' => PaymentStatus::PENDING,
    ]);
    
    // Mock verification returns PENDING
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
            'code' => '00',
            'data' => [
                'status' => 'PENDING',
                'amount' => $transaction->amount,
            ]
        ], 200)
    ]);
    
    $response = $this->get(route('payment.return', ['transactionId' => $transaction->transaction_id]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.pending');
    $response->assertViewHas('gatewayName', 'CinetPay');
    
    // Verify transaction status is still PENDING
    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::PENDING);
});

/**
 * Test gateway selection shows both gateways when both are configured
 */
test('gateway selection shows both CinetPay and Tranzak when both configured', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get(route('payment.select-gateway', [
        'amount' => 1000,
    ]));
    
    $response->assertStatus(200);
    $response->assertViewIs('payment.select-gateway');
    $response->assertSee('CinetPay');
    $response->assertSee('Tranzak');
    
    $availableGateways = $response->viewData('availableGateways');
    expect($availableGateways)->toContain(GatewayType::CINETPAY)
        ->and($availableGateways)->toContain(GatewayType::TRANZAK);
});

/**
 * Test invalid gateway type is rejected
 */
test('invalid gateway type is rejected during payment initiation', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => 1000,
        'gateway_type' => 'invalid_gateway',
    ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('error');
    
    // Verify no transaction was created
    $count = Transaction::where('user_id', $user->id)->count();
    expect($count)->toBe(0);
});

/**
 * Test malformed callback is handled gracefully
 */
test('malformed Tranzak callback is handled gracefully', function () {
    // Test with empty payload
    $response = $this->postJson(route('payment.callback.tranzak'), []);
    
    $response->assertStatus(200);
    $response->assertJson(['status' => 'error']);
    
    // Test with invalid payload
    $response = $this->postJson(route('payment.callback.tranzak'), [
        'invalid_key' => 'invalid_value',
    ]);
    
    $response->assertStatus(200);
});

/**
 * Test concurrent transactions with different gateways
 */
test('can handle concurrent transactions with different gateways', function () {
    $user = User::factory()->create();
    
    // Create CinetPay transaction
    Http::fake([
        'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
            'code' => '201',
            'data' => [
                'payment_url' => 'https://checkout.cinetpay.com/payment/test1',
                'payment_id' => 'cp_1',
            ]
        ], 200)
    ]);
    
    $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => 1000,
        'gateway_type' => 'cinetpay',
    ]);
    
    // Create Tranzak transaction
    Http::fake([
        'https://dsapi.tranzak.me/auth/token' => Http::response([
            'data' => ['token' => 'mocked_token']
        ], 200),
        'https://dsapi.tranzak.me/xp021/v1/request/create' => Http::response([
            'request_id' => 'req_1',
            'links' => ['payment_url' => 'https://pay.tranzak.me/payment/req_1'],
        ], 200)
    ]);
    
    $this->actingAs($user)->post(route('payment.initiate'), [
        'amount' => 2000,
        'gateway_type' => 'tranzak',
    ]);
    
    // Verify both transactions exist with correct gateway types
    $cinetpayTx = Transaction::where('user_id', $user->id)
        ->where('gateway_type', GatewayType::CINETPAY)
        ->first();
    
    $tranzakTx = Transaction::where('user_id', $user->id)
        ->where('gateway_type', GatewayType::TRANZAK)
        ->first();
    
    expect($cinetpayTx)->not->toBeNull()
        ->and($cinetpayTx->gateway_type)->toBe(GatewayType::CINETPAY)
        ->and($tranzakTx)->not->toBeNull()
        ->and($tranzakTx->gateway_type)->toBe(GatewayType::TRANZAK);
});

