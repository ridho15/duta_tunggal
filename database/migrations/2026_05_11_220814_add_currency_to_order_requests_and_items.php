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
        // Add currency_id to order_requests (header level currency)
        Schema::table('order_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_requests', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('status');
                $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            }
        });

        // Add currency_id to order_request_items (item level currency)
        Schema::table('order_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_request_items', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('tipe_pajak');
                $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_request_items', 'currency_id')) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            }
        });

        Schema::table('order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_requests', 'currency_id')) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            }
        });
    }
};
