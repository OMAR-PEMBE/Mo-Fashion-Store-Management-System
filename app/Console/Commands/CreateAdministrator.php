<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'app:create-administrator';

    protected $description = 'Create a staff administrator using interactive, hidden password input';

    public function handle(): int
    {
        $role = Role::where('slug', 'administrator')->first();
        if (! $role) {
            $this->error('Run migrations and the RolePermissionSeeder first.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->ask('Name'),
            'email' => Str::lower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (at least 10 characters, including letters and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'max:255', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
        $user->role()->associate($role);
        $user->is_active = true;
        $user->save();
        $this->info('Administrator created. You can now sign in.');

        return self::SUCCESS;
    }
}
