<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_request_items', 'status')) {
                $table->string('status', 20)->default('draft')->after('fulfilled_quantity')->index();
            }

            if (! Schema::hasColumn('order_request_items', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('order_request_items', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('order_request_items', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('order_request_items', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('order_request_items', 'rejection_note')) {
                $table->text('rejection_note')->nullable()->after('rejected_at');
            }
        });

        if (Schema::hasTable('order_requests')) {
            // Backfill approval status for existing records so old approved/processed ORs do not become editable drafts.
            DB::statement("
                UPDATE order_request_items ori
                INNER JOIN order_requests orh ON orh.id = ori.order_request_id
                SET ori.status = CASE
                    WHEN orh.status IN ('approved', 'partial', 'complete', 'closed') THEN 'approved'
                    WHEN orh.status = 'rejected' THEN 'rejected'
                    ELSE 'draft'
                END
                WHERE ori.status IS NULL OR ori.status = 'draft'
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            foreach (['approved_by', 'rejected_by'] as $column) {
                if (Schema::hasColumn('order_request_items', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['status', 'approved_at', 'rejected_at', 'rejection_note'] as $column) {
                if (Schema::hasColumn('order_request_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
