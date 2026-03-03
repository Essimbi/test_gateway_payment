<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when an invalid status transition is attempted
 * 
 * This exception is thrown when attempting to change a transaction
 * status in a way that violates the state machine rules:
 * - ACCEPTED and REFUSED are terminal states
 * - Only PENDING can transition to ACCEPTED or REFUSED
 * 
 * Validates: Requirements 5.5, 5.6
 */
class InvalidStatusTransitionException extends PaymentException
{
}
