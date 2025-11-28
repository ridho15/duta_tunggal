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
            // Make invoice_id nullable since we'll store invoices in JSON
            $table->integer('invoice_id')->nullable()->change();
            
            // Add field for multiple invoices
            $table->json('selected_invoices')->nullable()->after('customer_id');
            
            // Add field for payment adjustment
            $table->decimal('payment_adjustment', 18, 2)->default(0)->after('diskon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            // Avoid forcing invoice_id to NOT NULL during rollback (some rows may be null)
            // $table->integer('invoice_id')->nullable(false)->change();
            $table->dropColumn(['selected_invoices', 'payment_adjustment']);
        });
    }
};
