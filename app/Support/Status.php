<?php

namespace App\Support;

use BackedEnum;

/**
 * Plain-language labels and semantic badge tones for every workflow status in the system.
 * Tones follow UI.md: warning = needs someone's action, info = in progress,
 * success = finished well, danger = refused/failed, neutral = closed or inactive.
 */
class Status
{
    private const MAP = [
        'NEW' => ['New', 'warning'],
        'PENDING' => ['Waiting for approval', 'warning'],
        'DRAFT' => ['Draft', 'neutral'],
        'CONFIRMED' => ['Confirmed', 'info'],
        'APPROVED' => ['Approved', 'info'],
        'PAYMENT_RECEIVED' => ['Paid', 'info'],
        'PREPARING' => ['Preparing', 'info'],
        'OUT_FOR_DELIVERY' => ['Out for delivery', 'info'],
        'DELIVERED' => ['Delivered', 'success'],
        'COMPLETED' => ['Completed', 'success'],
        'PARTIALLY_REFUNDED' => ['Partly refunded', 'info'],
        'REFUNDED' => ['Refunded', 'neutral'],
        'REJECTED' => ['Rejected', 'danger'],
        'CANCELLED' => ['Cancelled', 'neutral'],
        'ACTIVE' => ['Reserved', 'info'],
        'RELEASED' => ['Released', 'neutral'],
        'PAID' => ['Paid', 'success'],
        'UNPAID' => ['Unpaid', 'warning'],
        'PARTIALLY_PAID' => ['Partly paid', 'warning'],
    ];

    public static function value(BackedEnum|string|null $status): string
    {
        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }

    public static function label(BackedEnum|string|null $status): string
    {
        $value = self::value($status);

        return self::MAP[$value][0] ?? ucfirst(strtolower(str_replace('_', ' ', $value)));
    }

    public static function tone(BackedEnum|string|null $status): string
    {
        return self::MAP[self::value($status)][1] ?? 'neutral';
    }
}
