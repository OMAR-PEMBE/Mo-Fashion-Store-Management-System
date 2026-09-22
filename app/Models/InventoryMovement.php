<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['movement_type' => InventoryMovementType::class, 'quantity_change' => 'integer',
            'physical_quantity_before' => 'integer', 'physical_quantity_after' => 'integer',
            'reserved_quantity_before' => 'integer', 'reserved_quantity_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Inventory movements are written only by InventoryService.'));
        static::deleting(fn () => throw new LogicException('The inventory ledger is permanent.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
