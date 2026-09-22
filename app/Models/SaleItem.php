<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['unit_cost', 'line_cost', 'line_gross_profit'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'unit_cost' => 'decimal:2', 'discount_amount' => 'decimal:2', 'line_subtotal' => 'decimal:2', 'line_total' => 'decimal:2', 'line_cost' => 'decimal:2', 'line_gross_profit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Sale snapshots are immutable.'));
        static::deleting(fn () => throw new \LogicException('Sale snapshots are permanent.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
