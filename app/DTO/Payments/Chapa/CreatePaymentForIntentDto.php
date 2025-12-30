<?php

namespace App\DTO\Payments\Chapa;

readonly class CreatePaymentForIntentDto
{
    public function __construct(
        public int $reservationIntentId,
        public string $txRef,
        public float $amount,
        public string $currency,
        public string $callbackUrl,
        public string $returnUrl,
    ) {
    }
}

