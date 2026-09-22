<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        foreach (['unit_price', 'original_price', 'subtotal'] as $column) {
            if (Schema::hasColumn('order_request_items', $column)) {
                DB::statement("ALTER TABLE order_request_items MODIFY {$column} DECIMAL(20,10) NOT NULL DEFAULT 0");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        foreach (['unit_price', 'original_price', 'subtotal'] as $column) {
            if (Schema::hasColumn('order_request_items', $column)) {
                DB::statement("ALTER TABLE order_request_items MODIFY {$column} DECIMAL(15,2) NOT NULL DEFAULT 0");
            }
        }
    }
};
