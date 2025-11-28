<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_payment_details', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_payment_details', 'adjustment_amount')) {
                $table->decimal('adjustment_amount', 18, 2)->default(0); // Diskon / koreksi / penyesuaian
            }
            if (!Schema::hasColumn('vendor_payment_details', 'balance_amount')) {
                $table->decimal('balance_amount', 18, 2)->default(0); // Sisa hutang setelah payment + adjustment
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payment_details', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payment_details', 'adjustment_amount')) {
                $table->dropColumn('adjustment_amount');
            }
            if (Schema::hasColumn('vendor_payment_details', 'balance_amount')) {
                $table->dropColumn('balance_amount');
            }
        });
    }
};
