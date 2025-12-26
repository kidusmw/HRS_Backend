<?php

namespace App\DTO\Payments\Chapa;

readonly class InitiateChapaPaymentRequestDto
{
    public function __construct(
        public string $txRef,
        public float $amount,
        public string $currency,
        public ?string $customerPhone,
        public string $customerEmail,
        public string $customerFirstName,
        public string $customerLastName,
        public string $callbackUrl,
        public string $returnUrl,
    ) {
    }
}

