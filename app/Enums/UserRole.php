<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case Manager = 'manager';
    case RECEPTIONIST = 'receptionist';
    case CLIENT = 'client';
}
