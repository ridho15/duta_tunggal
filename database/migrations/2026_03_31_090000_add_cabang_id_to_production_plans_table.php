<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_plans')) {
            return;
        }

        if (!Schema::hasColumn('production_plans', 'cabang_id')) {
            Schema::table('production_plans', function (Blueprint $table) {
                $table->foreignId('cabang_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('cabangs')
                    ->nullOnDelete();
            });
        }

        DB::table('production_plans')
            ->orderBy('id')
            ->chunkById(100, function ($plans) {
                foreach ($plans as $plan) {
                    $cabangId = null;

                    if (!empty($plan->sale_order_id)) {
                        $cabangId = DB::table('sale_orders')
                            ->where('id', $plan->sale_order_id)
                            ->value('cabang_id');
                    }

                    if (!$cabangId && !empty($plan->bill_of_material_id)) {
                        $cabangId = DB::table('bill_of_materials')
                            ->where('id', $plan->bill_of_material_id)
                            ->value('cabang_id');
                    }

                    if (!$cabangId && !empty($plan->warehouse_id)) {
                        $cabangId = DB::table('warehouses')
                            ->where('id', $plan->warehouse_id)
                            ->value('cabang_id');
                    }

                    if ($cabangId) {
                        DB::table('production_plans')
                            ->where('id', $plan->id)
                            ->update(['cabang_id' => $cabangId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('production_plans') && Schema::hasColumn('production_plans', 'cabang_id')) {
            Schema::table('production_plans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cabang_id');
            });
        }
    }
};