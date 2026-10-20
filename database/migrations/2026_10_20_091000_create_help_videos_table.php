<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tahap 3 Spesifikasi UI/UX: video tutorial per peran (halaman Bantuan). */
    public function up(): void
    {
        Schema::create('help_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Kode peran spesifikasi (KSR, GDG, ADP, SLS, PUR, FIN, SM, OWN); null = untuk semua peran
            $table->string('role_code', 10)->nullable()->index();
            $table->string('url', 500);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_videos');
    }
};
