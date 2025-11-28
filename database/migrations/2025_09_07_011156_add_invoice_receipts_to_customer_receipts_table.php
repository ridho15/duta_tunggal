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
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->json('invoice_receipts')->nullable()->after('selected_invoices');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropColumn('invoice_receipts');
        });
    }
};
