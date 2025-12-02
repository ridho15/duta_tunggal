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
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->enum('confirmation_type', ['sales_order', 'manufacturing_order'])->nullable()->after('manufacturing_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->dropColumn('confirmation_type');
        });
    }
};
