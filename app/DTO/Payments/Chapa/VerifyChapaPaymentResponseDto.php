<?php

namespace App\DTO\Payments\Chapa;

readonly class VerifyChapaPaymentResponseDto
{
    public function __construct(
        public string $txRef,
        public string $chapaStatus,
        public array $raw,
    ) {
    }
}

