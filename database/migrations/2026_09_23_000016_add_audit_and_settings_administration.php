<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['audit.view' => 'View audit logs', 'settings.manage' => 'Manage business settings'] as $slug => $name) {
            DB::table('permissions')->insertOrIgnore(compact('slug', 'name'));
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['action', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['action', 'id']);
        });
        DB::table('permissions')->whereIn('slug', ['audit.view', 'settings.manage'])->delete();
    }
};
