<?php

namespace App\DTO\Payments\Chapa;

readonly class WebhookEventDto
{
    public function __construct(
        public string $txRef,
        public string $status,
        public float $amount,
        public string $currency,
        public array $raw,
    ) {
    }
}

