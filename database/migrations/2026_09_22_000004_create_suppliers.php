<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 100)->unique();
            $table->string('name', 191);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        $permission = DB::table('permissions')->insertGetId(['name' => 'Manage suppliers', 'slug' => 'suppliers.manage']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'suppliers.manage')->delete();
        Schema::dropIfExists('suppliers');
    }
};
