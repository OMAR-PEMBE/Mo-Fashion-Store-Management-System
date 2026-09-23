<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    private function authorize(User $actor, string $permission): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh, 403);
        Gate::forUser($fresh)->authorize('expenses.view');
        Gate::forUser($fresh)->authorize($permission);
    }

    public function save(array $input, User $actor, ?Expense $expense = null): Expense
    {
        $this->authorize($actor, $expense ? 'expenses.update' : 'expenses.create');
        $data = Validator::make($input, [
            'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'description' => ['nullable', 'string', 'max:5000'],
            'request_key' => [$expense ? 'exclude' : 'required', 'string', 'max:100'],
            'revision' => [$expense ? 'required' : 'exclude', 'integer', 'min:1'],
            'purchase_id' => ['prohibited'], 'supplier_id' => ['prohibited'], 'items' => ['prohibited'],
        ])->validate();
        $data['expense_category_id'] = (int) $data['expense_category_id'];
        $data['amount'] = (string) BigDecimal::of((string) $data['amount'])->toScale(2);
        $data['description'] = trim($data['description'] ?? '') ?: null;
        $key = $data['request_key'] ?? null;
        $revision = $data['revision'] ?? null;
        unset($data['request_key'], $data['revision'], $data['purchase_id'], $data['supplier_id'], $data['items']);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $key, $revision, $hash, $actor, $expense) {
            // Serializes creation retries and category edits with a consistent lock order.
            $sequence = DB::table('document_sequences')->where('document_type', 'EXPENSE')->lockForUpdate()->first();
            if (! $expense && ($existing = Expense::where('request_key', $key)->first())) {
                abort_unless($existing->request_hash === $hash && $existing->recorded_by === $actor->id, 409, 'This request was already used for another expense.');

                return $existing;
            }
            $current = $expense ? Expense::lockForUpdate()->findOrFail($expense->id) : null;
            abort_if($current && $current->revision !== (int) $revision, 409, 'This expense changed. Reload it before editing.');
            $category = ExpenseCategory::whereKey($data['expense_category_id'])->lockForUpdate()->first();
            if (! $category || (! $category->is_active && $current?->expense_category_id !== $category->id)) {
                throw ValidationException::withMessages(['expense_category_id' => 'Choose an active expense category.']);
            }
            $before = $current ? $this->snapshot($current) : null;
            if ($current) {
                DB::table('expenses')->where('id', $current->id)->update($data + ['revision' => $current->revision + 1, 'updated_at' => now()]);
                $id = $current->id;
            } else {
                $number = $sequence->current_number + 1;
                DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
                $id = DB::table('expenses')->insertGetId($data + ['expense_number' => $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'request_key' => $key, 'request_hash' => $hash, 'recorded_by' => $actor->id, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $result = Expense::findOrFail($id);
            $this->audit($actor, $current ? 'UPDATE_EXPENSE' : 'CREATE_EXPENSE', 'expense', $id, $before, $this->snapshot($result));

            return $result;
        }, 3);
    }

    public function saveCategory(array $input, User $actor, ?ExpenseCategory $category = null): ExpenseCategory
    {
        $this->authorize($actor, 'expense-categories.manage');
        if (isset($input['name']) && is_string($input['name'])) {
            $input['name'] = trim($input['name']);
        }

        return DB::transaction(function () use ($input, $actor, $category) {
            DB::table('document_sequences')->where('document_type', 'EXPENSE')->lockForUpdate()->first();
            $current = $category ? ExpenseCategory::lockForUpdate()->findOrFail($category->id) : null;
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:150', Rule::unique('expense_categories')->ignore($current?->id)],
                'description' => ['nullable', 'string', 'max:5000'], 'is_active' => ['required', 'boolean'],
                'revision' => [$current ? 'required' : 'exclude', 'integer', 'min:1'],
            ])->validate();
            abort_if($current && $current->revision !== (int) $data['revision'], 409, 'This category changed. Reload it before editing.');
            $before = $current?->only(['name', 'description', 'is_active', 'revision']);
            unset($data['revision']);
            $data['description'] = trim($data['description'] ?? '') ?: null;
            $data['is_active'] = (bool) $data['is_active'];
            $data['revision'] = $current ? $current->revision + 1 : 1;
            $data['updated_at'] = now();
            if ($current) {
                DB::table('expense_categories')->where('id', $current->id)->update($data);
                $id = $current->id;
            } else {
                $id = DB::table('expense_categories')->insertGetId($data + ['created_at' => now()]);
            }
            $result = ExpenseCategory::findOrFail($id);
            $this->audit($actor, $current ? 'UPDATE_EXPENSE_CATEGORY' : 'CREATE_EXPENSE_CATEGORY', 'expense_category', $id, $before, $result->only(['name', 'description', 'is_active', 'revision']));

            return $result;
        }, 3);
    }

    private function snapshot(Expense $expense): array
    {
        return $expense->only(['expense_number', 'expense_category_id', 'amount', 'description', 'recorded_by', 'revision']) + ['expense_date' => $expense->expense_date->format('Y-m-d'), 'category_name' => $expense->category->name];
    }

    private function audit(User $actor, string $action, string $type, int $id, ?array $before, array $after): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_values' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'new_values' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
