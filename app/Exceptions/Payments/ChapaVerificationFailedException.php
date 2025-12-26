<?php

namespace App\Exceptions\Payments;

use Exception;

class ChapaVerificationFailedException extends Exception
{
    public function __construct(string $message, public array $response = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

