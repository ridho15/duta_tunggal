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
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('draft','approved','partially_received','completed','invoiced','paid','closed','request_close','request_approval','request_approve','cancelled') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE purchase_receipts MODIFY COLUMN status ENUM('draft','partial','completed','cancelled') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE purchase_receipt_items MODIFY COLUMN status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE purchase_orders SET status = 'closed' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('draft','approved','partially_received','completed','invoiced','paid','closed','request_close','request_approval','request_approve') NOT NULL DEFAULT 'draft'");
            DB::statement("UPDATE purchase_receipts SET status = 'completed' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE purchase_receipts MODIFY COLUMN status ENUM('draft','partial','completed') NOT NULL DEFAULT 'draft'");
            DB::statement("UPDATE purchase_receipt_items SET status = 'completed' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE purchase_receipt_items MODIFY COLUMN status ENUM('pending','completed') NOT NULL DEFAULT 'pending'");
        }
    }
};

