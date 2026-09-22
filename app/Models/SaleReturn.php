<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $table = 'returns';

    protected $guarded = ['*'];

    protected $hidden = ['request_key', 'request_hash', 'total_cost_adjustment'];

    protected function casts(): array
    {
        return ['status' => ReturnStatus::class, 'total_cost_adjustment' => 'decimal:2', 'return_date' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use ReturnService for returns.'));
        static::deleting(fn () => throw new \LogicException('Return history is permanent.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
