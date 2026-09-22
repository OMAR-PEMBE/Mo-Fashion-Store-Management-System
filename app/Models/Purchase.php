<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => PurchaseStatus::class, 'purchase_date' => 'date', 'confirmed_at' => 'datetime',
            'subtotal' => 'decimal:2', 'total_amount' => 'decimal:2', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use PurchaseService to write purchases.'));
        static::deleting(fn () => throw new \LogicException('Purchase history cannot be deleted.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
