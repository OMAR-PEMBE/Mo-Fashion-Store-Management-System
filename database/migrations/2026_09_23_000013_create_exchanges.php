<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 50)->index();
            foreach (['total_return_value', 'total_replacement_value', 'amount_due', 'refund_due'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->foreignId('refund_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->string('reason', 255);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained()->restrictOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition', 50)->nullable();
            foreach (['unit_value', 'line_total', 'applied_credit', 'refund_amount'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('line_cost', 15, 2)->nullable();
            $table->timestamp('created_at');
            $table->unique(['exchange_id', 'item_type', 'product_variant_id']);
        });
        DB::table('document_sequences')->insert(['document_type' => 'EXCHANGE', 'prefix' => 'MFS-EXC-', 'current_number' => 0, 'updated_at' => now()]);
        $permission = DB::table('permissions')->insertGetId(['name' => 'Create exchanges', 'slug' => 'exchanges.create']);
        foreach (DB::table('roles')->whereIn('slug', ['administrator', 'salesperson'])->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
        Schema::dropIfExists('exchanges');
        DB::table('document_sequences')->where('document_type', 'EXCHANGE')->delete();
        DB::table('permissions')->where('slug', 'exchanges.create')->delete();
    }
};
