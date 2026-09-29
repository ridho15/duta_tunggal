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
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'nama_bank')) {
                $table->string('nama_bank', 100)->nullable()->after('keterangan');
            }
            if (! Schema::hasColumn('suppliers', 'nomor_rekening')) {
                $table->string('nomor_rekening', 100)->nullable()->after('nama_bank');
            }
            if (! Schema::hasColumn('suppliers', 'nama_rekening')) {
                $table->string('nama_rekening', 150)->nullable()->after('nomor_rekening');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach (['nama_rekening', 'nomor_rekening', 'nama_bank'] as $col) {
                if (Schema::hasColumn('suppliers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
