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
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'supplier_invoice_number')) {
                $table->string('supplier_invoice_number')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('invoices', 'tax_invoice_number')) {
                $table->string('tax_invoice_number')->nullable()->after('supplier_invoice_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'supplier_invoice_number')) {
                $table->dropColumn('supplier_invoice_number');
            }
            if (Schema::hasColumn('invoices', 'tax_invoice_number')) {
                $table->dropColumn('tax_invoice_number');
            }
        });
    }
};
