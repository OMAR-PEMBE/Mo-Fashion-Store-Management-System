<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 100)->unique();
            $table->string('full_name', 191)->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('whatsapp_number', 30)->nullable()->index();
            $table->string('location', 255)->nullable();
            $table->foreignId('preferred_size_id')->nullable()->constrained('sizes')->restrictOnDelete();
            $table->foreignId('preferred_colour_id')->nullable()->constrained('colours')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('first_purchase_at')->nullable();
            $table->dateTime('last_purchase_at')->nullable();
            $table->unsignedInteger('total_purchases')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_category_preferences', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->primary(['customer_id', 'category_id']);
        });
        DB::table('document_sequences')->insert(['document_type' => 'CUSTOMER', 'prefix' => 'MFS-CUS-', 'current_number' => 0, 'updated_at' => now()]);
        $permission = DB::table('permissions')->insertGetId(['name' => 'Manage customers', 'slug' => 'customers.manage']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'customers.manage')->delete();
        DB::table('document_sequences')->where('document_type', 'CUSTOMER')->delete();
        Schema::dropIfExists('customer_category_preferences');
        Schema::dropIfExists('customers');
    }
};
