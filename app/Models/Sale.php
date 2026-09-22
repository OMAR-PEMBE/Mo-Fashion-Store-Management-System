<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['request_hash', 'request_key', 'total_cogs', 'gross_profit'];

    protected function casts(): array
    {
        return ['status' => SaleStatus::class, 'sale_date' => 'datetime', 'completed_at' => 'datetime', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'total_amount' => 'decimal:2', 'total_cogs' => 'decimal:2', 'gross_profit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use SaleService for financial transactions.'));
        static::deleting(fn () => throw new \LogicException('Sales history cannot be deleted.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }
}
