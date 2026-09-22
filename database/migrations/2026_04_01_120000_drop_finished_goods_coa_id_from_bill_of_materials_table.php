<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bill_of_materials', 'finished_goods_coa_id')) {
            return;
        }

        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->dropForeign(['finished_goods_coa_id']);
            $table->dropColumn('finished_goods_coa_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bill_of_materials', 'finished_goods_coa_id')) {
            return;
        }

        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->foreignId('finished_goods_coa_id')
                ->nullable()
                ->after('uom_id')
                ->constrained('chart_of_accounts');
        });
    }
};