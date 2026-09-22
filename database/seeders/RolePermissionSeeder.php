<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (Permissions::ALL as $slug) {
                Permission::firstOrCreate(['slug' => $slug], ['name' => Str::headline(str_replace('.', ' ', $slug))]);
            }

            // Only initialize new roles; reruns preserve deliberate permission changes.
            foreach (['administrator' => Permissions::ALL, 'salesperson' => Permissions::SALESPERSON] as $slug => $permissions) {
                $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
                if ($role->wasRecentlyCreated) {
                    $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));
                }
            }
        });
    }
}
