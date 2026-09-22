<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Inventory extends Model
{
    public const CREATED_AT = null;

    protected $guarded = ['*'];

    protected $appends = ['available_quantity'];

    protected function casts(): array
    {
        return ['physical_quantity' => 'integer', 'reserved_quantity' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Use InventoryService to change inventory.'));
        static::deleting(fn () => throw new LogicException('Inventory balances cannot be deleted.'));
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->physical_quantity - $this->reserved_quantity;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
