<?php

namespace App\DTO\Payments\Chapa;

readonly class InitiateChapaPaymentResponseDto
{
    public function __construct(
        public string $checkoutUrl,
        public string $txRef,
        public string $status,
    ) {
    }
}

