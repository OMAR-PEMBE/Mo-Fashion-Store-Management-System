<?php

namespace App\Enums;

enum ExchangeStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
