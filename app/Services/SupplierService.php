<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function save(array $input, ?Supplier $supplier = null): Supplier
    {
        $supplier ??= new Supplier;
        foreach (['supplier_code', 'name', 'contact_person', 'phone', 'email', 'location', 'notes'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['supplier_code']) && is_string($input['supplier_code'])) {
            $input['supplier_code'] = Str::upper($input['supplier_code']);
        }
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower($input['email']);
        }
        $data = Validator::make($input, [
            'supplier_code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/', Rule::unique('suppliers')->ignore($supplier->id)],
            'name' => ['required', 'string', 'max:191'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ])->validate();
        try {
            $supplier->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['supplier_code' => 'This supplier code is already in use.']);
        }

        return $supplier;
    }
}
