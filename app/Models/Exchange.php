<?php

namespace App\Models;

use App\Enums\ExchangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exchange extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['request_key', 'request_hash'];

    protected function casts(): array
    {
        return ['status' => ExchangeStatus::class, 'total_return_value' => 'decimal:2', 'total_replacement_value' => 'decimal:2', 'amount_due' => 'decimal:2', 'refund_due' => 'decimal:2', 'processed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use ExchangeService to change exchanges.'));
        static::deleting(fn () => throw new \LogicException('Exchange history is permanent.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExchangeItem::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
