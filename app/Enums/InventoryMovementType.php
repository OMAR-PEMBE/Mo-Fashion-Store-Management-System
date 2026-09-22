<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OpeningBalance = 'OPENING_BALANCE';
    case Purchase = 'PURCHASE';
    case Sale = 'SALE';
    case Reservation = 'RESERVATION';
    case ReservationRelease = 'RESERVATION_RELEASE';
    case Return = 'RETURN';
    case ExchangeIn = 'EXCHANGE_IN';
    case ExchangeOut = 'EXCHANGE_OUT';
    case Damage = 'DAMAGE';
    case Loss = 'LOSS';
    case AdjustmentIn = 'ADJUSTMENT_IN';
    case AdjustmentOut = 'ADJUSTMENT_OUT';
    case Reversal = 'REVERSAL';
}
