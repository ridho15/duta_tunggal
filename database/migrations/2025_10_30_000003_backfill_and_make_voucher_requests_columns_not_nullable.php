<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Determine sensible defaults from existing data
        $defaultCabang = DB::table('cabangs')->value('id');
        $defaultUser = DB::table('users')->value('id');

        // Backfill existing nulls
        if ($defaultCabang) {
            DB::table('voucher_requests')->whereNull('cabang_id')->update(['cabang_id' => $defaultCabang]);
        }

        if ($defaultUser) {
            DB::table('voucher_requests')->whereNull('created_by')->update(['created_by' => $defaultUser]);
        }

        // Make columns NOT NULL if they exist and data has been backfilled
        if (Schema::hasTable('voucher_requests')) {
            // Use raw statements to alter column nullability to avoid requiring doctrine/dbal.
            // MySQL/SQLite syntax differs; we attempt both common forms and wrap in try/catch.
            try {
                // For MySQL
                DB::statement('ALTER TABLE `voucher_requests` MODIFY `created_by` BIGINT UNSIGNED NOT NULL');
                DB::statement('ALTER TABLE `voucher_requests` MODIFY `cabang_id` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {
                try {
                    // For SQLite (no-op) or other DBs, use Schema builder as fallback
                    Schema::table('voucher_requests', function ($table) {
                        if (Schema::hasColumn('voucher_requests', 'created_by')) {
                            $table->unsignedBigInteger('created_by')->nullable(false)->change();
                        }
                        if (Schema::hasColumn('voucher_requests', 'cabang_id')) {
                            $table->unsignedBigInteger('cabang_id')->nullable(false)->change();
                        }
                    });
                } catch (\Throwable $inner) {
                    // If altering fails, leave columns as-is and log to storage/logs/laravel.log
                    logger()->warning('Could not alter voucher_requests columns to NOT NULL: ' . $inner->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('voucher_requests')) {
            try {
                DB::statement('ALTER TABLE `voucher_requests` MODIFY `created_by` BIGINT UNSIGNED NULL');
                DB::statement('ALTER TABLE `voucher_requests` MODIFY `cabang_id` BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                try {
                    Schema::table('voucher_requests', function ($table) {
                        if (Schema::hasColumn('voucher_requests', 'created_by')) {
                            $table->unsignedBigInteger('created_by')->nullable()->change();
                        }
                        if (Schema::hasColumn('voucher_requests', 'cabang_id')) {
                            $table->unsignedBigInteger('cabang_id')->nullable()->change();
                        }
                    });
                } catch (\Throwable $inner) {
                    logger()->warning('Could not revert voucher_requests columns nullability: ' . $inner->getMessage());
                }
            }
        }
    }
};
