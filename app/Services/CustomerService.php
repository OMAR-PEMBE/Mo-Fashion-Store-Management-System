<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function save(array $input, User $actor, ?Customer $customer = null): Customer
    {
        $fresh = $actor->fresh();
        abort_unless($fresh, 403);
        Gate::forUser($fresh)->authorize($customer ? 'customers.manage' : 'customers.create');
        foreach (['full_name', 'phone', 'whatsapp_number', 'location', 'notes'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $data = Validator::make($input, [
            'full_name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'location' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
            'preferred_size_id' => ['nullable', 'integer'], 'preferred_colour_id' => ['nullable', 'integer'],
            'category_ids' => ['sometimes', 'array', 'max:100'], 'category_ids.*' => ['integer', 'distinct'],
            'marketing_opt_in' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'], 'allow_duplicate' => ['sometimes', 'boolean'],
        ])->validate();
        foreach (['phone', 'whatsapp_number'] as $field) {
            $number = preg_replace('/\D/', '', $data[$field] ?? '');
            if (str_starts_with($number, '00')) {
                $number = substr($number, 2);
            }
            if ($number !== '' && (strlen($number) < 7 || strlen($number) > 15)) {
                throw ValidationException::withMessages([$field => 'Enter a phone number containing 7–15 digits.']);
            }
            $data[$field] = $number === '' ? null : $number;
        }

        return DB::transaction(function () use ($customer, $data, $actor) {
            // Serialize contact checks and numbering, including concurrent registrations.
            $sequence = DB::table('document_sequences')->where('document_type', 'CUSTOMER')->lockForUpdate()->first();
            $customer = $customer ? Customer::lockForUpdate()->findOrFail($customer->id) : new Customer;
            $before = $customer->exists ? ['marketing_opt_in' => $customer->marketing_opt_in, 'is_active' => $customer->is_active] : null;
            $contacts = array_filter([$data['phone'], $data['whatsapp_number']]);
            if ($contacts && ! ($data['allow_duplicate'] ?? false)) {
                $duplicate = Customer::withTrashed()->where(fn ($q) => $q->whereIn('phone', $contacts)->orWhereIn('whatsapp_number', $contacts))
                    ->when($customer->exists, fn ($q) => $q->where('id', '!=', $customer->id))->lockForUpdate()->first();
                if ($duplicate) {
                    throw ValidationException::withMessages(['allow_duplicate' => 'Possible duplicate: '.$duplicate->customer_code.' ('.$duplicate->full_name.'). Search existing customers first, or explicitly confirm a separate profile below.']);
                }
            }
            foreach (['preferred_size_id' => Size::class, 'preferred_colour_id' => Colour::class] as $field => $model) {
                $data[$field] ??= null;
                if ($data[$field] && $data[$field] != $customer->$field && ! $model::whereKey($data[$field])->where('is_active', true)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([$field => 'Select an active preference or leave it blank.']);
                }
            }
            $categories = $data['category_ids'] ?? [];
            $newCategories = array_diff($categories, $customer->exists ? $customer->categories()->pluck('categories.id')->all() : []);
            if (Category::whereIn('id', $newCategories)->where('is_active', true)->lockForUpdate()->get()->count() !== count($newCategories)) {
                throw ValidationException::withMessages(['category_ids' => 'Select active categories.']);
            }
            $acknowledged = (bool) ($data['allow_duplicate'] ?? false);
            unset($data['category_ids'], $data['allow_duplicate']);
            $data += ['marketing_opt_in' => false, 'is_active' => true, 'location' => null, 'notes' => null];
            if (! $customer->exists) {
                $number = $sequence->current_number + 1;
                DB::table('document_sequences')->where('id', $sequence->id)->update(['current_number' => $number, 'updated_at' => now()]);
                $customer->customer_code = $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                $data['is_active'] = true;
            }
            $customer->fill($data);
            $changedFields = array_keys($customer->getDirty());
            $customer->save();
            $customer->categories()->sync($categories);
            DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $before ? 'UPDATE_CUSTOMER' : 'CREATE_CUSTOMER', 'entity_type' => 'customer', 'entity_id' => $customer->id,
                'old_values' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'new_values' => json_encode(['marketing_opt_in' => $customer->marketing_opt_in, 'is_active' => $customer->is_active, 'duplicate_acknowledged' => $acknowledged, 'changed_fields' => $changedFields, 'category_ids' => $categories], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $customer->fresh();
        }, 3);
    }
}
