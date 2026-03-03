<?php

namespace App\Services\Payment;

use App\Models\Transaction;
use App\PaymentStatus;
use App\GatewayType;
use App\Exceptions\Payment\InvalidStatusTransitionException;
use App\Exceptions\Payment\PaymentException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Payment Service
 * 
 * Handles business logic for payment processing with multiple gateways.
 * Manages transaction lifecycle, status updates, and callback processing.
 * 
 * Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 3.1-3.8, 4.1-4.5, 5.3-5.6, 7.1-7.5
 */
class PaymentService
{
    protected GatewayFactory $gatewayFactory;

    /**
     * Initialize the payment service
     * 
     * @param GatewayFactory $gatewayFactory
     */
    public function __construct(GatewayFactory $gatewayFactory)
    {
        $this->gatewayFactory = $gatewayFactory;
    }

    /**
     * Initialize a new payment
     * 
     * Creates a transaction record and initializes payment with the selected gateway.
     * 
     * @param float $amount Payment amount
     * @param int $userId User ID
     * @param GatewayType $gatewayType Selected payment gateway
     * @param array $metadata Additional metadata
     * @return Transaction Created transaction
     * @throws PaymentException
     */
    public function initializePayment(float $amount, int $userId, GatewayType $gatewayType, array $metadata = []): Transaction
    {
        // Create gateway instance
        $gateway = $this->gatewayFactory->createGateway($gatewayType);
        
        // Generate unique transaction ID
        $transactionId = 'TXN_' . Str::uuid();
        
        // Get gateway-specific configuration
        $config = config('payment.gateways.' . $gatewayType->value);
        $currency = $config['currency'] ?? 'XOF';
        
        // Define URLs
        $returnUrl = route('payment.return', ['transactionId' => $transactionId]);
        $notifyUrl = route('payment.callback.' . $gatewayType->value);
        
        // Create transaction record with PENDING status
        $transaction = Transaction::create([
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => PaymentStatus::PENDING,
            'gateway_type' => $gatewayType,
            'return_url' => $returnUrl,
            'notify_url' => $notifyUrl,
            'metadata' => $metadata,
        ]);
        
        // Log transaction initiation with gateway information
        Log::info('Transaction initiated', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'user_id' => $userId,
            'gateway' => $gatewayType->value,
        ]);
        
        // Initialize payment with the selected gateway
        try {
            $paymentData = [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'description' => $metadata['description'] ?? 'Payment for transaction ' . $transactionId,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
            ];
            
            $response = $gateway->initializePayment($paymentData);
            
            // Update transaction with gateway payment ID and payment URL
            $updateData = [];
            if (isset($response['payment_id'])) {
                $updateData['gateway_payment_id'] = $response['payment_id'];
            }
            
            // Store payment URL in metadata for later retrieval
            if (isset($response['payment_url'])) {
                $metadata['payment_url'] = $response['payment_url'];
                $updateData['metadata'] = $metadata;
            }
            
            if (!empty($updateData)) {
                $transaction->update($updateData);
            }
            
            Log::info('Payment initialized with gateway', [
                'transaction_id' => $transactionId,
                'gateway' => $gatewayType->value,
                'payment_id' => $response['payment_id'] ?? null,
                'has_payment_url' => isset($response['payment_url']),
            ]);
            
            // Re-read transaction to ensure we have the updated one
            $transaction = $transaction->fresh();
            
            return $transaction;
        } catch (\Exception $e) {
            Log::error('Failed to initialize payment with gateway', [
                'transaction_id' => $transactionId,
                'gateway' => $gatewayType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new PaymentException(
                'Failed to initialize payment: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get a transaction by its ID
     * 
     * @param string $transactionId Transaction ID
     * @return Transaction|null Transaction or null if not found
     */
    public function getTransaction(string $transactionId): ?Transaction
    {
        return Transaction::where('transaction_id', $transactionId)
            ->orWhere('gateway_payment_id', $transactionId)
            ->first();
    }

    /**
     * Verify transaction status with the appropriate gateway
     * 
     * Calls the gateway API to check the current status of a transaction.
     * Uses the transaction's gateway_type to select the correct gateway.
     * 
     * @param string $transactionId Transaction ID
     * @return PaymentStatus Current payment status
     * @throws PaymentException
     */
    public function verifyTransactionStatus(string $transactionId): PaymentStatus
    {
        // Get transaction to determine gateway type
        $transaction = $this->getTransaction($transactionId);
        
        if (!$transaction) {
            throw new PaymentException("Transaction not found: {$transactionId}");
        }
        
        // Create gateway instance based on transaction's gateway type
        $gateway = $this->gatewayFactory->createGateway($transaction->gateway_type);
        
        Log::info('Verifying transaction status', [
            'transaction_id' => $transactionId,
            'gateway' => $transaction->gateway_type->value,
        ]);
        
        try {
            // For Tranzak, the API requires requestId (gateway_payment_id), not our internal transaction_id
            $verifyIdentifier = ($transaction->gateway_type === GatewayType::TRANZAK && !empty($transaction->gateway_payment_id))
                ? $transaction->gateway_payment_id
                : $transactionId;

            // Call gateway API to check status
            $response = $gateway->verifyTransaction($verifyIdentifier);
            
            Log::debug('Gateway API response', [
                'transaction_id' => $transactionId,
                'gateway' => $transaction->gateway_type->value,
                'response' => $response,
            ]);
            
            // Extract status from response
            $status = $response['status'] ?? PaymentStatus::PENDING;
            
            Log::info('Transaction status verified', [
                'transaction_id' => $transactionId,
                'gateway' => $transaction->gateway_type->value,
                'status' => $status->value,
            ]);
            
            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to verify transaction status', [
                'transaction_id' => $transactionId,
                'gateway' => $transaction->gateway_type->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new PaymentException(
                'Failed to verify transaction status: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Update transaction status
     * 
     * Validates status transition and updates the transaction in database.
     * 
     * @param string $transactionId Transaction ID
     * @param PaymentStatus $newStatus New status
     * @return Transaction Updated transaction
     * @throws InvalidStatusTransitionException
     */
    public function updateTransactionStatus(string $transactionId, PaymentStatus $newStatus): Transaction
    {
        $transaction = $this->getTransaction($transactionId);
        
        if (!$transaction) {
            throw new PaymentException("Transaction not found: {$transactionId}");
        }
        
        $oldStatus = $transaction->status;
        
        // Validate status transition
        if (!$oldStatus->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException(
                "Invalid status transition from {$oldStatus->value} to {$newStatus->value}"
            );
        }
        
        // Update transaction status
        $transaction->update([
            'status' => $newStatus,
            'verified_at' => now(),
        ]);
        
        // Log status change
        Log::info('Transaction status updated', [
            'transaction_id' => $transactionId,
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
        ]);
        
        return $transaction->fresh();
    }

    /**
     * Process callback notification from payment gateway
     * 
     * Validates callback payload, verifies transaction status with the gateway,
     * and updates transaction status accordingly.
     * 
     * @param GatewayType $gatewayType Gateway that sent the callback
     * @param array $payload Callback notification payload
     * @return bool True if processed successfully
     */
    public function processCallback(GatewayType $gatewayType, array $payload): bool
    {
        // Create gateway instance
        $gateway = $this->gatewayFactory->createGateway($gatewayType);
        
        // Log callback notification with gateway information
        Log::info('Callback notification received', [
            'gateway' => $gatewayType->value,
            'payload' => $payload,
        ]);
        
        try {
            // Validate callback signature/authentication
            if (!$gateway->validateCallback($payload)) {
                Log::warning('Invalid callback signature', [
                    'gateway' => $gatewayType->value,
                ]);
                return false;
            }
            
            // Handle callback and extract transaction information
            $callbackData = $gateway->handleCallback($payload);
            $transactionId = $callbackData['transaction_id'];
            
            // Get transaction
            $transaction = $this->getTransaction($transactionId);
            
            if (!$transaction) {
                Log::warning('Transaction not found for callback', [
                    'transaction_id' => $transactionId,
                    'gateway' => $gatewayType->value,
                ]);
                return false;
            }
            
            // Verify transaction status with gateway before updating
            try {
                $verifiedStatus = $this->verifyTransactionStatus($transactionId);
                
                // Update transaction status based on verification
                if ($transaction->status->canTransitionTo($verifiedStatus)) {
                    $this->updateTransactionStatus($transactionId, $verifiedStatus);
                    
                    Log::info('Transaction status updated from callback', [
                        'transaction_id' => $transactionId,
                        'gateway' => $gatewayType->value,
                        'new_status' => $verifiedStatus->value,
                    ]);
                }
                
                return true;
            } catch (\Exception $e) {
                // If verification fails, log error and preserve current status
                Log::error('Callback verification failed', [
                    'transaction_id' => $transactionId,
                    'gateway' => $gatewayType->value,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to process callback', [
                'gateway' => $gatewayType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Process IPN notification from CinetPay (legacy method for backward compatibility)
     * 
     * @deprecated Use processCallback() instead
     * @param array $payload IPN notification payload
     * @return bool True if processed successfully
     */
    public function processIPN(array $payload): bool
    {
        return $this->processCallback(GatewayType::CINETPAY, $payload);
    }
    
    /**
     * Get list of available payment gateways
     * 
     * Returns an array of gateway types that have valid credentials
     * configured and are available for use.
     * 
     * @return array Array of available GatewayType enum values
     */
    public function getAvailableGateways(): array
    {
        return $this->gatewayFactory->getAvailableGateways();
    }
}
