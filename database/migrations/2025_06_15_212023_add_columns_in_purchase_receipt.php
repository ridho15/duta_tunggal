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
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->integer('currency_id');
            $table->bigInteger('other_cost')->comment('Biaya lain lain')->default(0);
            $table->enum('status', ['draft', 'partial', 'completed'])->default('draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            //
        });
    }
};
