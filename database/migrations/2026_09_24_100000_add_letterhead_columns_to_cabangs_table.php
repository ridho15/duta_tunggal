<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T6.1 (D12/D41): kop dokumen per Cabang — nama legal, NPWP, alamat pajak, dan daftar rekening bank.
 * Aditif & idempoten; kolom kosong berarti memakai pengaturan global (app_settings) lalu data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            if (! Schema::hasColumn('cabangs', 'nama_legal')) {
                $table->string('nama_legal')->nullable()->after('nama');
            }
            if (! Schema::hasColumn('cabangs', 'npwp')) {
                $table->string('npwp', 40)->nullable()->after('nama_legal');
            }
            if (! Schema::hasColumn('cabangs', 'alamat_pajak')) {
                $table->text('alamat_pajak')->nullable()->after('alamat');
            }
            if (! Schema::hasColumn('cabangs', 'rekening')) {
                $table->json('rekening')->nullable();   // [{bank, number, holder, branch?}]
            }
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            foreach (['nama_legal', 'npwp', 'alamat_pajak', 'rekening'] as $column) {
                if (Schema::hasColumn('cabangs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
