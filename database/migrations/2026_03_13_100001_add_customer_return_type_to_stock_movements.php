<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the stock_movements.type ENUM to include customer_return and purchase_return.
     *
     * MySQL requires ALTER TABLE to redefine the full ENUM list.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE stock_movements
            MODIFY COLUMN `type` ENUM(
                'purchase_in',
                'purchase_return',
                'sales',
                'customer_return',
                'transfer_in',
                'transfer_out',
                'manufacture_in',
                'manufacture_out',
                'adjustment_in',
                'adjustment_out'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations — remove the new values.
     * Note: rows using the removed values must be updated first;
     * here we simply leave them as-is (safe in development / test environments).
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE stock_movements
            MODIFY COLUMN `type` ENUM(
                'purchase_in',
                'sales',
                'transfer_in',
                'transfer_out',
                'manufacture_in',
                'manufacture_out',
                'adjustment_in',
                'adjustment_out'
            ) NOT NULL
        ");
    }
};
