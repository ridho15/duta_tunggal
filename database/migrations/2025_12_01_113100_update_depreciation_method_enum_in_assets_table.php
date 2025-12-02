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
        DB::statement("ALTER TABLE assets MODIFY COLUMN depreciation_method ENUM('straight_line', 'declining_balance', 'sum_of_years_digits', 'units_of_production') DEFAULT 'straight_line'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assets MODIFY COLUMN depreciation_method ENUM('straight_line', 'declining_balance', 'units_of_production') DEFAULT 'straight_line'");
    }
};
