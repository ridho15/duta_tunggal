<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('warehouse_id')->nullable()->change();
            $table->string('username', 50)->unique();
            $table->string('telepon', 20)->nullable();
            $table->enum('manage_type', ['all', 'cabang', 'warehouse'])->default('all');
            $table->integer('cabang_id')->nullable();
            $table->string('first_name', 50);
            $table->string('last_name', 50)->nullable();
            $table->boolean('status')->default(true);
            $table->string('kode_user');
            $table->string('posisi', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
