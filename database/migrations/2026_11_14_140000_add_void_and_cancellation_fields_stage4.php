<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_notes')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                if (! Schema::hasColumn('credit_notes', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('reason');
                }
                if (! Schema::hasColumn('credit_notes', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason');
                }
                if (! Schema::hasColumn('credit_notes', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('voided_by');
                }
            });
        }

        if (Schema::hasTable('cash_bank_transfers')) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `cash_bank_transfers` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'draft'");
            } catch (\Throwable) {
                // ignore if already varchar or non-mysql
            }

            Schema::table('cash_bank_transfers', function (Blueprint $table) {
                if (! Schema::hasColumn('cash_bank_transfers', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable()->after('description');
                }
                if (! Schema::hasColumn('cash_bank_transfers', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancel_reason');
                }
                if (! Schema::hasColumn('cash_bank_transfers', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('credit_notes')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                $columns = array_filter(['void_reason', 'voided_by', 'voided_at'], fn ($col) => Schema::hasColumn('credit_notes', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('cash_bank_transfers')) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `cash_bank_transfers` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'draft'");
            } catch (\Throwable) {
                // ignore if already varchar or non-mysql
            }

            Schema::table('cash_bank_transfers', function (Blueprint $table) {
                $columns = array_filter(['cancel_reason', 'cancelled_by', 'cancelled_at'], fn ($col) => Schema::hasColumn('cash_bank_transfers', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
