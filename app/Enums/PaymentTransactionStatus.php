<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}
