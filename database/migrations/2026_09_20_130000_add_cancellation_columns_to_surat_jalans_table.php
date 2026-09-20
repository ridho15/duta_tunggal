<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siklus hidup Surat Jalan: status 0 = Draft, 1 = Terbit, 2 = Dibatalkan.
     * Surat Jalan yang sudah terbit tidak diubah/dihapus; koreksi lewat Batalkan (alasan wajib)
     * lalu terbitkan ulang. `status` sudah tinyint sehingga nilai 2 tidak perlu migrasi enum.
     */
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_jalans', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('document_path');
            }
            if (! Schema::hasColumn('surat_jalans', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('surat_jalans', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            foreach (['cancel_reason', 'cancelled_by', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('surat_jalans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
