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
        // DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ensure any non-standard enum values are normalized before modifying column
        DB::statement("UPDATE chart_of_accounts SET `type` = 'Asset' WHERE `type` NOT IN ('Asset','Liability','Equity','Revenue','Expense')");
        DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense')");
    }
};
