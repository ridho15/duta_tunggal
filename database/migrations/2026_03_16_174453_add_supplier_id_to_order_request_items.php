<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_request_items') || Schema::hasColumn('order_request_items', 'supplier_id')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('order_request_id')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_request_items') || ! Schema::hasColumn('order_request_items', 'supplier_id')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
