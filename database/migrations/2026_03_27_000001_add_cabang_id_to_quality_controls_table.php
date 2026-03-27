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
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->foreignId('cabang_id')
                ->nullable()
                ->after('purchase_return_processed')
                ->constrained('cabangs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cabang_id');
        });
    }
};