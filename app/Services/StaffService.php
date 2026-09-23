<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function authorize(User $actor): User
    {
        $fresh = $actor->fresh();
        abort_unless($fresh?->is_active && $fresh->role?->slug === 'administrator' && ! $fresh->must_change_password, 403);
        Gate::forUser($fresh)->authorize('users.manage');

        return $fresh;
    }

    private function lockAndConfirm(User $actor, array $input): User
    {
        // One mutex across staff/role changes protects administrator access under races.
        Role::where('slug', 'administrator')->lockForUpdate()->firstOrFail();
        $actor = User::lockForUpdate()->findOrFail($actor->id);
        $this->authorize($actor);
        Validator::make($input, ['current_password' => ['required', 'string', 'max:255']])->validate();
        if (! Hash::check($input['current_password'], $actor->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        return $actor;
    }

    public function save(array $input, User $actor, ?User $target = null): User
    {
        $this->authorize($actor);
        foreach (['name', 'email', 'phone'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower($input['email']);
        }

        return DB::transaction(function () use ($input, $actor, $target) {
            $actor = $this->lockAndConfirm($actor, $input);
            $current = $target ? User::lockForUpdate()->findOrFail($target->id) : null;
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($current?->id)],
                'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
                'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereIn('slug', ['administrator', 'salesperson'])],
                'is_active' => ['required', 'boolean'], 'revision' => [$current ? 'required' : 'exclude', 'integer', 'min:1'],
                'password' => [$current ? 'exclude' : 'required', 'string', 'confirmed', 'max:255', Password::defaults()],
            ])->validate();
            abort_if($current && $current->revision !== (int) $data['revision'], 409, 'This account changed. Reload before editing.');
            if ($current?->id === $actor->id && (! $data['is_active'] || (int) $data['role_id'] !== $actor->role_id)) {
                throw ValidationException::withMessages(['role_id' => 'Another administrator must change your role or deactivate your account.']);
            }
            $before = $current ? $this->snapshot($current) : null;
            $securityChanged = $current && ($current->email !== $data['email'] || $current->role_id !== (int) $data['role_id'] || $current->is_active !== (bool) $data['is_active']);
            $user = $current ?? new User;
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'role_id' => (int) $data['role_id'], 'is_active' => (bool) $data['is_active'], 'revision' => $current ? $current->revision + 1 : 1]);
            if (! $current) {
                $user->forceFill(['password' => $data['password'], 'must_change_password' => true, 'security_version' => 1]);
            }
            if ($securityChanged) {
                $user->forceFill(['security_version' => $current->security_version + 1, 'remember_token' => Str::random(60)]);
            }
            if ($current && $current->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            $this->preserveAdministrator();
            if ($securityChanged) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->whereIn('email', array_filter([$before['email'], $user->email]))->delete();
            }
            $this->audit($actor, $current ? 'UPDATE_STAFF' : 'CREATE_STAFF', 'user', $user->id, $before, $this->snapshot($user));

            return $user->fresh();
        }, 3);
    }

    public function resetPassword(User $target, array $input, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($target, $input, $actor) {
            $actor = $this->lockAndConfirm($actor, $input);
            $target = User::lockForUpdate()->findOrFail($target->id);
            abort_if($target->id === $actor->id, 422, 'Use your profile to change your own password.');
            $data = Validator::make($input, ['revision' => ['required', 'integer', 'min:1'], 'password' => ['required', 'string', 'confirmed', 'max:255', Password::defaults()]])->validate();
            abort_if($target->revision !== (int) $data['revision'], 409, 'This account changed. Reload before resetting its password.');
            $target->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60), 'must_change_password' => true,
                'revision' => $target->revision + 1, 'security_version' => $target->security_version + 1])->save();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $this->audit($actor, 'RESET_STAFF_PASSWORD', 'user', $target->id, null, ['must_change_password' => true, 'revision' => $target->revision]);
        }, 3);
    }

    public function permissions(Role $role, array $input, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($role, $input, $actor) {
            $actor = $this->lockAndConfirm($actor, $input);
            $role = Role::lockForUpdate()->findOrFail($role->id);
            $data = Validator::make($input, ['revision' => ['required', 'integer', 'min:1'], 'permissions' => ['present', 'array'],
                'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')]])->validate();
            abort_if($role->revision !== (int) $data['revision'], 409, 'This role changed. Reload before saving permissions.');
            $slugs = Permission::whereIn('id', $data['permissions'])->orderBy('slug')->pluck('slug')->all();
            if ($role->slug !== 'administrator' && array_intersect($slugs, ['users.manage', 'refunds.approve', 'refunds.complete', 'audit.view', 'settings.manage'])) {
                throw ValidationException::withMessages(['permissions' => 'Staff administration, audit logs, settings and refund approval/completion require the Administrator role.']);
            }
            $before = $role->permissions()->orderBy('slug')->pluck('slug')->all();
            $role->permissions()->sync($data['permissions']);
            $this->preserveAdministrator();
            $role->revision++;
            $role->save();
            $this->audit($actor, 'UPDATE_ROLE_PERMISSIONS', 'role', $role->id, ['permissions' => $before], ['permissions' => $slugs, 'revision' => $role->revision]);
        }, 3);
    }

    private function preserveAdministrator(): void
    {
        if (! User::where('is_active', true)->whereHas('role', fn ($q) => $q->where('slug', 'administrator')->whereHas('permissions', fn ($q) => $q->where('slug', 'users.manage')))->exists()) {
            throw ValidationException::withMessages(['permissions' => 'Keep at least one active administrator with staff-management access.']);
        }
    }

    private function snapshot(User $user): array
    {
        return $user->only(['name', 'email', 'phone', 'role_id', 'is_active', 'revision']);
    }

    private function audit(User $actor, string $action, string $type, int $id, ?array $before, array $after): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_values' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'new_values' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
