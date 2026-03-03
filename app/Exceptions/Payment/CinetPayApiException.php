<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when communication with CinetPay API fails
 * 
 * This exception is thrown when:
 * - CinetPay API is unreachable
 * - API returns an error response
 * - Network timeouts occur
 * - SDK throws an exception
 * 
 * Validates: Requirements 10.1, 10.2
 */
class CinetPayApiException extends PaymentException
{
}
