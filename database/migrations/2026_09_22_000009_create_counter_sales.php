<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id')->nullable(); // Order FK is added with Phase 11.
            $table->foreignId('salesperson_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('sale_date')->index();
            $table->string('payment_method', 50);
            $table->string('payment_reference', 191)->nullable();
            foreach (['subtotal', 'discount_total', 'total_amount', 'total_cogs', 'gross_profit'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->string('status', 50)->index();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'sale_date']);
            $table->index(['salesperson_id', 'sale_date']);
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            foreach (['unit_price', 'unit_cost', 'discount_amount', 'line_subtotal', 'line_total', 'line_cost', 'line_gross_profit'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['sale_id', 'product_variant_id']);
        });
        DB::table('document_sequences')->insert(['document_type' => 'SALE', 'prefix' => 'MFS-SAL-', 'current_number' => 0, 'updated_at' => now()]);
        $permission = DB::table('permissions')->insertGetId(['name' => 'Cancel sales', 'slug' => 'sales.cancel']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'sales.cancel')->delete();
        DB::table('document_sequences')->where('document_type', 'SALE')->delete();
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
