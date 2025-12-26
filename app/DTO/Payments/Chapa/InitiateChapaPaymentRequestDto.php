<?php

namespace App\DTO\Payments\Chapa;

readonly class InitiateChapaPaymentRequestDto
{
    public function __construct(
        public int $reservationId,
        public float $amount,
        public string $currency,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public string $callbackUrl,
        public string $returnUrl,
    ) {
    }
}

