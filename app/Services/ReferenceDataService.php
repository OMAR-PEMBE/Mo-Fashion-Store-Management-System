<?php

namespace App\Services;

use App\Enums\ReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceDataService
{
    public function save(ReferenceType $type, array $input, ?Model $record = null): Model
    {
        $model = $type->model();
        $record ??= new $model;
        if (! $record instanceof $model) {
            throw new \InvalidArgumentException('Reference type does not match the record.');
        }
        $identifier = $type->identifier();
        foreach (['name', 'slug', 'code', 'description', 'hex_code'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input[$identifier]) && is_string($input[$identifier])) {
            $input[$identifier] = $identifier === 'slug' ? Str::lower($input[$identifier]) : Str::upper($input[$identifier]);
        }
        if (isset($input['hex_code']) && is_string($input['hex_code'])) {
            $input['hex_code'] = Str::upper($input['hex_code']);
        }
        $nameLength = match ($type) {
            ReferenceType::Categories => 150,
            ReferenceType::Sizes => 50,
            ReferenceType::Colours => 100,
        };
        $rules = [
            'name' => ['required', 'string', 'max:'.$nameLength],
            'is_active' => ['required', 'boolean'],
            $identifier => ['required', 'string', 'max:'.($identifier === 'slug' ? 160 : $nameLength),
                $identifier === 'slug' ? 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/' : 'regex:/^[A-Z0-9]+(?:[-_][A-Z0-9]+)*$/',
                Rule::unique($record->getTable(), $identifier)->ignore($record->getKey())],
        ];
        $rules += match ($type) {
            ReferenceType::Categories => ['description' => ['nullable', 'string', 'max:5000']],
            ReferenceType::Sizes => ['sort_order' => ['required', 'integer', 'between:0,2147483647']],
            ReferenceType::Colours => ['hex_code' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/']],
        };
        $data = Validator::make($input, $rules)->validate();
        try {
            $record->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request may claim the identifier after validation.
            throw ValidationException::withMessages([$identifier => 'This '.$identifier.' is already in use.']);
        }

        return $record;
    }
}
