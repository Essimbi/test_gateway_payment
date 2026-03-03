<?php

namespace App\Exceptions\Payment;

use Exception;

/**
 * Base exception for the payment module
 * 
 * All payment-related exceptions should extend this class
 * to allow for centralized exception handling.
 */
class PaymentException extends Exception
{
}
