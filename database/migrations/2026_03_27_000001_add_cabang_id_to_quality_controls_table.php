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
        if (! Schema::hasTable('quality_controls') || Schema::hasColumn('quality_controls', 'cabang_id')) {
            return;
        }

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
        if (! Schema::hasTable('quality_controls') || ! Schema::hasColumn('quality_controls', 'cabang_id')) {
            return;
        }

        Schema::table('quality_controls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cabang_id');
        });
    }
};