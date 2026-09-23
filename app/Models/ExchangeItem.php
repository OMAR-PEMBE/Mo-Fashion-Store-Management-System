<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['unit_cost', 'line_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_value' => 'decimal:2', 'line_total' => 'decimal:2', 'applied_credit' => 'decimal:2', 'refund_amount' => 'decimal:2', 'unit_cost' => 'decimal:2', 'line_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use ExchangeService for exchange items.'));
        static::deleting(fn () => throw new \LogicException('Exchange item history is permanent.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
