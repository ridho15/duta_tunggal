<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductInventoryCoaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-inventory-coa
                            {--dry-run : Jalankan simulasi tanpa menyimpan perubahan ke database}
                            {--force : Paksa perbarui semua produk ke COA default sesuai tipe/kategorinya}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronkan COA persediaan pada master produk (1140.10 Barang Dagang, 1140.01/1-101 Bahan Baku, 1140.02 Barang Produksi)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $this->info('=====================================================');
        $this->info(' SINKRONISASI COA PERSEDIAAN PRODUK (ISSUE 15)       ');
        $this->info('=====================================================');
        $this->info('Mode: ' . ($isDryRun ? 'SIMULASI (DRY-RUN)' : 'EKSEKUSI LANGSUNG'));
        $this->info('Force: ' . ($isForce ? 'YA (Semua produk)' : 'TIDAK (Hanya salah klasifikasi / kosong)'));
        $this->newLine();

        $rawCoa = ChartOfAccount::where('code', '1140.01')->first();
        $dagangCoa = ChartOfAccount::where('code', config('coa.inventory', '1140.10'))->first();
        $prodCoa = ChartOfAccount::where('code', '1140.02')->first();

        if (! $dagangCoa) {
            $this->error('COA Persediaan Barang Dagangan (' . config('coa.inventory', '1140.10') . ') tidak ditemukan di database.');
            return self::FAILURE;
        }

        $standardTargetId = $dagangCoa->id;
        $rawTargetId = Product::resolveDefaultProductCoaId('inventory_coa_id', false, true) ?? $rawCoa?->id;
        $manufactureTargetId = Product::resolveDefaultProductCoaId('inventory_coa_id', true, false) ?? $prodCoa?->id ?? $standardTargetId;

        $this->line("Target COA Standard (Barang Dagang) : [{$dagangCoa->code}] {$dagangCoa->name} (ID: {$standardTargetId})");
        $this->line("Target COA Raw Material (Bahan Baku): [1140.01] (ID: " . ($rawTargetId ?? 'None') . ")");
        $this->line("Target COA Manufacture (Produksi)   : [1140.02] (ID: " . ($manufactureTargetId ?? 'None') . ")");
        $this->newLine();

        $products = Product::all();
        $totalScanned = $products->count();
        $updatedCount = 0;
        $details = [];

        foreach ($products as $product) {
            $currentCoaId = $product->inventory_coa_id;
            $targetCoaId = null;
            $typeLabel = 'Standard';

            if ($product->is_raw_material) {
                $targetCoaId = $rawTargetId;
                $typeLabel = 'Bahan Baku';
            } elseif ($product->is_manufacture) {
                $targetCoaId = $manufactureTargetId;
                $typeLabel = 'Produksi';
            } else {
                $targetCoaId = $standardTargetId;
                $typeLabel = 'Barang Dagangan';
            }

            if (! $targetCoaId) {
                continue;
            }

            $shouldUpdate = false;

            if ($isForce) {
                $shouldUpdate = ($currentCoaId !== $targetCoaId);
            } else {
                // Perbarui jika null ATAU jika produk non-bahan-baku saat ini keliru diarahkan ke 1140.01 (Bahan Baku)
                if (is_null($currentCoaId)) {
                    $shouldUpdate = true;
                } elseif (! $product->is_raw_material && $rawCoa && $currentCoaId === $rawCoa->id) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                $updatedCount++;
                $details[] = [
                    'ID' => $product->id,
                    'SKU' => $product->sku ?? '-',
                    'Nama' => mb_strimwidth($product->name, 0, 30, '...'),
                    'Tipe' => $typeLabel,
                    'COA Lama' => $currentCoaId ? (ChartOfAccount::find($currentCoaId)?->code ?? $currentCoaId) : '(Kosong)',
                    'COA Baru' => ChartOfAccount::find($targetCoaId)?->code ?? $targetCoaId,
                ];

                if (! $isDryRun) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['inventory_coa_id' => $targetCoaId]);
                }
            }
        }

        if (! empty($details)) {
            $this->table(
                ['ID', 'SKU', 'Nama Produk', 'Tipe', 'COA Lama', 'COA Baru'],
                array_slice($details, 0, 15)
            );

            if (count($details) > 15) {
                $this->line('... dan ' . (count($details) - 15) . ' produk lainnya.');
            }
        }

        $this->newLine();
        $this->info("Total produk dipindai : {$totalScanned}");
        $this->info("Total produk diubah    : {$updatedCount}");

        if ($isDryRun) {
            $this->warn('Simulasi selesai. Jalankan kembali tanpa flag --dry-run untuk menyimpan perubahan.');
        } else {
            $this->info('Sinkronisasi COA persediaan berhasil diterapkan ke database.');
        }

        return self::SUCCESS;
    }
}
