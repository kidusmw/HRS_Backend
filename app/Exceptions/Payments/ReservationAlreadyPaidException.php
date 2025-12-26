<?php

namespace App\Exceptions\Payments;

use Exception;

class ReservationAlreadyPaidException extends Exception
{
    public function __construct(string $message = 'Reservation is already fully paid', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

