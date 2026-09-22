<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['supplier_code', 'name', 'contact_person', 'phone', 'email', 'location', 'notes', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // Phase 7 integration point: purchases(): HasMany using purchases.supplier_id.
    // Add the relation when Purchase and its table exist; never fake purchase history.
}
