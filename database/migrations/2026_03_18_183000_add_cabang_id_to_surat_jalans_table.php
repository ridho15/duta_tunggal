<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('surat_jalans') || Schema::hasColumn('surat_jalans', 'cabang_id')) {
            return;
        }

        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_id')->nullable()->after('document_path');
            $table->foreign('cabang_id')->references('id')->on('cabangs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('surat_jalans') || ! Schema::hasColumn('surat_jalans', 'cabang_id')) {
            return;
        }

        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropColumn('cabang_id');
        });
    }
};
