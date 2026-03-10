<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add created_at indexes on high-volume tables to support
 * ORDER BY created_at DESC sorting without full table scans.
 */
return new class extends Migration
{
    /**
     * High-volume tables that need a created_at sort index.
     * Tables that use a custom date column (stock_movements.date,
     * invoices.invoice_date, inventory_stocks.updated_at) get
     * their own specific index instead.
     */
    private array $createdAtTables = [
        'journal_entries',
        'sale_orders',
        'purchase_orders',
        'delivery_orders',
        'deposits',
        'account_payables',
        'account_receivables',
        'quotations',
        'payment_requests',
        'voucher_requests',
        'purchase_receipts',
        'purchase_returns',
        'stock_adjustments',
        'stock_opnames',
        'stock_transfers',
        'material_issues',
        'productions',
        'production_plans',
        'manufacturing_orders',
        'quality_controls',
        'order_requests',
        'other_sales',
        'customer_receipts',
        'cash_bank_transactions',
        'cash_bank_transfers',
        'asset_disposals',
        'asset_transfers',
        'assets',
        'return_products',
        'surat_jalans',
        'bank_reconciliations',
        'warehouse_confirmations',
        'vendor_payments',
    ];

    public function up(): void
    {
        foreach ($this->createdAtTables as $table) {
            $indexName = "{$table}_created_at_index";
            if (! $this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->index('created_at');
                });
            }
        }

        // stock_movements sorts by `date` column
        if (! $this->indexExists('stock_movements', 'stock_movements_date_index')) {
            Schema::table('stock_movements', function (Blueprint $t) {
                $t->index('date');
            });
        }

        // invoices sorts by `invoice_date`
        if (! $this->indexExists('invoices', 'invoices_invoice_date_index')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->index('invoice_date');
            });
        }

        // inventory_stocks sorts by `updated_at`
        if (! $this->indexExists('inventory_stocks', 'inventory_stocks_updated_at_index')) {
            Schema::table('inventory_stocks', function (Blueprint $t) {
                $t->index('updated_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->createdAtTables as $table) {
            $indexName = "{$table}_created_at_index";
            if ($this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['created_at']);
                });
            }
        }

        if ($this->indexExists('stock_movements', 'stock_movements_date_index')) {
            Schema::table('stock_movements', function (Blueprint $t) {
                $t->dropIndex(['date']);
            });
        }

        if ($this->indexExists('invoices', 'invoices_invoice_date_index')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->dropIndex(['invoice_date']);
            });
        }

        if ($this->indexExists('inventory_stocks', 'inventory_stocks_updated_at_index')) {
            Schema::table('inventory_stocks', function (Blueprint $t) {
                $t->dropIndex(['updated_at']);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains('Key_name', $indexName);
    }
};
