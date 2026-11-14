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
            DB::statement("ALTER TABLE material_issues MODIFY COLUMN status ENUM('draft','pending_approval','approved','rejected','completed','cancelled') NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM('draft','in_progress','completed','approved','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE material_issues SET status = 'rejected' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE material_issues MODIFY COLUMN status ENUM('draft','pending_approval','approved','rejected','completed') NULL DEFAULT 'draft'");
            DB::statement("UPDATE stock_opnames SET status = 'completed' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM('draft','in_progress','completed','approved') NOT NULL DEFAULT 'draft'");
        }
    }
};
