<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_request_items', 'tipe_pajak')) {
                $table->string('tipe_pajak', 20)->default('Eklusif')->after('tax');
            }
        });

        if (Schema::hasTable('order_request_items') && Schema::hasTable('order_requests')) {
            DB::statement(
                "UPDATE order_request_items ori
                 INNER JOIN order_requests orh ON orh.id = ori.order_request_id
                 SET ori.tipe_pajak = CASE
                    WHEN COALESCE(ori.tax, 0) <= 0 OR COALESCE(orh.tax_type, 'PPN Excluded') = 'None' THEN 'Non Pajak'
                    WHEN COALESCE(orh.tax_type, 'PPN Excluded') = 'PPN Included' THEN 'Inklusif'
                    ELSE 'Eklusif'
                 END"
            );
        }
    }

    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_request_items', 'tipe_pajak')) {
                $table->dropColumn('tipe_pajak');
            }
        });
    }
};
