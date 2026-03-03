<?php

namespace App\Services\Payment;

use App\GatewayType;
use App\Exceptions\Payment\PaymentConfigurationException;
use App\Exceptions\Payment\UnsupportedGatewayException;
use Illuminate\Support\Facades\Log;

/**
 * Gateway Factory
 * 
 * Factory class for creating payment gateway instances.
 * Handles gateway instantiation with proper configuration and credentials,
 * and provides methods to query available gateways.
 * 
 * Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 3.5, 3.6, 3.7
 */
class GatewayFactory
{
    /**
     * @var array Gateway configuration
     */
    private array $config;
    
    /**
     * Create a new GatewayFactory instance
     * 
     * @param array $config Gateway configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        Log::debug('GatewayFactory initialized', [
            'configured_gateways' => array_keys($config),
        ]);
    }
    
    /**
     * Create a gateway instance based on the gateway type
     * 
     * This method uses a match expression to instantiate the appropriate
     * gateway implementation based on the provided gateway type.
     * 
     * @param GatewayType $gatewayType The type of gateway to create
     * 
     * @return PaymentGatewayInterface Configured gateway instance
     * 
     * @throws UnsupportedGatewayException If the gateway type is not supported
     * @throws PaymentConfigurationException If gateway credentials are missing
     */
    public function createGateway(GatewayType $gatewayType): PaymentGatewayInterface
    {
        Log::debug('Creating gateway instance', [
            'gateway_type' => $gatewayType->value,
        ]);
        
        return match($gatewayType) {
            GatewayType::CINETPAY => $this->createCinetPayGateway(),
            GatewayType::TRANZAK => $this->createTranzakGateway(),
        };
    }
    
    /**
     * Create a CinetPay gateway instance
     * 
     * Instantiates and configures a CinetPayGateway with the appropriate
     * credentials from the configuration. Validates that all required
     * credentials are present before creating the gateway.
     * 
     * @return CinetPayGateway Configured CinetPay gateway instance
     * 
     * @throws PaymentConfigurationException If CinetPay credentials are missing
     */
    private function createCinetPayGateway(): CinetPayGateway
    {
        $config = $this->config['cinetpay'] ?? [];
        
        // Validate required credentials
        if (empty($config['api_key']) || empty($config['site_id']) || empty($config['secret_key'])) {
            Log::error('CinetPay credentials are missing', [
                'has_api_key' => !empty($config['api_key']),
                'has_site_id' => !empty($config['site_id']),
                'has_secret_key' => !empty($config['secret_key']),
            ]);
            
            throw new PaymentConfigurationException(
                'CinetPay credentials are missing. Please configure CINETPAY_API_KEY, CINETPAY_SITE_ID, and CINETPAY_SECRET_KEY in your .env file.'
            );
        }
        
        Log::debug('Creating CinetPay gateway with configuration');
        
        // Create CinetPayClient instance
        // Note: CinetPayClient reads from config/cinetpay.php directly
        $client = new CinetPayClient();
        
        // Create and return the gateway
        return new CinetPayGateway($client, $config);
    }
    
    /**
     * Create a Tranzak gateway instance
     * 
     * Instantiates and configures a TranzakGateway with the appropriate
     * credentials from the configuration. Validates that all required
     * credentials are present before creating the gateway.
     * 
     * @return TranzakGateway Configured Tranzak gateway instance
     * 
     * @throws PaymentConfigurationException If Tranzak credentials are missing
     */
    private function createTranzakGateway(): TranzakGateway
    {
        $config = $this->config['tranzak'] ?? [];
        
        // Validate required credentials
        if (empty($config['api_key']) || empty($config['app_id'])) {
            Log::error('Tranzak credentials are missing', [
                'has_api_key' => !empty($config['api_key']),
                'has_app_id' => !empty($config['app_id']),
            ]);
            
            throw new PaymentConfigurationException(
                'Tranzak credentials are missing. Please configure TRANZAK_API_KEY and TRANZAK_APP_ID in your .env file.'
            );
        }
        
        Log::debug('Creating Tranzak gateway with configuration');
        
        // Create TranzakClient instance with configuration
        $client = new TranzakClient(
            apiKey: $config['api_key'],
            appId: $config['app_id'],
            baseUrl: $config['base_url'] ?? 'https://dsapi.tranzak.me',
            timeout: $config['timeout'] ?? 30,
            retryAttempts: $config['retry_attempts'] ?? 3,
            retryDelays: $config['retry_delays'] ?? [1, 2, 4]
        );
        
        // Create and return the gateway
        return new TranzakGateway($client, $config);
    }
    
    /**
     * Get list of available gateways
     * 
     * Returns an array of gateway types that have valid credentials
     * configured. This method attempts to create each gateway and
     * catches configuration exceptions to determine availability.
     * 
     * Gateways with missing credentials are excluded from the list,
     * allowing the application to gracefully handle partial gateway
     * configurations.
     * 
     * @return array Array of available GatewayType enum values
     */
    public function getAvailableGateways(): array
    {
        $available = [];
        
        Log::debug('Checking available gateways');
        
        // Check each gateway type
        foreach (GatewayType::cases() as $type) {
            try {
                // Attempt to create the gateway
                $this->createGateway($type);
                
                // If successful, add to available list
                $available[] = $type;
                
                Log::debug('Gateway is available', [
                    'gateway_type' => $type->value,
                ]);
                
            } catch (PaymentConfigurationException $e) {
                // Gateway not available due to missing credentials
                Log::info('Gateway is unavailable', [
                    'gateway_type' => $type->value,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info('Available gateways determined', [
            'count' => count($available),
            'gateways' => array_map(fn($g) => $g->value, $available),
        ]);
        
        return $available;
    }
}
