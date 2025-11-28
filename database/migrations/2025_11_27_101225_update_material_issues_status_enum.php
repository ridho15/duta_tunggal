<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update enum to include approval workflow statuses
        DB::statement("ALTER TABLE material_issues MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'completed') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE material_issues MODIFY COLUMN status ENUM('draft', 'completed') DEFAULT 'draft'");
    }
};
