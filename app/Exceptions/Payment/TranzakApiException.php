<?php

namespace App\Exceptions\Payment;

/**
 * Exception thrown when Tranzak API calls fail
 * 
 * This exception is specific to Tranzak gateway errors and should be thrown
 * when API requests to Tranzak fail, timeout, or return error responses.
 */
class TranzakApiException extends PaymentException
{
}
