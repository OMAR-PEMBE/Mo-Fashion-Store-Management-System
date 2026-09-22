<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}
