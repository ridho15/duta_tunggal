<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if columns already exist
        if (!Schema::hasColumn('purchase_orders', 'legacy_source_name')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Note: cabang_id reference removed as it will be dropped. Add after refer_model_id instead.
                $table->string('legacy_source_name', 50)->nullable()->after('refer_model_id');
                $table->unsignedBigInteger('legacy_legacy_id')->nullable()->after('legacy_source_name');
                $table->string('legacy_reference_number', 150)->nullable()->after('legacy_legacy_id');

                $table->unique(['legacy_source_name', 'legacy_legacy_id'], 'purchase_orders_legacy_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_legacy_unique');
            $table->dropColumn(['legacy_source_name', 'legacy_legacy_id', 'legacy_reference_number']);
        });
    }
};