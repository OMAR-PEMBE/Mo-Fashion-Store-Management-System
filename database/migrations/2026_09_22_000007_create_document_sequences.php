<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50)->unique();
            $table->string('prefix', 50);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamp('updated_at');
        });
        DB::table('document_sequences')->insert(['document_type' => 'PURCHASE', 'prefix' => 'MFS-PUR-', 'current_number' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
