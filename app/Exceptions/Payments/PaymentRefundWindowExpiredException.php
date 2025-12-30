<?php

namespace App\Exceptions\Payments;

use Exception;

class PaymentRefundWindowExpiredException extends Exception
{
    public function __construct(string $message = 'Refund window has expired. Refunds are only available within 24 hours of payment completion.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

