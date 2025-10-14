<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case RECEPTIONIST = 'receptionist';
    case CLIENT = 'client';
}
