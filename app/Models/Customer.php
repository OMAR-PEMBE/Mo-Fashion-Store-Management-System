<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['full_name', 'phone', 'whatsapp_number', 'location', 'preferred_size_id', 'preferred_colour_id', 'notes', 'marketing_opt_in', 'is_active'];

    protected function casts(): array
    {
        return ['marketing_opt_in' => 'boolean', 'is_active' => 'boolean', 'total_purchases' => 'integer', 'total_spent' => 'decimal:2', 'first_purchase_at' => 'datetime', 'last_purchase_at' => 'datetime'];
    }

    public function preferredSize(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'preferred_size_id');
    }

    public function preferredColour(): BelongsTo
    {
        return $this->belongsTo(Colour::class, 'preferred_colour_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'customer_category_preferences')->withTrashed();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
