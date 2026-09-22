<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 5A (Penerimaan Customer):
     *  - chart_of_accounts.is_cash_bank : penanda akun yang BOLEH menerima uang (kas/bank). Diisi lewat
     *    `php artisan coa:flag-cash-bank` setelah ditinjau akuntansi.
     *  - customer_receipts.overpayment_amount / deposit_id : kelebihan bayar yang dicatat sebagai
     *    Deposit Customer (bukan lagi dipotong senyap) beserta tautannya.
     */
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('chart_of_accounts', 'is_cash_bank')) {
                $table->boolean('is_cash_bank')->default(false)->after('is_active')->index();
            }
        });

        Schema::table('customer_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_receipts', 'overpayment_amount')) {
                $table->decimal('overpayment_amount', 15, 2)->default(0)->after('total_payment_idr');
            }
            if (! Schema::hasColumn('customer_receipts', 'deposit_id')) {
                $table->unsignedBigInteger('deposit_id')->nullable()->after('overpayment_amount')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            foreach (['deposit_id', 'overpayment_amount'] as $column) {
                if (Schema::hasColumn('customer_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'is_cash_bank')) {
                $table->dropColumn('is_cash_bank');
            }
        });
    }
};
