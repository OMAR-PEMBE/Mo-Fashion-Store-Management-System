<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 50)->index();
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('proof_type', 50);
            $table->string('proof_reference', 191);
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            foreach (['approved_by', 'completed_by', 'rejected_by'] as $field) {
                $table->foreignId($field)->nullable()->constrained('users')->restrictOnDelete();
            }
            $table->dateTime('return_date');
            foreach (['approved_at', 'completed_at', 'rejected_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->string('rejection_reason', 255)->nullable();
            $table->decimal('total_cost_adjustment', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition', 50);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('cost_adjustment', 15, 2);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->boolean('returned_to_stock')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['return_id', 'sale_item_id']);
        });
        DB::table('document_sequences')->insert(['document_type' => 'RETURN', 'prefix' => 'MFS-RET-', 'current_number' => 0, 'updated_at' => now()]);
        foreach (['returns.create', 'returns.approve'] as $slug) {
            $permission = DB::table('permissions')->insertGetId(['name' => str_replace('.', ' ', $slug), 'slug' => $slug]);
            foreach (DB::table('roles')->whereIn('slug', ['administrator', 'salesperson'])->pluck('id') as $role) {
                DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        DB::table('document_sequences')->where('document_type', 'RETURN')->delete();
        DB::table('permissions')->whereIn('slug', ['returns.create', 'returns.approve'])->delete();
    }
};
