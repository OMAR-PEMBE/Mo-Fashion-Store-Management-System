<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reserved_at' => 'datetime', 'expires_at' => 'datetime', 'released_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use OrderService for reservations.'));
        static::deleting(fn () => throw new \LogicException('Reservation history is permanent.'));
    }
}
