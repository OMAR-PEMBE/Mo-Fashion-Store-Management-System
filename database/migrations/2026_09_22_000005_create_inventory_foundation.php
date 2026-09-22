<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('physical_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->string('movement_type', 50);
            $table->integer('quantity_change');
            $table->integer('physical_quantity_before');
            $table->integer('physical_quantity_after');
            $table->integer('reserved_quantity_before');
            $table->integer('reserved_quantity_after');
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['product_variant_id', 'id']);
            $table->index(['product_variant_id', 'reference_type', 'reference_id'], 'inventory_reference_index');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventory_valid_balances CHECK (physical_quantity <= 2147483647 AND reserved_quantity <= physical_quantity)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_valid_movement CHECK (physical_quantity_before >= 0 AND physical_quantity_after >= 0 AND reserved_quantity_before >= 0 AND reserved_quantity_after >= 0 AND reserved_quantity_before <= physical_quantity_before AND reserved_quantity_after <= physical_quantity_after AND quantity_change = physical_quantity_after - physical_quantity_before)');
        }
        DB::table('inventories')->insertUsing(['product_variant_id', 'physical_quantity', 'reserved_quantity', 'updated_at'],
            DB::table('product_variants')->selectRaw('id, 0, 0, CURRENT_TIMESTAMP'));
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
    }
};
