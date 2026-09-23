<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessSettingsService
{
    public const DEFAULTS = [
        'business_name' => 'Mo Fashion Store', 'business_phone' => '', 'business_address' => '',
        'currency' => 'TZS', 'timezone' => 'Africa/Dar_es_Salaam', 'low_stock_default' => '2',
        'receipt_footer' => 'Thank you for shopping with us.',
    ];

    public function values(): array
    {
        return array_replace(self::DEFAULTS, SystemSetting::whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all());
    }

    public function revision(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function authorize(User $actor): void
    {
        $actor = $actor->fresh();
        abort_unless($actor?->canAccessWorkspace() && $actor->role->slug === 'administrator' && ! $actor->must_change_password, 403);
        Gate::forUser($actor)->authorize('settings.manage');
    }

    public function save(array $input, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($input, $actor) {
            // Serialize settings with administrator/permission changes.
            Role::where('slug', 'administrator')->lockForUpdate()->firstOrFail();
            $actor = User::lockForUpdate()->findOrFail($actor->id);
            $this->authorize($actor);
            $data = Validator::make($input, [
                'business_name' => ['required', 'string', 'max:150'],
                'business_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
                'business_address' => ['nullable', 'string', 'max:500'], 'receipt_footer' => ['nullable', 'string', 'max:500'],
                'low_stock_default' => ['required', 'integer', 'between:0,2147483647'],
                'currency' => ['required', Rule::in(['TZS'])], 'timezone' => ['required', Rule::in(['Africa/Dar_es_Salaam'])],
                'revision' => ['required', 'string', 'size:64'], 'current_password' => ['required', 'string', 'max:255'],
            ])->validate();
            if (! Hash::check($data['current_password'], $actor->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }
            SystemSetting::whereIn('key', array_keys(self::DEFAULTS))->orderBy('key')->lockForUpdate()->get();
            $before = $this->values();
            abort_unless(hash_equals($this->revision($before), $data['revision']), 409, 'Settings changed. Reload and review before saving.');
            $after = [];
            foreach (self::DEFAULTS as $key => $default) {
                $after[$key] = trim((string) ($data[$key] ?? ''));
                $setting = SystemSetting::firstOrNew(['key' => $key]);
                $setting->forceFill(['value' => $after[$key], 'type' => $key === 'low_stock_default' ? 'integer' : 'string', 'updated_by' => $actor->id])->save();
            }
            if ($after !== $before) {
                app(AuditService::class)->record($actor, 'UPDATE_SETTINGS', 'system_settings', null, $before, $after);
            }
        }, 3);
    }
}
