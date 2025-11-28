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
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'is_import')) {
                $table->boolean('is_import')->default(false)->after('is_asset');
            }
            if (!Schema::hasColumn('purchase_orders', 'ppn_option')) {
                $table->enum('ppn_option', ['standard', 'non_ppn'])
                    ->default('standard')
                    ->after('is_import');
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_payments', 'is_import_payment')) {
                $table->boolean('is_import_payment')->default(false)->after('payment_method');
            }
            if (!Schema::hasColumn('vendor_payments', 'ppn_import_amount')) {
                $table->decimal('ppn_import_amount', 18, 2)->default(0)->after('is_import_payment');
            }
            if (!Schema::hasColumn('vendor_payments', 'pph22_amount')) {
                $table->decimal('pph22_amount', 18, 2)->default(0)->after('ppn_import_amount');
            }
            if (!Schema::hasColumn('vendor_payments', 'bea_masuk_amount')) {
                $table->decimal('bea_masuk_amount', 18, 2)->default(0)->after('pph22_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'ppn_option')) {
                $table->dropColumn('ppn_option');
            }
            if (Schema::hasColumn('purchase_orders', 'is_import')) {
                $table->dropColumn('is_import');
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payments', 'bea_masuk_amount')) {
                $table->dropColumn('bea_masuk_amount');
            }
            if (Schema::hasColumn('vendor_payments', 'pph22_amount')) {
                $table->dropColumn('pph22_amount');
            }
            if (Schema::hasColumn('vendor_payments', 'ppn_import_amount')) {
                $table->dropColumn('ppn_import_amount');
            }
            if (Schema::hasColumn('vendor_payments', 'is_import_payment')) {
                $table->dropColumn('is_import_payment');
            }
        });
    }
};
