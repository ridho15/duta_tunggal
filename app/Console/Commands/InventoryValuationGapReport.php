<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * LAPORAN SAJA (tidak memposting apa pun). Mengukur selisih antara saldo Persediaan di Buku Besar
 * dengan nilai persediaan fisik saat ini pada HPP (keputusan Ko Handy: persediaan dinilai HPP).
 *
 * Saldo historis yang dulu dicatat pada harga PO membuat Buku Besar menyimpang dari nilai HPP.
 * Angka selisih di sini adalah dasar bagi AKUNTAN untuk membuat SATU jurnal koreksi manual
 * (Dr/Cr Persediaan lawan akun selisih/ekuitas) — perintah ini sengaja tidak menjurnal apa pun.
 */
class InventoryValuationGapReport extends Command
{
    protected $signature = 'inventory:valuation-gap {--no-csv : Jangan tulis CSV.}';

    protected $description = 'Laporan selisih saldo Persediaan Buku Besar vs nilai HPP persediaan fisik (read-only).';

    public function handle(): int
    {
        $defaultInventory = config('coa.inventory', '1140.10');

        // Nilai persediaan fisik saat ini pada HPP, dikelompokkan per COA persediaan produk.
        $stocks = InventoryStock::query()
            ->selectRaw('product_id, SUM(qty_available) as qty')
            ->groupBy('product_id')
            ->having('qty', '>', 0)
            ->pluck('qty', 'product_id');

        if ($stocks->isEmpty()) {
            $this->info('Tidak ada stok fisik > 0. Tidak ada yang dinilai.');

            return self::SUCCESS;
        }

        $products = Product::query()
            ->whereIn('id', $stocks->keys())
            ->get(['id', 'sku', 'name', 'cost_price', 'inventory_coa_id']);

        $hppByCoaCode = [];       // code => nilai HPP persediaan fisik
        $zeroCostProducts = [];   // produk tanpa HPP (cost_price 0) — tidak bisa dinilai

        foreach ($products as $p) {
            $qty = (float) ($stocks[$p->id] ?? 0);
            $cost = (float) ($p->cost_price ?? 0);
            $coa = $p->inventoryCoa && $p->inventoryCoa->code ? $p->inventoryCoa->code : $defaultInventory;

            if ($cost <= 0) {
                $zeroCostProducts[] = $p->sku.' ('.$p->name.')';
            }

            $hppByCoaCode[$coa] = ($hppByCoaCode[$coa] ?? 0) + $qty * $cost;
        }

        // Saldo Buku Besar tiap COA persediaan (debit − kredit).
        $codes = array_unique(array_merge(array_keys($hppByCoaCode), [$defaultInventory]));
        $rows = [];
        $totalGl = 0.0;
        $totalHpp = 0.0;

        foreach ($codes as $code) {
            $coa = ChartOfAccount::where('code', $code)->first();
            if (! $coa) {
                $rows[] = [$code, '(COA tidak ditemukan)', '-', '-', '-'];

                continue;
            }
            $debit = (float) JournalEntry::where('coa_id', $coa->id)->sum('debit');
            $credit = (float) JournalEntry::where('coa_id', $coa->id)->sum('credit');
            $glBalance = round($debit - $credit, 2);
            $hpp = round($hppByCoaCode[$code] ?? 0, 2);
            $gap = round($glBalance - $hpp, 2);

            $totalGl += $glBalance;
            $totalHpp += $hpp;

            $rows[] = [
                $code,
                $coa->name,
                number_format($glBalance, 2, ',', '.'),
                number_format($hpp, 2, ',', '.'),
                number_format($gap, 2, ',', '.'),
            ];
        }

        $this->table(['COA', 'Nama', 'Saldo Buku Besar', 'Nilai HPP Fisik', 'Selisih (BB − HPP)'], $rows);
        $this->line('');
        $this->info('Total Saldo Buku Besar : Rp '.number_format($totalGl, 2, ',', '.'));
        $this->info('Total Nilai HPP Fisik  : Rp '.number_format($totalHpp, 2, ',', '.'));
        $this->warn('Total Selisih          : Rp '.number_format($totalGl - $totalHpp, 2, ',', '.'));
        $this->line('');
        $this->line('Selisih POSITIF = Buku Besar lebih tinggi dari nilai HPP (persediaan historis dicatat terlalu mahal / harga PO).');
        $this->line('Angka ini adalah usulan untuk akuntan membuat SATU jurnal koreksi manual. Perintah ini tidak menjurnal apa pun.');

        if (! empty($zeroCostProducts)) {
            $this->line('');
            $this->warn('Produk tanpa HPP (cost_price 0) — nilainya 0 di laporan, perlu isi HPP dulu: '.count($zeroCostProducts).' produk.');
        }

        if (! $this->option('no-csv')) {
            $csv = "coa,nama,saldo_buku_besar,nilai_hpp_fisik,selisih\n";
            foreach ($rows as $r) {
                $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r))."\n";
            }
            $path = 'audits/inventory-valuation-gap-'.now()->format('Ymd-His').'.csv';
            Storage::disk('local')->put($path, $csv);
            $this->info('CSV: storage/app/'.$path);
        }

        return self::SUCCESS;
    }
}
