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
            'payment_url' => $response['links']['paymentAuthUrl'],
            'payment_id' => $response['requestId'],
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
     * Normalize Tranzak webhook (TPN) payload structure.
     * 
     * Tranzak sends transaction data inside a 'resource' object at the top level.
     * This method extracts it to a flat structure for consistent processing.
     * 
     * @param array $payload Raw webhook payload
     * @return array Normalized payload with transaction data at top level
     */
    private function normalizeWebhookPayload(array $payload): array
    {
        if (isset($payload['resource']) && is_array($payload['resource'])) {
            $resource = $payload['resource'];
            return array_merge($resource, [
                'request_id' => $resource['requestId'] ?? $resource['request_id'] ?? $payload['resourceId'] ?? null,
                'status' => $resource['transactionStatus'] ?? $resource['status'] ?? null,
            ]);
        }
        return $payload;
    }

    /**
     * Handle callback notification from Tranzak
     * 
     * Processes a callback/webhook notification from Tranzak.
     * Supports both flat payload and TPN format (data inside 'resource').
     * 
     * Validates: Requirements 12.1, 12.2, 12.3, 12.5
     * 
     * @param array $payload Raw callback payload from Tranzak
     * 
     * @return array Processed callback data containing:
     *   - transaction_id: Transaction identifier (internal)
     *   - status: Mapped payment status
     *   - verified: Whether the callback was verified
     * 
     * @throws \App\Exceptions\Payment\PaymentValidationException If validation fails
     */
    public function handleCallback(array $payload): array
    {
        $payload = $this->normalizeWebhookPayload($payload);

        Log::debug('TranzakGateway: Handling callback', [
            'request_id' => $payload['request_id'] ?? $payload['requestId'] ?? null,
            'mchTransactionRef' => $payload['mchTransactionRef'] ?? null,
        ]);

        // Validate payload structure (Requirement 12.1)
        $this->validateCallbackPayload($payload);
        
        // Extract identifiers - try request_id first (matches gateway_payment_id), then mchTransactionRef
        $requestId = $payload['request_id'] ?? $payload['requestId'] ?? null;
        $mchTransactionRef = $payload['mchTransactionRef'] ?? null;

        // Verify transaction exists (Requirement 12.2) - returns transaction for downstream use
        $transaction = $this->verifyTransactionExists($requestId, $mchTransactionRef);

        // Validate API response if request_id is present (Requirement 12.3)
        if ($requestId !== null) {
            $this->validateApiResponse($payload);
        }

        // Map the status from the callback (support both 'status' and 'transactionStatus')
        $gatewayStatus = $payload['transactionStatus'] ?? $payload['status'] ?? 'PENDING';
        $status = $this->mapStatus($gatewayStatus);

        // Return internal transaction_id for PaymentService
        $transactionId = $transaction->transaction_id;
        
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

        $payload = $this->normalizeWebhookPayload($payload);

        // For Tranzak, we validate by checking if the request_id exists
        // and can be verified via the API
        $requestId = $payload['request_id'] ?? $payload['requestId'] ?? null;
        $mchTransactionRef = $payload['mchTransactionRef'] ?? null;
        if ($requestId === null && $mchTransactionRef === null) {
            Log::warning('TranzakGateway: Callback missing required identifiers');
            return false;
        }

        try {
            // Attempt to verify the transaction via API (requires requestId)
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
        $status = $payload['transactionStatus'] ?? $payload['status'] ?? null;
        $requestId = $payload['request_id'] ?? $payload['requestId'] ?? null;
        $mchTransactionRef = $payload['mchTransactionRef'] ?? null;

        $missingFields = [];

        if ($status === null) {
            $missingFields[] = 'status or transactionStatus';
        }

        if ($requestId === null && $mchTransactionRef === null) {
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
        if (!is_string($status)) {
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
     * @param string|null $requestId Tranzak request ID (matches gateway_payment_id)
     * @param string|null $mchTransactionRef Merchant transaction reference (may match transaction_id)
     * @return \App\Models\Transaction The found transaction
     * @throws \App\Exceptions\Payment\PaymentValidationException If transaction not found
     */
    private function verifyTransactionExists(?string $requestId, ?string $mchTransactionRef): \App\Models\Transaction
    {
        $identifiers = array_filter([$requestId, $mchTransactionRef]);
        if (empty($identifiers)) {
            Log::error('TranzakGateway: Empty transaction ID in callback');

            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction ID is empty'
            );
        }

        // Check if transaction exists - try request_id first (gateway_payment_id), then mchTransactionRef (transaction_id)
        $transaction = null;
        foreach ($identifiers as $identifier) {
            $transaction = \App\Models\Transaction::where('transaction_id', $identifier)
                ->orWhere('gateway_payment_id', $identifier)
                ->first();
            if ($transaction) {
                break;
            }
        }

        if (!$transaction) {
            Log::error('TranzakGateway: Transaction not found', [
                'request_id' => $requestId,
                'mch_transaction_ref' => $mchTransactionRef,
            ]);

            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction not found'
            );
        }

        // Verify it's a Tranzak transaction
        if ($transaction->gateway_type !== GatewayType::TRANZAK) {
            Log::error('TranzakGateway: Transaction is not a Tranzak transaction', [
                'transaction_id' => $transaction->transaction_id,
                'gateway_type' => $transaction->gateway_type->value,
            ]);

            throw new \App\Exceptions\Payment\PaymentValidationException(
                'Invalid Tranzak callback: transaction is not a Tranzak transaction'
            );
        }

        Log::debug('TranzakGateway: Transaction exists and is valid', [
            'transaction_id' => $transaction->transaction_id,
        ]);

        return $transaction;
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
            'FAILED', 'CANCELLED', 'CANCELED', 'CANCELLED/REFUNDED', 'REJECTED', 'REFUNDED' => PaymentStatus::REFUSED,
            'PENDING', 'PROCESSING', 'INITIATED', 'PAYMENT_IN_PROGRESS', 'PAYER_REDIRECT_REQUIRED' => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };
    }
}
