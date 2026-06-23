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
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_request_items', 'unit_price')) {
                $table->decimal('unit_price', 15, 2)->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('order_request_items', 'discount')) {
                $table->decimal('discount', 15, 2)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('order_request_items', 'tax')) {
                $table->decimal('tax', 15, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('order_request_items', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('tax');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            $columns = array_filter(['unit_price', 'discount', 'tax', 'subtotal'], fn ($column) => Schema::hasColumn('order_request_items', $column));
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
