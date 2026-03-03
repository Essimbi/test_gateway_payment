<?php

namespace App\Services\Payment;

use App\GatewayType;

/**
 * Payment Gateway Interface
 * 
 * Common interface that all payment gateways must implement.
 * This interface defines the contract for payment gateway implementations,
 * enabling a consistent API across different payment providers.
 * 
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
 */
interface PaymentGatewayInterface
{
    /**
     * Get the gateway name
     * 
     * Returns a human-readable name for the payment gateway.
     * 
     * @return string Gateway name (e.g., "CinetPay", "Tranzak")
     */
    public function getGatewayName(): string;
    
    /**
     * Get the gateway type
     * 
     * Returns the enum value representing this gateway type.
     * 
     * @return GatewayType Gateway type enum value
     */
    public function getGatewayType(): GatewayType;
    
    /**
     * Initialize a payment
     * 
     * Creates a new payment request with the gateway and returns
     * the payment URL where the user should be redirected.
     * 
     * @param array $data Payment data containing:
     *   - transaction_id: Unique transaction identifier
     *   - amount: Payment amount
     *   - currency: Currency code (e.g., "XOF", "XAF")
     *   - return_url: URL to redirect user after payment
     *   - notify_url: URL for gateway callback notifications
     *   - description: Payment description
     * 
     * @return array Payment initialization response containing:
     *   - payment_url: URL to redirect user for payment
     *   - payment_id: Gateway's payment identifier
     * 
     * @throws \App\Exceptions\Payment\PaymentException If initialization fails
     */
    public function initializePayment(array $data): array;
    
    /**
     * Verify transaction status
     * 
     * Queries the gateway API to verify the current status of a transaction.
     * This method should be called to confirm payment status before updating
     * local transaction records.
     * 
     * @param string $transactionId Transaction identifier
     * 
     * @return array Verification response containing:
     *   - status: Payment status from gateway
     *   - transaction_id: Transaction identifier
     *   - amount: Payment amount (if available)
     *   - currency: Currency code (if available)
     * 
     * @throws \App\Exceptions\Payment\PaymentException If verification fails
     */
    public function verifyTransaction(string $transactionId): array;
    
    /**
     * Handle callback notification
     * 
     * Processes a callback/webhook notification from the payment gateway.
     * Extracts relevant payment information from the gateway's payload format.
     * 
     * @param array $payload Raw callback payload from gateway
     * 
     * @return array Processed callback data containing:
     *   - transaction_id: Transaction identifier
     *   - status: Mapped payment status
     *   - verified: Whether the callback was verified
     * 
     * @throws \App\Exceptions\Payment\PaymentException If callback processing fails
     */
    public function handleCallback(array $payload): array;
    
    /**
     * Validate callback authenticity
     * 
     * Verifies that a callback notification is authentic and comes from
     * the payment gateway. Implementation depends on gateway's security
     * mechanism (signature validation, API verification, etc.).
     * 
     * @param array $payload Callback payload to validate
     * 
     * @return bool True if callback is valid, false otherwise
     */
    public function validateCallback(array $payload): bool;
}
