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
        // SQLite doesn't support modifying enum directly, so we need to recreate the column
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('bank_recon_status');
        });
        
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('bank_recon_status', ['matched', 'cleared', 'confirmed'])->nullable()->after('bank_recon_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('bank_recon_status');
        });
        
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->enum('bank_recon_status', ['matched', 'cleared'])->nullable()->after('bank_recon_id');
        });
    }
};
