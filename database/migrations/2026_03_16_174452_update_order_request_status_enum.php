<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE order_requests MODIFY COLUMN status ENUM('draft','request_approve','approved','partial','complete','closed','rejected') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Map new statuses back to closest old values before reverting enum
        DB::statement("UPDATE order_requests SET status = 'approved' WHERE status IN ('partial','complete','request_approve')");
        DB::statement("ALTER TABLE order_requests MODIFY COLUMN status ENUM('draft','approved','rejected','closed') NOT NULL DEFAULT 'draft'");
    }
};
