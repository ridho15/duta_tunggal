<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_id')->nullable();
        });

        // Assign default cabang_id to existing records
        DB::table('suppliers')->update(['cabang_id' => 1]);

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_id')->nullable(false)->change();
            $table->foreign('cabang_id')->references('id')->on('cabangs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            //
        });
    }
};
