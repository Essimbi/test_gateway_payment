<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when payment data validation fails
 * 
 * This exception is thrown when:
 * - Invalid payment amount is provided
 * - Required fields are missing
 * - Data format is incorrect
 * - Business rules are violated
 */
class PaymentValidationException extends PaymentException
{
}
