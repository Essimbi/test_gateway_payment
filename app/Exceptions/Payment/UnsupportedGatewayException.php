<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when an unsupported gateway type is requested
 * 
 * This exception should be thrown when attempting to use a gateway type
 * that is not defined in the GatewayType enum or not supported by the system.
 */
class UnsupportedGatewayException extends PaymentException
{
}
