<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 191);
            $table->string('product_code', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('default_selling_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('colour_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sku', 150)->unique();
            $table->decimal('selling_price', 15, 2);
            $table->decimal('weighted_average_cost', 15, 2)->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            // SQL unique indexes otherwise permit repeated NULL combinations.
            $table->unsignedBigInteger('size_key')->storedAs('coalesce(size_id, 0)');
            $table->unsignedBigInteger('colour_key')->storedAs('coalesce(colour_id, 0)');
            $table->unique(['product_id', 'size_key', 'colour_key'], 'variants_combination_unique');
        });
        $permission = DB::table('permissions')->insertGetId(['name' => 'View product cost', 'slug' => 'products.view_cost']);
        foreach (DB::table('roles')->where('slug', 'administrator')->pluck('id') as $role) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'products.view_cost')->delete();
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
