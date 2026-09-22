<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel notifikasi database Laravel/Filament (lonceng notifikasi panel admin) — dibutuhkan oleh
 * `Notification::make()->sendToDatabase(...)` (Isu 8: notifikasi invoice Terlambat ke finance, dan aksi lain
 * yang sudah memakainya seperti AssetResource). Sebelumnya tabel ini ADA di database dev/produksi tetapi
 * TIDAK PERNAH tercatat sebagai migrasi (dibuat manual di luar version control) — lingkungan baru/segar
 * akan gagal setiap kali `sendToDatabase()` dipanggil. Idempoten: tidak menimpa tabel yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
