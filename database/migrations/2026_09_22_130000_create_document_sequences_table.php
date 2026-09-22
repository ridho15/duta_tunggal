<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** T4.1 (D11) — urutan nomor dokumen atomik per jenis × cabang × periode (YYMM). */
    public function up(): void
    {
        if (! Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('type', 40);                       // mis. sale_order, invoice_tax
                $table->unsignedBigInteger('cabang_id')->default(0);   // 0 = tanpa cabang (global)
                $table->string('period', 8);                      // YYMM
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['type', 'cabang_id', 'period'], 'document_sequences_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
