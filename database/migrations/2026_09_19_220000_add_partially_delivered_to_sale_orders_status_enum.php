<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add partially_delivered to sale_orders.status enum.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('draft','request_approve','request_close','approved','closed','completed','partial_confirmed','confirmed','received','canceled','reject','partially_delivered') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('draft','request_approve','request_close','approved','closed','completed','partial_confirmed','confirmed','received','canceled','reject') NOT NULL DEFAULT 'draft'");
    }
};
