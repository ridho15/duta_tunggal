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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama Barang
            $table->date('purchase_date'); // Tanggal Beli
            $table->date('usage_date'); // Tanggal Pakai
            $table->decimal('purchase_cost', 20, 2); // Biaya Aset
            $table->decimal('salvage_value', 20, 2)->default(0); // Nilai Sisa
            $table->integer('useful_life_years'); // Umur Manfaat (Tahun)
            
            // COA References
            $table->foreignId('asset_coa_id')->constrained('chart_of_accounts'); // Aset COA (1210.x)
            $table->foreignId('accumulated_depreciation_coa_id')->constrained('chart_of_accounts'); // Akumulasi Penyusutan (1220.x)
            $table->foreignId('depreciation_expense_coa_id')->constrained('chart_of_accounts'); // Beban Penyusutan (6311-6314)
            
            // Calculated fields
            $table->decimal('annual_depreciation', 20, 2)->default(0); // Penyusutan per tahun
            $table->decimal('monthly_depreciation', 20, 2)->default(0); // Penyusutan per bulan
            $table->decimal('accumulated_depreciation', 20, 2)->default(0); // Total akumulasi penyusutan
            $table->decimal('book_value', 20, 2)->default(0); // Nilai buku saat ini
            
            $table->string('status')->default('active'); // active, disposed, fully_depreciated
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
        Schema::dropIfExists('assets');
    }
};
