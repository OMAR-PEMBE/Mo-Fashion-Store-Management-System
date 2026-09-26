<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Salespeople sell at catalogue price; changing a price is an administrator permission.
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            if (! DB::table('permissions')->where('slug', 'sales.override_price')->exists()) {
                DB::table('permissions')->insert(['slug' => 'sales.override_price', 'name' => 'Sales Override Price']);
            }
            $permission = DB::table('permissions')->where('slug', 'sales.override_price')->value('id');
            $role = DB::table('roles')->where('slug', 'administrator')->value('id');
            if ($role && ! DB::table('role_permissions')->where(['role_id' => $role, 'permission_id' => $permission])->exists()) {
                DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
            }
        });
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('slug', 'sales.override_price')->value('id');
        DB::table('role_permissions')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
    }
};
