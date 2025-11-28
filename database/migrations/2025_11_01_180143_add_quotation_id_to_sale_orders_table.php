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
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('quotation_id')->nullable()->after('customer_id');
            $table->foreign('quotation_id')->references('id')->on('quotations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            // Attempt to drop foreign key and column; ignore errors if they don't exist
            try {
                $table->dropForeign(['quotation_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->dropColumn('quotation_id');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};
