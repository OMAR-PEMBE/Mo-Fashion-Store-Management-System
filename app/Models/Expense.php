<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['request_key', 'request_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Use ExpenseService for expenses.'));
        static::deleting(fn () => throw new \LogicException('Expense history is permanent.'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
