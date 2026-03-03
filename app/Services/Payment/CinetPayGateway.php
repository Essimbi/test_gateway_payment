<?php

namespace App\Services\Payment;

use App\GatewayType;
use App\PaymentStatus;
use Illuminate\Support\Facades\Log;

/**
 * CinetPay Gateway Implementation
 * 
 * Implements the PaymentGatewayInterface for CinetPay payment gateway.
 * This class wraps the existing CinetPayClient and adapts it to the
 * common gateway interface.
 * 
 * Validates: Requirements 2.6, 9.3, 9.4
 */
class CinetPayGateway implements PaymentGatewayInterface
{
    /**
     * @var CinetPayClient The CinetPay API client
     */
    private CinetPayClient $client;
    
    /**
     * @var array Gateway configuration
     */
    private array $config;
    
    /**
     * Create a new CinetPayGateway instance
     * 
     * @param CinetPayClient $client The CinetPay API client
     * @param array $config Gateway configuration
     */
    public function __construct(CinetPayClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }
    
    /**
     * Get the gateway name
     * 
     * @return string Gateway name
     */
    public function getGatewayName(): string
    {
        return 'CinetPay';
    }
    
    /**
     * Get the gateway type
     * 
     * @return GatewayType Gateway type enum value
     */
    public function getGatewayType(): GatewayType
    {
        return GatewayType::CINETPAY;
    }
    
    /**
     * Initialize a payment with CinetPay
     * 
     * @param array $data Payment data
     * @return array Payment initialization response
     * @throws \App\Exceptions\Payment\CinetPayApiException
     */
    public function initializePayment(array $data): array
    {
        Log::debug('CinetPayGateway: Initializing payment', [
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount' => $data['amount'] ?? null,
        ]);
        
        // Use the existing CinetPayClient to initialize payment
        $response = $this->client->initializePayment($data);
        
        // Adapt the response to the common interface format
        return [
            'payment_url' => $response['payment_url'],
            'payment_id' => $response['cinetpay_payment_id'] ?? $response['payment_token'],
        ];
    }
    
    /**
     * Verify transaction status with CinetPay
     * 
     * @param string $transactionId Transaction identifier
     * @return array Verification response
     * @throws \App\Exceptions\Payment\CinetPayApiException
     */
    public function verifyTransaction(string $transactionId): array
    {
        Log::debug('CinetPayGateway: Verifying transaction', [
            'transaction_id' => $transactionId,
        ]);
        
        // Use the existing CinetPayClient to check status
        $response = $this->client->checkTransactionStatus($transactionId);
        
        // Map the CinetPay status to our PaymentStatus enum
        $mappedStatus = $this->mapStatus($response['status'] ?? 'PENDING');
        
        // Return in common format
        return [
            'status' => $mappedStatus,
            'transaction_id' => $transactionId,
            'amount' => $response['amount'] ?? null,
            'payment_method' => $response['payment_method'] ?? null,
            'operator_id' => $response['operator_id'] ?? null,
        ];
    }
    
    /**
     * Handle callback notification from CinetPay
     * 
     * @param array $payload Callback payload
     * @return array Processed callback data
     */
    public function handleCallback(array $payload): array
    {
        Log::debug('CinetPayGateway: Handling callback', [
            'transaction_id' => $payload['cpm_trans_id'] ?? null,
        ]);
        
        // Extract transaction ID from CinetPay payload
        $transactionId = $payload['cpm_trans_id'] ?? null;
        
        // Map the status from the callback
        $status = $this->mapStatus($payload['cpm_payment_status'] ?? 'PENDING');
        
        return [
            'transaction_id' => $transactionId,
            'status' => $status,
            'verified' => true,
        ];
    }
    
    /**
     * Validate callback authenticity
     * 
     * @param array $payload Callback payload
     * @return bool True if callback is valid
     */
    public function validateCallback(array $payload): bool
    {
        Log::debug('CinetPayGateway: Validating callback signature');
        
        // Use the existing CinetPayClient signature validation
        return $this->client->validateSignature($payload);
    }
    
    /**
     * Map CinetPay status to PaymentStatus enum
     * 
     * Converts CinetPay-specific status codes to our standardized
     * PaymentStatus enum values.
     * 
     * @param string $gatewayStatus Status from CinetPay
     * @return PaymentStatus Mapped payment status
     */
    private function mapStatus(string $gatewayStatus): PaymentStatus
    {
        return match(strtoupper($gatewayStatus)) {
            'ACCEPTED', 'SUCCESSFUL', 'SUCCESS' => PaymentStatus::ACCEPTED,
            'REFUSED', 'FAILED', 'CANCELLED' => PaymentStatus::REFUSED,
            default => PaymentStatus::PENDING,
        };
    }
}

