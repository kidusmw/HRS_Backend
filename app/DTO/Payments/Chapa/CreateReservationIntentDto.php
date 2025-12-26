<?php

namespace App\DTO\Payments\Chapa;

readonly class CreateReservationIntentDto
{
    public function __construct(
        public int $hotelId,
        public string $roomType,
        public string $checkIn,
        public string $checkOut,
        public int $guests,
    ) {
    }
}

