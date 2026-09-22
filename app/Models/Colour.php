<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Colour extends Model
{
    protected $fillable = ['name', 'code', 'hex_code', 'is_active'];

    protected $attributes = ['is_active' => true];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
