<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\InventoryStock;

class ZeroBalanceSheetSeeder extends Seeder
{
    /**
     * Tables with transactional data that must be cleared before reseeding.
     *
     * @var array<int, string>
     */
    protected array $transactionTables = [
        // Accounting & Finance
        'journal_entries',
        'bank_reconciliations',
        'cash_bank_transaction_details',
        'cash_bank_transactions',
        'cash_bank_transfers',
        'account_payables',
        'account_receivables',
        'ageing_schedules',
        'vendor_payment_details',
        'vendor_payments',
        'customer_receipt_items',
        'customer_receipts',
        'invoices',
        'invoice_items',
        'deposits',
        'deposit_logs',
        'voucher_requests',

        // Sales & Distribution
        'quotations',
        'quotation_items',
        'sale_orders',
        'sale_order_items',
        'delivery_sales_orders',
        'delivery_orders',
        'delivery_order_items',
        'delivery_order_logs',
        'surat_jalans',
        'surat_jalan_delivery_orders',
        'warehouse_confirmations',
        'warehouse_confirmation_items',

        // Purchasing & Logistics
        'purchase_orders',
        'purchase_order_items',
        'purchase_order_item_biayas',
        'purchase_order_biayas',
        'purchase_order_currencies',
        'purchase_receipts',
        'purchase_receipt_items',
        'purchase_receipt_biayas',
        'purchase_receipt_item_nominals',
        'purchase_receipt_photos',
        'purchase_receipt_item_photos',
        'purchase_returns',
        'purchase_return_items',
        'order_requests',
        'order_request_items',
        'return_products',
        'return_product_items',
        'quality_controls',

        // Inventory & Manufacturing
        'stock_movements',
        'stock_transfers',
        'stock_transfer_items',
        'material_issues',
        'material_issue_items',
        'material_fulfillments',
        'production_plans',
        'manufacturing_orders',
        'manufacturing_order_materials',
        'productions',
        'finished_goods_completions',

        // Assets
        'assets',
        'asset_depreciations',

        // Operational logs
        'activity_log',
    ];

    /**
     * Run the database seeds to create a zero balance sheet.
     * This seeder cleans transactional data and reseeds master data only.
     */
    public function run(): void
    {
        $this->command?->info('Preparing zero balance sheet environment...');

        // Temporarily disable InventoryStockObserver to prevent journal entries during seeding
        InventoryStock::withoutEvents(function () {
            $this->performSeeding();
        });
    }

    /**
     * Perform the actual seeding operations.
     */
    private function performSeeding(): void
    {
        $cleared = $this->cleanupTransactionalData();
        $this->resetChartOfAccounts();

        if (!empty($cleared)) {
            $this->command?->info(sprintf('Cleared %d tables: %s', count($cleared), implode(', ', $cleared)));
        }

        $this->command?->info('Seeding master data for zero balance sheet...');

        $this->call([
            // Core System Setup
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,

            // Master Data
            CurrencySeeder::class,
            UnitOfMeasureSeeder::class,
            CabangSeeder::class,
            ChartOfAccountSeeder::class,
            MasterDataSeeder::class,

            // Business Entities (without transactions)
            ProductCategorySeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            ProductSeeder::class,
            BillOfMaterialSeeder::class,

            // Inventory & Warehouse Setup
            WarehouseSeeder::class,
            RakSeeder::class,
        ]);

        // Ensure inventory stocks remain empty after seeding master data.
        // Some seeders may create InventoryStock records (e.g., Product/Warehouse seeders).
        // Clear them here to guarantee a true "zero balance" inventory state.
        $this->clearInventoryStocks();

        $this->command?->info('Zero balance sheet seeding completed. No financial transactions created. Inventory stocks cleared.');
    }

    /**
     * Ensure the inventory_stocks table is empty after seeding.
     */
    private function clearInventoryStocks(): void
    {
        if (!Schema::hasTable('inventory_stocks')) {
            return;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        try {
            $this->truncateTable($connection, $driver, 'inventory_stocks');
            $this->command?->info('Cleared inventory_stocks table to ensure zero inventory.');
        } catch (\Throwable $e) {
            $this->command?->warn(sprintf('Failed to clear inventory_stocks: %s', $e->getMessage()));
        }
    }

    /**
     * Delete all transactional data to ensure general ledger resets to zero.
     *
     * @return array<int, string>
     */
    private function cleanupTransactionalData(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $this->disableForeignKeyChecks($connection, $driver);

        $cleared = [];

        foreach ($this->transactionTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $this->truncateTable($connection, $driver, $table);
                $cleared[] = $table;
            } catch (\Throwable $e) {
                $this->command?->warn(sprintf('Failed to clear table %s: %s', $table, $e->getMessage()));
            }
        }

        $this->enableForeignKeyChecks($connection, $driver);

        return $cleared;
    }

    /**
     * Reset account balances so ledger and balance sheet start from zero.
     */
    private function resetChartOfAccounts(): void
    {
        if (!Schema::hasTable('chart_of_accounts')) {
            return;
        }

        DB::table('chart_of_accounts')->update([
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);
    }

    private function truncateTable(Connection $connection, string $driver, string $table): void
    {
        if ($driver === 'sqlite') {
            $connection->table($table)->delete();
            $connection->statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
            return;
        }

        try {
            $connection->table($table)->truncate();
        } catch (\Throwable $e) {
            // Fallback for tables that cannot be truncated due to FK constraints
            $connection->table($table)->delete();

            if ($driver === 'mysql') {
                $connection->statement(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = 1', $table));
            } elseif ($driver === 'pgsql') {
                $sequence = $table . '_id_seq';
                $connection->statement(sprintf('ALTER SEQUENCE "%s" RESTART WITH 1', $sequence));
            }

            $this->command?->warn(sprintf('Truncate fallback applied for %s (%s)', $table, $e->getMessage()));
        }
    }

    private function disableForeignKeyChecks(Connection $connection, string $driver): void
    {
        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
        } elseif ($driver === 'pgsql') {
            $connection->statement('SET CONSTRAINTS ALL DEFERRED');
        }
    }

    private function enableForeignKeyChecks(Connection $connection, string $driver): void
    {
        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'pgsql') {
            $connection->statement('SET CONSTRAINTS ALL IMMEDIATE');
        }
    }
}