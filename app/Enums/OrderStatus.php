<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'NEW';
    case Confirmed = 'CONFIRMED';
    case PaymentReceived = 'PAYMENT_RECEIVED';
    case Preparing = 'PREPARING';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
}
