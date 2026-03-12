<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ERP Financial Audit Fix — Account Receivables Integrity
 *
 * Bugs fixed:
 * #6  Missing indexes on account_receivables.invoice_id and customer_id caused full table scans.
 * #7  No unique constraint on invoice_id allowed duplicate AR records if InvoiceObserver fired twice.
 *
 * Before adding the unique index on invoice_id, we remove any duplicate rows that may already exist,
 * keeping the one with the lowest id (first created).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Remove pre-existing duplicate invoice_id rows (keep earliest id per invoice)
        DB::statement("
            DELETE ar1
            FROM account_receivables ar1
            INNER JOIN account_receivables ar2
                ON ar1.invoice_id = ar2.invoice_id
               AND ar1.id > ar2.id
        ");

        Schema::table('account_receivables', function (Blueprint $table) {
            // Bug #7: Unique constraint prevents duplicate AR per invoice
            $table->unique('invoice_id', 'account_receivables_invoice_id_unique');

            // Bug #6: Performance indexes for common query patterns
            $table->index('customer_id', 'account_receivables_customer_id_index');
            $table->index(['status', 'cabang_id'], 'account_receivables_status_cabang_index');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropUnique('account_receivables_invoice_id_unique');
            $table->dropIndex('account_receivables_customer_id_index');
            $table->dropIndex('account_receivables_status_cabang_index');
        });
    }
};
