<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 100)->unique();
            $table->string('request_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('refund_method', 50)->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->string('reason', 255);
            $table->string('status', 50)->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            foreach (['approved_by', 'processed_by', 'closed_by'] as $field) {
                $table->foreignId($field)->nullable()->constrained('users')->restrictOnDelete();
            }
            foreach (['approved_at', 'processed_at', 'closed_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->string('closure_reason', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamp('created_at');
            $table->unique(['refund_id', 'sale_item_id']);
        });
        DB::table('document_sequences')->insert(['document_type' => 'REFUND', 'prefix' => 'MFS-REF-', 'current_number' => 0, 'updated_at' => now()]);
        $complete = DB::table('permissions')->insertGetId(['name' => 'Complete refunds', 'slug' => 'refunds.complete']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $complete]);
        }
        $create = DB::table('permissions')->where('slug', 'refunds.create')->value('id');
        if (! $create) {
            $create = DB::table('permissions')->insertGetId(['name' => 'Request refunds', 'slug' => 'refunds.create']);
        }
        foreach (DB::table('roles')->where('slug', 'salesperson')->pluck('id') as $role) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $create]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        DB::table('document_sequences')->where('document_type', 'REFUND')->delete();
        DB::table('permissions')->where('slug', 'refunds.complete')->delete();
    }
};
