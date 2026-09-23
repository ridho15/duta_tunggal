<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;
use App\Models\QuotationItem;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Services\CustomerMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsolidateDuplicateMasterEntitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master:consolidate-duplicates
        {--apply : Terapkan penggabungan ke database (default: dry-run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Konsolidasi data master ganda (Produk, Customer, Supplier): alihkan transaksi ke entitas utama dan nonaktifkan duplikat';

    public function handle(CustomerMerger $customerMerger): int
    {
        $apply = (bool) $this->option('apply');

        $this->info('================================================================');
        $this->info('  KONSOLIDASI MASTER DATA GANDA (DUTA TUNGGAL ERP)');
        $this->info('  Mode: ' . ($apply ? 'APPLY (Perubahan akan disimpan permanen)' : 'DRY-RUN (Tidak ada perubahan yang disimpan)'));
        $this->info('================================================================');

        $totalConsolidated = 0;

        DB::beginTransaction();
        try {
            // 1. Konsolidasi Produk Duplikat
            $totalConsolidated += $this->consolidateProducts($apply);

            // 2. Konsolidasi Customer Duplikat
            $totalConsolidated += $this->consolidateCustomers($customerMerger, $apply);

            // 3. Konsolidasi Supplier Duplikat
            $totalConsolidated += $this->consolidateSuppliers($apply);

            if ($apply) {
                DB::commit();
                $this->newLine();
                $this->info("✓ [SUKSES] Seluruh konsolidasi master data ({$totalConsolidated} entitas) berhasil diterapkan.");
            } else {
                DB::rollBack();
                $this->newLine();
                $this->info('✓ [DRY-RUN BERHASIL] Transaksi dibatalkan (rollback). Tidak ada master data yang diubah.');
                $this->comment('Untuk mengeksekusi secara permanen, jalankan kembali dengan opsi --apply:');
                $this->line('  php artisan master:consolidate-duplicates --apply');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Terjadi kesalahan saat konsolidasi master data: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * 1. Konsolidasi Produk Duplikat
     */
    protected function consolidateProducts(bool $apply): int
    {
        $this->newLine();
        $this->info('1. Memeriksa Duplikasi Master Produk...');
        $changes = 0;

        // A. Cek kelompok produk dengan target ETBG000016 atau suffix -DUP
        $targetProducts = Product::where(function ($q) {
            $q->where('sku', 'like', '%ETBG000016%')
                ->orWhere('sku', 'like', '%-DUP%')
                ->orWhere('name', 'like', '%ETBG000016%');
        })->get();

        if ($targetProducts->isNotEmpty()) {
            $this->warn("   Ditemukan {$targetProducts->count()} produk varian ETBG000016 / DUP.");
            // Pilih canonical: ID terendah yang aktif atau ID pertama
            $canonical = $targetProducts->sortBy('id')->first();
            $duplicates = $targetProducts->where('id', '!=', $canonical->id);

            foreach ($duplicates as $dup) {
                $this->line("   - Menggabungkan Produk ID {$dup->id} ({$dup->sku} - {$dup->name}) -> ID Utama {$canonical->id} ({$canonical->sku})");
                $this->repointProductTransactions($dup->id, $canonical->id, $apply);

                if ($apply) {
                    $dup->update([
                        'is_active' => false,
                        'name' => '[DUPLIKAT - NONAKTIF] ' . $dup->name,
                    ]);
                }
                $changes++;
            }
        }

        // B. Cek produk dengan nama yang persis sama di database
        $duplicateNames = Product::select('name', DB::raw('count(*) as c'))
            ->whereNull('deleted_at')
            ->groupBy('name')
            ->having('c', '>', 1)
            ->pluck('name');

        if ($duplicateNames->isNotEmpty()) {
            $this->warn("   Ditemukan " . $duplicateNames->count() . " grup produk dengan nama sama:");
            foreach ($duplicateNames as $name) {
                $group = Product::where('name', $name)->orderBy('id')->get();
                $canonical = $group->first();
                $dups = $group->slice(1);

                $this->line("   - Grup: \"{$name}\" (ID Utama: {$canonical->id}, Duplikat: " . $dups->pluck('id')->implode(', ') . ")");
                foreach ($dups as $dup) {
                    $this->repointProductTransactions($dup->id, $canonical->id, $apply);
                    if ($apply) {
                        $dup->update([
                            'is_active' => false,
                            'name' => '[DUPLIKAT - NONAKTIF] ' . $dup->name,
                        ]);
                    }
                    $changes++;
                }
            }
        }

        if ($changes === 0) {
            $this->line('   ✓ Tidak ditemukan produk duplikat yang perlu digabung.');
        } else {
            $this->info($apply ? "   ✓ {$changes} produk duplikat berhasil dikonsolidasikan." : "   [DRY-RUN] {$changes} produk duplikat akan dikonsolidasikan.");
        }

        return $changes;
    }

    /**
     * Alihkan transaksi yang merujuk product_id duplikat ke product_id utama.
     */
    protected function repointProductTransactions(int $fromProductId, int $toProductId, bool $apply): void
    {
        $tablesWithProductId = [
            'sale_order_items' => 'product_id',
            'delivery_order_items' => 'product_id',
            'quotation_items' => 'product_id',
            'purchase_order_items' => 'product_id',
            'purchase_receipt_items' => 'product_id',
        ];

        foreach ($tablesWithProductId as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->where($column, $fromProductId)->count();
            if ($count > 0) {
                $this->line("     * {$count} baris di {$table} dialihkan dari Produk #{$fromProductId} ke #{$toProductId}");
                if ($apply) {
                    DB::table($table)->where($column, $fromProductId)->update([$column => $toProductId]);
                }
            }
        }
    }

    /**
     * 2. Konsolidasi Customer Duplikat
     */
    protected function consolidateCustomers(CustomerMerger $customerMerger, bool $apply): int
    {
        $this->newLine();
        $this->info('2. Memeriksa Duplikasi Master Customer...');
        $changes = 0;

        // A. Cek customer dengan kata kunci DAYA TEKNIK MEDIKA
        $dayaCustomers = Customer::where(function ($q) {
            $q->where('name', 'like', '%DAYA%TEKNIK%')
                ->orWhere('perusahaan', 'like', '%DAYA%TEKNIK%');
        })->whereNull('merged_into')->get();

        if ($dayaCustomers->count() > 1) {
            $this->warn("   Ditemukan {$dayaCustomers->count()} customer DAYA TEKNIK MEDIKA.");
            $canonical = $dayaCustomers->sortBy('id')->first();
            $duplicates = $dayaCustomers->where('id', '!=', $canonical->id);

            foreach ($duplicates as $dup) {
                $this->line("   - Menggabungkan Customer ID {$dup->id} ({$dup->name}) -> ID Utama {$canonical->id} ({$canonical->name})");
                if ($apply) {
                    try {
                        $customerMerger->merge($canonical, $dup, 'Konsolidasi master data ganda DAYA TEKNIK MEDIKA');
                    } catch (\Throwable $e) {
                        $this->error("     Gagal menggabung Customer #{$dup->id}: " . $e->getMessage());
                    }
                }
                $changes++;
            }
        }

        // B. Cek customer dengan nama/perusahaan identik
        $dupNames = Customer::select('name', DB::raw('count(*) as c'))
            ->whereNull('deleted_at')
            ->whereNull('merged_into')
            ->groupBy('name')
            ->having('c', '>', 1)
            ->pluck('name');

        foreach ($dupNames as $name) {
            $group = Customer::where('name', $name)->whereNull('merged_into')->orderBy('id')->get();
            if ($group->count() <= 1) {
                continue;
            }

            $canonical = $group->first();
            $dups = $group->slice(1);

            $this->warn("   Ditemukan duplikasi customer bernama \"{$name}\" (Utama: #{$canonical->id}):");
            foreach ($dups as $dup) {
                $this->line("   - Menggabungkan #{$dup->id} -> #{$canonical->id}");
                if ($apply) {
                    try {
                        $customerMerger->merge($canonical, $dup, 'Konsolidasi master data ganda nama identik');
                    } catch (\Throwable $e) {
                        $this->error("     Gagal menggabung Customer #{$dup->id}: " . $e->getMessage());
                    }
                }
                $changes++;
            }
        }

        if ($changes === 0) {
            $this->line('   ✓ Tidak ditemukan customer duplikat aktif di database.');
        } else {
            $this->info($apply ? "   ✓ {$changes} customer duplikat berhasil dikonsolidasikan." : "   [DRY-RUN] {$changes} customer duplikat akan dikonsolidasikan.");
        }

        return $changes;
    }

    /**
     * 3. Konsolidasi Supplier Duplikat
     */
    protected function consolidateSuppliers(bool $apply): int
    {
        $this->newLine();
        $this->info('3. Memeriksa Duplikasi Master Supplier...');
        $changes = 0;

        // A. Cek supplier dengan kata kunci Abdi Karya
        $abdiSuppliers = Supplier::where(function ($q) {
            $q->where('perusahaan', 'like', '%Abdi%Karya%')
                ->orWhere('kontak_person', 'like', '%Abdi%Karya%')
                ->orWhere('perusahaan', 'like', '%Abdi Karya%');
        })->get();

        if ($abdiSuppliers->count() > 1) {
            $this->warn("   Ditemukan {$abdiSuppliers->count()} supplier Abdi Karya.");
            $canonical = $abdiSuppliers->sortBy('id')->first();
            $duplicates = $abdiSuppliers->where('id', '!=', $canonical->id);

            foreach ($duplicates as $dup) {
                $this->line("   - Menggabungkan Supplier ID {$dup->id} ({$dup->perusahaan}) -> ID Utama {$canonical->id} ({$canonical->perusahaan})");
                $this->repointSupplierTransactions($dup->id, $canonical->id, $apply);
                if ($apply) {
                    $dup->delete(); // Soft-delete
                }
                $changes++;
            }
        }

        // B. Cek supplier dengan nama perusahaan identik
        $dupPerusahaan = Supplier::select('perusahaan', DB::raw('count(*) as c'))
            ->whereNull('deleted_at')
            ->groupBy('perusahaan')
            ->having('c', '>', 1)
            ->pluck('perusahaan');

        foreach ($dupPerusahaan as $comp) {
            $group = Supplier::where('perusahaan', $comp)->orderBy('id')->get();
            if ($group->count() <= 1) {
                continue;
            }

            $canonical = $group->first();
            $dups = $group->slice(1);

            $this->warn("   Ditemukan duplikasi supplier perusahaan \"{$comp}\" (Utama: #{$canonical->id}):");
            foreach ($dups as $dup) {
                $this->line("   - Menggabungkan #{$dup->id} -> #{$canonical->id}");
                $this->repointSupplierTransactions($dup->id, $canonical->id, $apply);
                if ($apply) {
                    $dup->delete();
                }
                $changes++;
            }
        }

        if ($changes === 0) {
            $this->line('   ✓ Tidak ditemukan supplier duplikat di database.');
        } else {
            $this->info($apply ? "   ✓ {$changes} supplier duplikat berhasil dikonsolidasikan." : "   [DRY-RUN] {$changes} supplier duplikat akan dikonsolidasikan.");
        }

        return $changes;
    }

    /**
     * Alihkan transaksi yang merujuk supplier_id duplikat ke supplier_id utama.
     */
    protected function repointSupplierTransactions(int $fromSupplierId, int $toSupplierId, bool $apply): void
    {
        $tablesWithSupplierId = [
            'purchase_orders' => 'supplier_id',
            'invoices' => 'supplier_id',
            'payment_requests' => 'supplier_id',
            'products' => 'supplier_id',
        ];

        foreach ($tablesWithSupplierId as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->where($column, $fromSupplierId)->count();
            if ($count > 0) {
                $this->line("     * {$count} baris di {$table} dialihkan dari Supplier #{$fromSupplierId} ke #{$toSupplierId}");
                if ($apply) {
                    DB::table($table)->where($column, $fromSupplierId)->update([$column => $toSupplierId]);
                }
            }
        }
    }
}
