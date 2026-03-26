<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->string('confirm_from')->nullable()->after('delivery_order_id')->comment('Source of confirmation, e.g. delivery_order, stock_movement');
            $table->unsignedBigInteger('confirm_id')->nullable()->after('confirm_from')->comment('ID of the source record');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_confirmations', 'confirm_from')) {
                $table->dropColumn('confirm_from');
            }
            if (Schema::hasColumn('warehouse_confirmations', 'confirm_id')) {
                $table->dropColumn('confirm_id');
            }
        });
    }
};
