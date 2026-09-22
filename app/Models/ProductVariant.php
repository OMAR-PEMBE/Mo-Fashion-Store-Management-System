<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = ['size_id', 'colour_id', 'sku', 'selling_price', 'low_stock_threshold', 'is_active'];

    protected $attributes = ['is_active' => true, 'weighted_average_cost' => 0, 'low_stock_threshold' => 2];

    protected $hidden = ['weighted_average_cost', 'size_key', 'colour_key'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'selling_price' => 'decimal:2', 'weighted_average_cost' => 'decimal:2', 'low_stock_threshold' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('size_id')->orWhereHas('size', fn ($size) => $size->where('is_active', true)))
            ->where(fn ($q) => $q->whereNull('colour_id')->orWhereHas('colour', fn ($colour) => $colour->where('is_active', true)));
    }
}
