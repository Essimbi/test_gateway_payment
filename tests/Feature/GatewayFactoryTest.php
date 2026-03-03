<?php

use App\GatewayType;
use App\Services\Payment\GatewayFactory;
use App\Services\Payment\CinetPayGateway;
use App\Services\Payment\TranzakGateway;
use App\Exceptions\Payment\PaymentConfigurationException;
use Illuminate\Support\Facades\Config;

/**
 * Feature tests for GatewayFactory
 * 
 * Tests the factory pattern implementation for creating payment gateway instances.
 */

test('creates CinetPay gateway with valid credentials', function () {
    // Set up config for CinetPayClient
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    $config = [
        'cinetpay' => [
            'api_key' => 'test_api_key',
            'site_id' => 'test_site_id',
            'secret_key' => 'test_secret_key',
            'currency' => 'XOF',
        ],
    ];
    
    $factory = new GatewayFactory($config);
    $gateway = $factory->createGateway(GatewayType::CINETPAY);
    
    expect($gateway)->toBeInstanceOf(CinetPayGateway::class);
    expect($gateway->getGatewayType())->toBe(GatewayType::CINETPAY);
    expect($gateway->getGatewayName())->toBe('CinetPay');
});

test('creates Tranzak gateway with valid credentials', function () {
    $config = [
        'tranzak' => [
            'api_key' => 'test_api_key',
            'app_id' => 'test_app_id',
            'currency' => 'XAF',
            'base_url' => 'https://dsapi.tranzak.me',
        ],
    ];
    
    $factory = new GatewayFactory($config);
    $gateway = $factory->createGateway(GatewayType::TRANZAK);
    
    expect($gateway)->toBeInstanceOf(TranzakGateway::class);
    expect($gateway->getGatewayType())->toBe(GatewayType::TRANZAK);
    expect($gateway->getGatewayName())->toBe('Tranzak');
});

test('throws exception when CinetPay credentials are missing', function () {
    $config = [
        'cinetpay' => [
            // Missing credentials
        ],
    ];
    
    $factory = new GatewayFactory($config);
    
    expect(fn() => $factory->createGateway(GatewayType::CINETPAY))
        ->toThrow(PaymentConfigurationException::class);
});

test('throws exception when Tranzak credentials are missing', function () {
    $config = [
        'tranzak' => [
            // Missing credentials
        ],
    ];
    
    $factory = new GatewayFactory($config);
    
    expect(fn() => $factory->createGateway(GatewayType::TRANZAK))
        ->toThrow(PaymentConfigurationException::class);
});

test('getAvailableGateways returns only gateways with valid credentials', function () {
    // Set up config for CinetPayClient
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    $config = [
        'cinetpay' => [
            'api_key' => 'test_api_key',
            'site_id' => 'test_site_id',
            'secret_key' => 'test_secret_key',
        ],
        'tranzak' => [
            // Missing credentials - should be excluded
        ],
    ];
    
    $factory = new GatewayFactory($config);
    $available = $factory->getAvailableGateways();
    
    expect($available)->toBeArray();
    expect($available)->toContain(GatewayType::CINETPAY);
    expect($available)->not->toContain(GatewayType::TRANZAK);
});

test('getAvailableGateways returns empty array when no credentials configured', function () {
    $config = [
        'cinetpay' => [],
        'tranzak' => [],
    ];
    
    $factory = new GatewayFactory($config);
    $available = $factory->getAvailableGateways();
    
    expect($available)->toBeArray();
    expect($available)->toBeEmpty();
});

test('getAvailableGateways returns all gateways when all credentials are valid', function () {
    // Set up config for CinetPayClient
    Config::set('cinetpay.api_key', 'test_api_key');
    Config::set('cinetpay.site_id', 'test_site_id');
    Config::set('cinetpay.secret_key', 'test_secret_key');
    
    $config = [
        'cinetpay' => [
            'api_key' => 'test_api_key',
            'site_id' => 'test_site_id',
            'secret_key' => 'test_secret_key',
        ],
        'tranzak' => [
            'api_key' => 'test_api_key',
            'app_id' => 'test_app_id',
        ],
    ];
    
    $factory = new GatewayFactory($config);
    $available = $factory->getAvailableGateways();
    
    expect($available)->toBeArray();
    expect($available)->toHaveCount(2);
    expect($available)->toContain(GatewayType::CINETPAY);
    expect($available)->toContain(GatewayType::TRANZAK);
});
