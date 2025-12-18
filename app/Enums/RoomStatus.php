<?php

namespace App\Enums;

enum RoomStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
}

