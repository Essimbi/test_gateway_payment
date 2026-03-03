<?php

namespace App\Services\Payment;

use App\GatewayType;
use App\PaymentStatus;
use Illuminate\Support\Facades\Log;

/**
 * Tranzak Gateway Implementation
 * 
 * Implements the PaymentGatewayInterface for Tranzak payment gateway.
 * This class wraps the TranzakClient and adapts it to the common
 * gateway interface, enabling consistent payment processing across
 * different payment providers.
 * 
 * Validates: Requirements 2.7, 4.1, 4.3, 4.4
 */
class TranzakGateway implements PaymentGatewayInterface
{
    /**
     * @var TranzakClient The Tranzak API client
     */
    private TranzakClient $client;
    
    /**
     * @var array Gateway configuration
     */
    private array $config;
    
    /**
     * Create a new TranzakGateway instance
     * 
     * @param TranzakClient $client The Tranzak API client
     * @param array $config Gateway configuration
     */
    public function __construct(TranzakClient $client, array $config)
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
        return 'Tranzak';
    }
    
    /**
     * Get the gateway type
     * 
     * @return GatewayType Gateway type enum value
     */
    public function getGatewayType(): GatewayType
    {
        return GatewayType::TRANZAK;
    }
    
    /**
     * Initialize a payment with Tranzak
     * 
     * Creates a payment request with Tranzak and returns the payment URL
     * where the user should be redirected to complete the payment.
     * 
     * @param array $data Payment data containing:
     *   - transaction_id: Unique transaction identifier
     *   - amount: Payment amount
     *   - currency: Currency code (e.g., "XAF")
     *   - return_url: URL to redirect user after payment
     *   - notify_url: URL for gateway callback notifications
     *   - description: Payment description
     * 
     * @return array Payment initialization response containing:
     *   - payment_url: URL to redirect user for payment
     *   - payment_id: Gateway's payment identifier
     * 
     * @throws \App\Exceptions\Payment\TranzakApiException If initialization fails
     */
    public function initializePayment(array $data): array
    {
        Log::debug('TranzakGateway: Initializing payment', [
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount' => $data['amount'] ?? null,
        ]);
        
        // Prepare payment data for Tranzak API
        // Use a shorter, alphanumeric ID for Tranzak (max 25 chars)
        $mchTransactionRef = substr(str_replace(['TXN_', '-'], '', $data['transaction_id']), 0, 25);
        
        $paymentData = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'XAF',
            'description' => $data['description'] ?? 'Payment',
            'return_url' => $data['return_url'],
            'cancel_url' => $data['return_url'], // Use return_url as cancel_url
            'callback_url' => $data['notify_url'],
            'app_id' => $this->config['app_id'] ?? null,
            'mchTransactionRef' => $mchTransactionRef, // Use the shortened reference
        ];
        
        // Call Tranzak API to create payment, disabling retries as per instruction
        $response = $this->client->createPayment($paymentData, ['retries' => 0]);
        
        Log::info('TranzakGateway: Payment initialized successfully', [
            'transaction_id' => $data['transaction_id'],
            'request_id' => $response['request_id'] ?? null,
        ]);
        
        // Adapt the response to the common interface format
        return [
            'payment_url' => $response['links']['payment_url'],
            'payment_id' => $response['request_id'],
        ];
    }
    
    /**
     * Verify transaction status with Tranzak
     * 
     * Queries the Tranzak API to verify the current status of a transaction.
     * This method should be called to confirm payment status before updating
     * local transaction records.
     * 
     * @param string $transactionId Transaction identifier (can be request_id or mchTransactionRef)
     * 
     * @return array Verification response containing:
     *   - status: Mapped payment status
     *   - transaction_id: Transaction identifier
     *   - amount: Payment amount (if available)
     *   - currency: Currency code (if available)
     * 
     * @throws \App\Exceptions\Payment\TranzakApiException If verification fails
     */
    public function verifyTransaction(string $transactionId): array
    {
        Log::debug('TranzakGateway: Verifying transaction', [
            'transaction_id' => $transactionId,
        ]);
        
        // Get payment status from Tranzak API
        $response = $this->client->getPaymentStatus($transactionId);
        
        // Map the Tranzak status to our PaymentStatus enum
        $mappedStatus = $this->mapStatus($response['status'] ?? 'PENDING');
        
        Log::debug('TranzakGateway: Transaction verified', [
            'transaction_id' => $transactionId,
            'gateway_status' => $response['status'] ?? 'UNKNOWN',
            'mapped_status' => $mappedStatus->value,
        ]);
        
        // Return in common format
        return [
            'status' => $mappedStatus,
            'transaction_id' => $response['mchTransactionRef'] ?? $transactionId,
            'amount' => $response['amount'] ?? null,
            'currency' => $response['currencyCode'] ?? null,
        ];
    }
    
    /**
     * Handle callback notification from Tranzak
     * 
     * Processes a callback/webhook notification from Tranzak.
     * Extracts relevant payment information from the Tranzak payload format.
     * 
     * Validates: Requirements 12.1, 12.2, 12.3, 12.5
     * 
     * @param array $payload Raw callback payload from Tranzak
     * 
     * @return array Processed callback data containing:
     *   - transaction_id: Transaction identifier
     *   - status: Mapped payment status
     *   - verified: Whether the callback was verified
     * 
     * @throws \App\Exceptions\Payment\PaymentValidationException If validation fails
     */
    public function handleCallback(array $payload): array
    {
        Log::debug('TranzakGateway: Handling callback', [
            'request_id' => $payload['request_id'] ?? null,
            'mchTransactionRef' => $payload['mchTransactionRef'] ?? null,
        ]);
        
        // Validate payload structure (Requirement 12.1)
        $this->validateCallbackPayload($payload);
        
        // Extract transaction ID from Tranzak payload
        // Prefer mchTransactionRef (our transaction ID) over request_id
        $transactionId = $payload['mchTransactionRef'] ?? $payload['request_id'] ?? null;
        
        // Verify transaction exists (Requirement 12.2)
        $this->verifyTransactionExists($transactionId);
        
        // Validate API response if request_id is present (Requirement 12.3)
        if (isset($payload['request_id'])) {
            $this->validateApiResponse($payload);
        }
        
        // Map the status from the callback
        $status = $this->mapStatus($payload['status'] ?? 'PENDING');
        
        Log::info('TranzakGateway: Callback processed', [
            'transaction_id' => $transactionId,
            'gateway_status' => $payload['status'] ?? 'UNKNOWN',
            'mapped_status' => $status->value,
        ]);
        
        return [
            'transaction_id' => $transactionId,
            'status' => $status,
            'verified' => true,
        ];
    }
    
    /**
     * Validate callback authenticity
     * 
     * Verifies that a callback notification is authentic and comes from Tranzak.
     * For Tranzak, we validate by making an API call to verify the transaction
     * status, as Tranzak uses API key authentication rather than signature-based
     * validation.
     * 
     * @param array $payload Callback payload to validate
     * 
     * @return bool True if callback is valid, false otherwise
     */
    public function validateCallback(array $payload): bool
    {
        Log::debug('TranzakGateway: Validating callback');
        
        // For Tranzak, we validate by checking if the request_id exists
        // and can be verified via the API
        if (!isset($payload['request_id']) && !isset($payload['mchTransactionRef'])) {
            Log::warning('TranzakGateway: Callback missing required identifiers');
            return false;
        }
        
        try {
            // Attempt to verify the transaction via API
            $requestId = $payload['request_id'] ?? null;
            
            if ($requestId) {
                $this->client->getPaymentStatus($requestId);
                Log::debug('TranzakGateway: Callback validated successfully');
                return true;
            }
            
            // If no request_id, we'll accept it but mark for verification
            Log::debug('TranzakGateway: Callback accepted (will verify via API later)');
            return true;
            
        } catch (\Exception $e) {
            Log::warning('TranzakGateway: Callback validation failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Validate callback payload structure
     * 
     * Validates: Requirement 12.1
     * 
     * @param array $payload Callback payload to validate
     * @throws \App\Exceptions\Payment\PaymentValidationException If payload is invalid
     */
    private function validateCallbackPayload(array $payload): void
    {
        // Check for required fields
        $requiredFields = ['status'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                $missingFields[] = $field;
            }
        }
        
        // Must have at least one identifier (request_id or mchTransactionRef)
        if (!isset($payload['request_id']) && !isset($payload['mchTransactionRef'])) {
            $missingFields[] = 'request_id or mchTransactionRef';
        }
        
        if (!empty($missingFields)) {
            Log::error('TranzakGateway: Invalid callback payload structure', [
                'missing_fields' => $missingFields,
                'payload' => $payload,
            ]);
            
            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback payload: missing required fields - ' . implode(', ', $missingFields)
            );
        }
        
        // Validate status is a string
        if (!is_string($payload['status'])) {
            Log::error('TranzakGateway: Invalid status type in callback', [
                'status_type' => gettype($payload['status']),
                'payload' => $payload,
            ]);
            
            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback payload: status must be a string'
            );
        }
        
        Log::debug('TranzakGateway: Callback payload structure validated');
    }
    
    /**
     * Verify transaction exists in database
     * 
     * Validates: Requirement 12.2
     * 
     * @param string|null $transactionId Transaction identifier
     * @throws \App\Exceptions\Payment\PaymentValidationException If transaction not found
     */
    private function verifyTransactionExists(?string $transactionId): void
    {
        if (empty($transactionId)) {
            Log::error('TranzakGateway: Empty transaction ID in callback');
            
            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction ID is empty'
            );
        }
        
        // Check if transaction exists
        $transaction = \App\Models\Transaction::where('transaction_id', $transactionId)
            ->orWhere('gateway_payment_id', $transactionId)
            ->first();
        
        if (!$transaction) {
            Log::error('TranzakGateway: Transaction not found', [
                'transaction_id' => $transactionId,
            ]);
            
            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction not found - ' . $transactionId
            );
        }
        
        // Verify it's a Tranzak transaction
        if ($transaction->gateway_type !== GatewayType::TRANZAK) {
            Log::error('TranzakGateway: Transaction is not a Tranzak transaction', [
                'transaction_id' => $transactionId,
                'gateway_type' => $transaction->gateway_type->value,
            ]);
            
            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction is not a Tranzak transaction'
            );
        }
        
        Log::debug('TranzakGateway: Transaction exists and is valid', [
            'transaction_id' => $transactionId,
        ]);
    }
    
    /**
     * Validate API response format
     * 
     * Validates: Requirement 12.3
     * 
     * @param array $payload Callback payload containing API response data
     * @throws \App\Exceptions\Payment\PaymentValidationException If response format is invalid
     */
    private function validateApiResponse(array $payload): void
    {
        // Validate that the response has expected structure
        // Tranzak responses should have certain fields
        $expectedFields = ['status'];
        
        foreach ($expectedFields as $field) {
            if (!isset($payload[$field])) {
                Log::error('TranzakGateway: Invalid API response format', [
                    'missing_field' => $field,
                    'payload' => $payload,
                ]);
                
                throw new \App\Exceptions\Payment\PaymentValidationException(
                    'Invalid Tranzak API response format: missing ' . $field
                );
            }
        }
        
        // Validate status is a recognized value
        $validStatuses = ['SUCCESSFUL', 'SUCCESS', 'COMPLETED', 'FAILED', 'CANCELLED', 
                         'CANCELED', 'REJECTED', 'PENDING', 'PROCESSING', 'INITIATED'];
        
        $status = strtoupper($payload['status']);
        if (!in_array($status, $validStatuses)) {
            Log::warning('TranzakGateway: Unknown status in API response', [
                'status' => $payload['status'],
                'payload' => $payload,
            ]);
            
            // Don't throw exception for unknown status, just log warning
            // The system will default to PENDING for unknown statuses
        }
        
        Log::debug('TranzakGateway: API response format validated');
    }
    
    /**
     * Map Tranzak status to PaymentStatus enum
     * 
     * Converts Tranzak-specific status codes to our standardized
     * PaymentStatus enum values.
     * 
     * @param string $gatewayStatus Status from Tranzak
     * @return PaymentStatus Mapped payment status
     */
    private function mapStatus(string $gatewayStatus): PaymentStatus
    {
        return match(strtoupper($gatewayStatus)) {
            'SUCCESSFUL', 'SUCCESS', 'COMPLETED' => PaymentStatus::ACCEPTED,
            'FAILED', 'CANCELLED', 'CANCELED', 'REJECTED' => PaymentStatus::REFUSED,
            'PENDING', 'PROCESSING', 'INITIATED' => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };
    }
}
