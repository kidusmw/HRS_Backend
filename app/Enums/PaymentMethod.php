<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CHAPA = 'chapa';
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case STRIPE = 'stripe';
    case TELEBIRR = 'telebirr';
}
