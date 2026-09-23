<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('security_version')->default(1);
            $table->boolean('must_change_password')->default(false);
        });
        Schema::table('roles', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(1));
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['revision', 'security_version', 'must_change_password']));
    }
};
