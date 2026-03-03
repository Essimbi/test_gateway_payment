<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when payment configuration is invalid or missing
 * 
 * This exception is thrown when required configuration values
 * (API_KEY, SITE_ID, SECRET_KEY) are missing or invalid.
 * 
 * Validates: Requirements 2.5
 */
class PaymentConfigurationException extends PaymentException
{
}
