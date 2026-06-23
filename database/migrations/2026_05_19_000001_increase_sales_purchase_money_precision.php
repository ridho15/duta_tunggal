<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyIfExists('sale_order_items', 'unit_price', 'DECIMAL(20,10) NOT NULL DEFAULT 0');
        $this->modifyIfExists('sale_orders', 'total_amount', 'DECIMAL(20,10) NOT NULL DEFAULT 0');
        $this->modifyIfExists('purchase_order_items', 'unit_price', 'DECIMAL(20,10) NOT NULL DEFAULT 0');
        $this->modifyIfExists('purchase_orders', 'total_amount', 'DECIMAL(20,10) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        $this->modifyIfExists('sale_order_items', 'unit_price', 'DECIMAL(18,2) NOT NULL DEFAULT 0');
        $this->modifyIfExists('sale_orders', 'total_amount', 'DECIMAL(18,2) NOT NULL DEFAULT 0');
        $this->modifyIfExists('purchase_order_items', 'unit_price', 'DECIMAL(18,2) NOT NULL DEFAULT 0');
        $this->modifyIfExists('purchase_orders', 'total_amount', 'DECIMAL(18,2) NOT NULL DEFAULT 0');
    }

    private function modifyIfExists(string $table, string $column, string $definition): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        }
    }
};
