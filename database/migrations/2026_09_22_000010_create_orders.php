<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 50)->index();
            $table->string('payment_status', 50);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 191)->nullable();
            foreach (['subtotal', 'discount_total', 'total_amount'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->string('delivery_address', 255)->nullable();
            $table->text('notes')->nullable();
            foreach (['confirmed_at', 'payment_received_at', 'delivered_at', 'cancelled_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            foreach (['unit_price', 'discount_amount', 'line_total'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['order_id', 'product_variant_id']);
        });
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 50)->index();
            $table->dateTime('reserved_at');
            foreach (['expires_at', 'released_at', 'completed_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->unique('order_id');
        });
        DB::table('document_sequences')->insert(['document_type' => 'ORDER', 'prefix' => 'MFS-ORD-', 'current_number' => 0, 'updated_at' => now()]);
        $permission = DB::table('permissions')->insertGetId(['name' => 'Manage all orders', 'slug' => 'orders.manage']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropUnique(['order_id']);
        });
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        DB::table('document_sequences')->where('document_type', 'ORDER')->delete();
        DB::table('permissions')->where('slug', 'orders.manage')->delete();
    }
};
