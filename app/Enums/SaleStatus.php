<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'DRAFT';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';
}
