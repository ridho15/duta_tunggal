<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the confirmation_type enum to include 'delivery_order'
        DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN confirmation_type ENUM('sales_order','manufacturing_order','delivery_order') NOT NULL");
    }

    public function down(): void
    {
        // Revert – any rows with 'delivery_order' must be handled before rollback
        DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN confirmation_type ENUM('sales_order','manufacturing_order') NOT NULL");
    }
};
