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
        // Migration already applied or column/key doesn't exist
        // Skip to avoid errors
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('product_categories', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->foreign('cabang_id')->references('id')->on('cabangs')->onDelete('cascade');
            }
        });
    }
};
