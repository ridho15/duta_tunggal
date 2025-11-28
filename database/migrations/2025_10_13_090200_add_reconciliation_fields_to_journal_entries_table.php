<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_recon_id')->nullable()->after('source_id');
            $table->enum('bank_recon_status', ['matched', 'cleared'])->nullable()->after('bank_recon_id');
            $table->date('bank_recon_date')->nullable()->after('bank_recon_status');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['bank_recon_id', 'bank_recon_status', 'bank_recon_date']);
        });
    }
};
