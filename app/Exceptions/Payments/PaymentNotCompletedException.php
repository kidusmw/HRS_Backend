<?php

namespace App\Exceptions\Payments;

use Exception;

class PaymentNotCompletedException extends Exception
{
    public function __construct(string $message = 'Payment must be completed before it can be refunded', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

