<?php

namespace App\DTO\Payments\Chapa;

readonly class RefundChapaPaymentRequestDto
{
    public function __construct(
        public int $paymentId,
        public string $reason,
    ) {
    }
}

