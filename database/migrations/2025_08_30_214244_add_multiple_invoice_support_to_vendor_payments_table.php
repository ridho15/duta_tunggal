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
        Schema::table('vendor_payments', function (Blueprint $table) {
            // Make invoice_id nullable since we'll store invoices in JSON
            $table->integer('invoice_id')->nullable()->change();
            
            // Add field for multiple invoices
            $table->json('selected_invoices')->nullable()->after('supplier_id');
            
            // Add field for payment adjustment
            $table->decimal('payment_adjustment', 18, 2)->default(0)->after('diskon');
            
            // Add payment_date field since it was dropped before
            $table->date('payment_date')->nullable()->after('ntpn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->integer('invoice_id')->nullable(false)->change();
            $table->dropColumn(['selected_invoices', 'payment_adjustment', 'payment_date']);
        });
    }
};
