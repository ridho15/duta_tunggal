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
        Schema::table('sale_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_orders', 'shipping_recipient_name')) {
                $table->string('shipping_recipient_name', 150)->nullable()->after('shipped_to');
            }
            if (! Schema::hasColumn('sale_orders', 'shipping_recipient_phone')) {
                $table->string('shipping_recipient_phone', 50)->nullable()->after('shipping_recipient_name');
            }
        });

        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'shipping_recipient_name')) {
                $table->string('shipping_recipient_name', 150)->nullable()->after('shipped_to');
            }
            if (! Schema::hasColumn('quotations', 'shipping_recipient_phone')) {
                $table->string('shipping_recipient_phone', 50)->nullable()->after('shipping_recipient_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sale_orders', 'shipping_recipient_phone')) {
                $table->dropColumn('shipping_recipient_phone');
            }
            if (Schema::hasColumn('sale_orders', 'shipping_recipient_name')) {
                $table->dropColumn('shipping_recipient_name');
            }
        });

        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'shipping_recipient_phone')) {
                $table->dropColumn('shipping_recipient_phone');
            }
            if (Schema::hasColumn('quotations', 'shipping_recipient_name')) {
                $table->dropColumn('shipping_recipient_name');
            }
        });
    }
};

