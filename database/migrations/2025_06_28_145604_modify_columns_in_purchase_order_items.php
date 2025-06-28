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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('currency_id');
            $table->renameColumn('opsi_harga', 'tipe_pajak');
            $table->enum('tipe_pajak', ['Non Pajak', 'Inklusif', 'Eklusif'])->default('Non Pajak')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            //
        });
    }
};
