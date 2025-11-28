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
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->date('depreciation_date'); // Tanggal penyusutan
            $table->integer('period_month'); // Bulan ke-
            $table->integer('period_year'); // Tahun
            $table->decimal('amount', 20, 2); // Nilai penyusutan
            $table->decimal('accumulated_total', 20, 2); // Total akumulasi sampai periode ini
            $table->decimal('book_value', 20, 2); // Nilai buku setelah penyusutan
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Link ke journal entry
            $table->string('status')->default('recorded'); // recorded, reversed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
    }
};
