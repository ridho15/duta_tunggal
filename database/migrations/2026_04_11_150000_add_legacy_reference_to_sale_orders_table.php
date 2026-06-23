<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('legacy_source_name', 50)->nullable()->after('cabang_id');
            $table->unsignedBigInteger('legacy_legacy_id')->nullable()->after('legacy_source_name');
            $table->string('legacy_reference_number', 150)->nullable()->after('legacy_legacy_id');

            $table->unique(['legacy_source_name', 'legacy_legacy_id'], 'sale_orders_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropUnique('sale_orders_legacy_unique');
            $table->dropColumn(['legacy_source_name', 'legacy_legacy_id', 'legacy_reference_number']);
        });
    }
};