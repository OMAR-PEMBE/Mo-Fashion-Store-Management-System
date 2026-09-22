<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['unit_cost', 'cost_adjustment'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'returned_to_stock' => 'boolean', 'unit_cost' => 'decimal:2', 'cost_adjustment' => 'decimal:2', 'refund_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use ReturnService for return items.'));
        static::deleting(fn () => throw new \LogicException('Return item history is permanent.'));
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
