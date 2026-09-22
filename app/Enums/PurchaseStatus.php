<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
}
