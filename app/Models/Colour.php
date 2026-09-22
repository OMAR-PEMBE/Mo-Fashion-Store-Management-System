<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colour extends Model
{
    protected $fillable = ['name', 'code', 'hex_code', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
