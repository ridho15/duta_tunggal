<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. asset_disposals
        if (Schema::hasTable('asset_disposals')) {
            // Ubah tipe kolom status menjadi VARCHAR(50) agar mendukung cancelled/voided
            try {
                DB::statement("ALTER TABLE asset_disposals MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'completed'");
            } catch (\Throwable $e) {
                // Fallback jika database driver tidak mendukung direct ALTER
            }

            Schema::table('asset_disposals', function (Blueprint $table) {
                if (!Schema::hasColumn('asset_disposals', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('asset_disposals', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason');
                }
                if (!Schema::hasColumn('asset_disposals', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('voided_by');
                }
            });
        }

        // 2. asset_transfers
        if (Schema::hasTable('asset_transfers')) {
            try {
                DB::statement("ALTER TABLE asset_transfers MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
            } catch (\Throwable $e) {
            }

            Schema::table('asset_transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('asset_transfers', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('reason');
                }
                if (!Schema::hasColumn('asset_transfers', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason');
                }
                if (!Schema::hasColumn('asset_transfers', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('voided_by');
                }
            });
        }

        // 3. suppliers
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('suppliers', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('cabang_id');
                }
                if (!Schema::hasColumn('suppliers', 'merged_into')) {
                    $table->unsignedBigInteger('merged_into')->nullable()->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('asset_disposals')) {
            Schema::table('asset_disposals', function (Blueprint $table) {
                $table->dropColumn(['void_reason', 'voided_by', 'voided_at']);
            });
        }

        if (Schema::hasTable('asset_transfers')) {
            Schema::table('asset_transfers', function (Blueprint $table) {
                $table->dropColumn(['void_reason', 'voided_by', 'voided_at']);
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn(['is_active', 'merged_into']);
            });
        }
    }
};

