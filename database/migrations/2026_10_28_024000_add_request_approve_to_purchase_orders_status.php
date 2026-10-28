<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','approved','partially_received','completed','invoiced','paid','closed','request_close','request_approval','request_approve') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE purchase_orders SET status = 'request_approval' WHERE status = 'request_approve'");
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','approved','partially_received','completed','invoiced','paid','closed','request_close','request_approval') NOT NULL DEFAULT 'draft'");
        }
    }
};
