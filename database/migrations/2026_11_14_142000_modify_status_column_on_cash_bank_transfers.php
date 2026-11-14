<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_bank_transfers')) {
            DB::statement("ALTER TABLE `cash_bank_transfers` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_bank_transfers')) {
            DB::statement("ALTER TABLE `cash_bank_transfers` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'draft'");
        }
    }
};

