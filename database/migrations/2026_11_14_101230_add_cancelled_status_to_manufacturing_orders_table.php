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
        DB::statement("ALTER TABLE manufacturing_orders MODIFY COLUMN status ENUM('draft', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE manufacturing_orders MODIFY COLUMN status ENUM('draft', 'in_progress', 'completed') NOT NULL DEFAULT 'draft'");
    }
};

