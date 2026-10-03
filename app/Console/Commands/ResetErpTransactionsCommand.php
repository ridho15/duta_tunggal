<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetErpTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:reset-transactions
        {--dry-run : Hanya simulasi dan hitung jumlah data transaksi tanpa menghapus}
        {--force : Lewati konfirmasi interaktif (gunakan hati-hati!)}
        {--reset-sequences : Reset juga urutan nomor dokumen (document_sequences) ke awal}
        {--truncate-stock : Kosongkan tabel inventory_stocks sepenuhnya (default: reset saldo stok ke 0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus SEMUA data transaksi operasional ERP dan mempertahankan data master, role, permission, serta user.';

    /**
     * Daftar tabel MASTER & AKSES yang DIJAMIN AMAN (TIDAK AKAN DIHAPUS).
     */
    private array $preservedMasterTables = [
        // Akses & User
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'password_reset_tokens',

        // Organisasi & Lokasi
        'cabangs',
        'warehouses',
        'raks',

        // Katalog Produk & Manufaktur
        'product_categories',
        'products',
        'product_supplier',
        'product_standard_costs',
        'product_unit_conversions',
        'unit_of_measures',
        'bill_of_materials',
        'bill_of_material_items',

        // Mitra Bisnis & Eksternal
        'customers',
        'suppliers',
        'drivers',
        'vehicles',
        'assets', // Master aset tetap dipertahankan (penyusutan transaksi dihapus)

        // Akuntansi & Keuangan Master
        'chart_of_accounts',
        'cash_bank_accounts',
        'currencies',
        'tax_settings',
        'accounting_periods',
        'accounting_settings',

        // Template & Konfigurasi Laporan Keuangan
        'income_statement_items',
        'report_cash_flow_cash_accounts',
        'report_cash_flow_item_prefixes',
        'report_cash_flow_item_sources',
        'report_cash_flow_items',
        'report_cash_flow_sections',
        'report_hpp_overhead_item_prefixes',
        'report_hpp_overhead_items',
        'report_hpp_prefixes',

        // Konfigurasi Sistem
        'app_settings',
        'approval_rules',
        'approval_overrides',
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * Daftar tabel TRANSAKSI yang akan dikosongkan (diurutkan berdasarkan domain).
     */
    private array $transactionTables = [
        // 1. Penjualan & Penawaran (Sales & Quotation)
        'quotation_items',
        'quotations',
        'sale_order_item_warehouse_allocations',
        'sale_order_items',
        'sale_orders',
        'other_sales',

        // 2. Pengiriman & Logistik (Delivery & Logistics)
        'delivery_order_logs',
        'delivery_order_approval_logs',
        'delivery_order_item_warehouse_sources',
        'delivery_order_items',
        'delivery_orders',
        'delivery_sales_orders',
        'delivery_schedule_delivery_orders',
        'delivery_schedule_surat_jalans',
        'delivery_schedules',
        'surat_jalan_delivery_orders',
        'surat_jalans',

        // 3. Faktur Penjualan, Piutang (AR) & Penerimaan Kas (Customer Receipts)
        'invoice_items',
        'invoices',
        'account_receivables',
        'customer_receipt_items',
        'customer_receipts',
        'deposits',
        'deposit_logs',
        'credit_note_items',
        'credit_notes',

        // 4. Retur Penjualan & Retur Produk
        'customer_return_items',
        'customer_returns',
        'return_product_items',
        'return_products',

        // 5. Permintaan Barang & Pengadaan (Purchase & Procurement)
        'order_request_items',
        'order_requests',
        'purchase_order_biayas',
        'purchase_order_currencies',
        'purchase_order_items',
        'purchase_orders',

        // 6. Penerimaan Barang & QC (Purchase Receipts & Quality Control)
        'purchase_receipt_photos',
        'purchase_receipt_item_photos',
        'purchase_receipt_item_nominals',
        'purchase_receipt_biayas',
        'purchase_receipt_items',
        'purchase_receipts',
        'quality_control_items',
        'quality_controls',

        // 7. Retur Pembelian (Purchase Returns)
        'purchase_return_items',
        'purchase_returns',

        // 8. Hutang Dagang (AP), Permintaan Bayar & Vendor Payments
        'account_payables',
        'payment_requests',
        'voucher_requests',
        'vendor_payment_details',
        'vendor_payments',

        // 9. Mutasi Stok, Opname, Transfer, & Reservasi
        'stock_movements',
        'stock_adjustment_items',
        'stock_adjustments',
        'stock_opname_items',
        'stock_opnames',
        'stock_transfer_items',
        'stock_transfers',
        'stock_reservation_events',
        'stock_reservations',
        'warehouse_confirmation_items',
        'warehouse_confirmation_warehouses',
        'warehouse_confirmations',

        // 10. Produksi & Manufaktur (Manufacturing)
        'material_issue_items',
        'material_issues',
        'production_cost_entries',
        'production_plans',
        'productions',
        'manufacturing_orders',
        'cost_variances',

        // 11. Buku Besar (General Ledger) & Kas/Bank Transaksional
        'journal_entries',
        'cash_bank_transaction_details',
        'cash_bank_transactions',
        'cash_bank_transfers',
        'bank_reconciliations',
        'ageing_schedules',

        // 12. Transaksi Aset (Penyusutan & Pelepasan)
        'asset_depreciations',
        'asset_disposals',
        'asset_transfers',

        // 13. Arsip & Log Transaksi
        'legacy_transaction_archives',
        'activity_log',
        'notifications',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $truncateStock = (bool) $this->option('truncate-stock');
        $resetSequences = (bool) $this->option('reset-sequences');

        $this->alert('ERP DATABASE RESET: PEMBERSIHAN DATA TRANSAKSI OPERASIONAL');
        $this->line('Command ini akan <fg=red;options=bold>MENGHAPUS SEMUA TRANSAKSI</> dan <fg=green;options=bold>MEMPERTAHANKAN DATA MASTER, USERS, ROLES & PERMISSIONS</>.');

        if ($dryRun) {
            $this->warn('MODE DRY-RUN DIAKTIFKAN: Tidak ada data yang akan dihapus.');
        }

        // 1. Verifikasi tabel transaksi yang eksis di database
        $existingTables = array_map(
            fn ($r) => array_values((array) $r)[0],
            DB::select('SHOW TABLES')
        );

        $targetTables = [];
        $totalRows = 0;

        foreach ($this->transactionTables as $table) {
            if (in_array($table, $existingTables)) {
                $count = DB::table($table)->count();
                $targetTables[$table] = $count;
                $totalRows += $count;
            }
        }

        // Tampilkan preview tabel transaksi
        $tableRows = [];
        foreach ($targetTables as $table => $count) {
            $tableRows[] = [
                $table,
                number_format($count, 0, ',', '.'),
                $count > 0 ? '<fg=yellow>Akan Dikosongkan</>' : '<fg=gray>Kosong</>',
            ];
        }

        $this->table(['Nama Tabel Transaksi', 'Jumlah Data Saat Ini', 'Aksi'], $tableRows);
        $this->info("Total data transaksi terdeteksi: " . number_format($totalRows, 0, ',', '.') . " baris pada " . count($targetTables) . " tabel.");

        // Tampilkan ringkasan data master yang aman dipertahankan
        $preservedCounts = [];
        foreach (['users', 'roles', 'permissions', 'products', 'customers', 'suppliers', 'chart_of_accounts', 'cabangs', 'warehouses'] as $masterTable) {
            if (in_array($masterTable, $existingTables)) {
                $preservedCounts[] = [
                    $masterTable,
                    number_format(DB::table($masterTable)->count(), 0, ',', '.'),
                    '<fg=green;options=bold>DIAMANKAN (DIPERTAHANKAN)</>',
                ];
            }
        }
        $this->newLine();
        $this->info('Contoh Data Master & Akses yang AMAN dan TETAP UTUH:');
        $this->table(['Tabel Master / Akses', 'Jumlah Data', 'Status Proteksi'], $preservedCounts);

        if ($dryRun) {
            $this->info('Simulasi Dry-Run Selesai. Database tidak mengalami perubahan.');
            return self::SUCCESS;
        }

        // Konfirmasi keamanan ganda
        if (! $force) {
            $this->newLine();
            $this->error('PERINGATAN: Tindakan ini permanen dan tidak dapat dibatalkan!');
            if (! $this->confirm('Apakah Anda benar-benar yakin ingin MENGHAPUS SELURUH data transaksi di atas?', false)) {
                $this->warn('Operasi reset dibatalkan oleh pengguna.');
                return self::INVALID;
            }

            $confirmText = $this->ask('Ketik "RESET-TRANSAKSI" untuk mengonfirmasi eksekusi:');
            if ($confirmText !== 'RESET-TRANSAKSI') {
                $this->warn('Konfirmasi tidak sesuai. Operasi reset dibatalkan.');
                return self::INVALID;
            }
        }

        $this->newLine();
        $this->info('Memulai pembersihan data transaksi...');

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $progressBar = $this->output->createProgressBar(count($targetTables));
            $progressBar->start();

            foreach (array_keys($targetTables) as $table) {
                DB::table($table)->truncate();
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Penanganan Saldo Stok Fisik (inventory_stocks)
            if (in_array('inventory_stocks', $existingTables)) {
                if ($truncateStock) {
                    DB::table('inventory_stocks')->truncate();
                    $this->info('✓ Tabel inventory_stocks dikosongkan (truncate).');
                } else {
                    DB::table('inventory_stocks')->update([
                        'qty_available' => 0,
                        'qty_reserved' => 0,
                    ]);
                    $this->info('✓ Saldo stok fisik (inventory_stocks) berhasil direset ke 0 (mapping produk & gudang dipertahankan).');
                }
            }

            // Penanganan Sequence Penomoran Dokumen
            if ($resetSequences) {
                if (in_array('document_sequences', $existingTables)) {
                    DB::table('document_sequences')->update(['current_number' => 0]);
                    $this->info('✓ Counter penomoran dokumen (document_sequences) berhasil direset ke 0.');
                }
                if (in_array('voucher_number_sequences', $existingTables)) {
                    DB::table('voucher_number_sequences')->truncate();
                    $this->info('✓ Urutan nomor voucher berhasil dikosongkan.');
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->newLine();
            $this->info('===============================================================');
            $this->info('  PEMBERSIHAN DATA TRANSAKSI ERP BERHASIL DISELESAIKAN!       ');
            $this->info('===============================================================');
            $this->line('• Semua transaksi operasional, faktur, PO/SO, DO, GL, dan mutasi stok telah bersih.');
            $this->line('• Seluruh data Master Produk, Pelanggan, Supplier, COA, Cabang, Gudang tetap utuh.');
            $this->line('• Seluruh akun Pengguna, Role, dan Permission Spatie tetap utuh tanpa perubahan.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Gagal saat mereset transaksi: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
