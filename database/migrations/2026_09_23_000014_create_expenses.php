<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(1));
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('expense_date')->index();
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        DB::table('document_sequences')->insert(['document_type' => 'EXPENSE', 'prefix' => 'MFS-EXP-', 'current_number' => 0, 'updated_at' => now()]);
        foreach (['expenses.view', 'expenses.create', 'expenses.update', 'expense-categories.manage'] as $slug) {
            $id = DB::table('permissions')->insertGetId(['name' => $slug, 'slug' => $slug]);
            foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
                DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('revision'));
        DB::table('document_sequences')->where('document_type', 'EXPENSE')->delete();
        DB::table('permissions')->whereIn('slug', ['expenses.view', 'expenses.create', 'expenses.update', 'expense-categories.manage'])->delete();
    }
};
