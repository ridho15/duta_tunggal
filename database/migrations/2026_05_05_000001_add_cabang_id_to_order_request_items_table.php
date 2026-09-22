<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_request_items') || Schema::hasColumn('order_request_items', 'cabang_id')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('supplier_id')->constrained('cabangs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_request_items') || ! Schema::hasColumn('order_request_items', 'cabang_id')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropColumn('cabang_id');
        });
    }
};