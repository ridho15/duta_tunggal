<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receipt_items')) {
            return;
        }

        if (! Schema::hasColumn('purchase_receipt_items', 'status')) {
            Schema::table('purchase_receipt_items', function (Blueprint $table) {
                $table->enum('status', ['pending', 'completed'])->default('pending')->after('is_sent')
                    ->comment('Status of the receipt item: pending = not yet QC completed, completed = QC done and stock updated');
            });
        }

        if (Schema::hasColumn('purchase_receipt_items', 'is_sent') && Schema::hasColumn('purchase_receipt_items', 'status')) {
            // Migrate existing data: if is_sent = 1, set status = 'completed'
            DB::statement("UPDATE purchase_receipt_items SET status = 'completed' WHERE is_sent = 1");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_receipt_items')) {
            return;
        }

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_receipt_items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
