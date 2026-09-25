<?php

namespace App\Console\Commands;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentRequest;
use App\Models\PurchaseReturn;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemediateLegacyCorruptedDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:remediate-data
        {--dry-run : Hanya tampilkan ringkasan rencana perbaikan tanpa mengubah database}
        {--so= : Batasi perbaikan progres pengiriman ke nomor SO tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remediasi data transaksi historis yang rusak/inkonsisten (Jurnal tidak seimbang, relasi DO-SO, status PR lunas, dan retur uji)';

    public function handle(SaleOrderDeliveryProgress $deliveryProgress): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('================================================================');
        $this->info('  REMEDIASI DATA HISTORIS RUSAK / INKONSISTEN (DUTA TUNGGAL ERP)');
        $this->info('  Mode: ' . ($dryRun ? 'DRY-RUN (Tidak ada perubahan yang disimpan)' : 'APPLY (Perubahan akan disimpan permanen)'));
        $this->info('================================================================');

        $remediatedCount = 0;

        DB::beginTransaction();
        try {
            // 1. Remediasi Jurnal Tidak Seimbang / Draft Invoices
            $remediatedCount += $this->remediateUnbalancedOrDraftJournals($dryRun);

            // 2. Remediasi Relasi Item DO ke SO dan update cache delivered_quantity
            $remediatedCount += $this->remediateDeliveryOrderRelationsAndProgress($deliveryProgress, $dryRun);

            // 3. Remediasi Status Payment Request (PR) yang Invoicenya sudah lunas
            $remediatedCount += $this->remediatePaymentRequestStatuses($dryRun);

            // 4. Remediasi / Pembersihan Data Uji Retur Pembelian (NR-20260922-8073 / Retur #17)
            $remediatedCount += $this->remediateTestPurchaseReturn($dryRun);

            // 5. Remediasi Phantom Reserved Stock (Kalkulasi Ulang Cadangan Stok ke Sumber Asli)
            $remediatedCount += $this->remediatePhantomStockReservations($dryRun);

            // 6. Remediasi Pembayaran Vendor yang Menggunakan Akun Induk (1100 / 1110)
            $remediatedCount += $this->remediateVendorPaymentsWithParentCoa($dryRun);

            // 7. Remediasi / Pembersihan Dokumen Uji Tambahan (CR-2026-0001 & DO-20260922-0003)
            $remediatedCount += $this->remediateTestDocuments($dryRun);

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('✓ [DRY-RUN BERHASIL] Transaksi dibatalkan (rollback). Tidak ada data di database yang diubah.');
                $this->comment('Untuk mengeksekusi secara permanen, jalankan kembali tanpa opsi --dry-run:');
                $this->line('  php artisan legacy:remediate-data');
            } else {
                DB::commit();
                $this->newLine();
                $this->info("✓ [SUKSES] Seluruh remediasi data historis ({$remediatedCount} tindakan) berhasil diterapkan ke database.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Terjadi kesalahan saat remediasi: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * 1. Periksa dan bersihkan jurnal tidak seimbang atau jurnal yang melekat pada draft invoice.
     */
    protected function remediateUnbalancedOrDraftJournals(bool $dryRun): int
    {
        $this->newLine();
        $this->info('1. Memeriksa Integritas Jurnal Akuntansi...');
        $changes = 0;

        // A. Jurnal tidak balance per referensi (debit != credit)
        $unbalanced = JournalEntry::select('reference', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'), DB::raw('ABS(SUM(debit) - SUM(credit)) as diff'))
            ->groupBy('reference')
            ->havingRaw('diff > 0.01')
            ->get();

        if ($unbalanced->isNotEmpty()) {
            $this->warn("   Ditemukan {$unbalanced->count()} grup jurnal tidak seimbang:");
            foreach ($unbalanced as $u) {
                $this->line("   - Ref: {$u->reference} | Debit: {$u->total_debit} | Credit: {$u->total_credit} | Selisih: {$u->diff}");
                if (! $dryRun) {
                    JournalEntry::where('reference', $u->reference)->delete();
                }
                $changes++;
            }
            $this->info($dryRun ? '   [DRY-RUN] Entri jurnal tidak seimbang akan dibersihkan.' : '   ✓ Entri jurnal tidak seimbang telah dibersihkan.');
        } else {
            $this->line('   ✓ Tidak ditemukan entri jurnal yang tidak seimbang di buku besar.');
        }

        // B. Jurnal tak bertuan untuk INV-20260918-0002 atau draft invoice
        $draftInvoiceJournals = JournalEntry::where(function ($q) {
            $q->where('reference', 'like', '%INV-20260918-0002%')
                ->orWhere('description', 'like', '%INV-20260918-0002%')
                ->orWhere(function ($sub) {
                    $sub->where('credit', '165053')
                        ->orWhere('credit', '165053.00')
                        ->orWhere('debit', '165053')
                        ->orWhere('debit', '165053.00');
                });
        })->get();

        if ($draftInvoiceJournals->isNotEmpty()) {
            $this->warn("   Ditemukan {$draftInvoiceJournals->count()} baris jurnal terkait INV-20260918-0002 / nominal Rp 165.053:");
            foreach ($draftInvoiceJournals as $j) {
                $this->line("   - ID {$j->id}: Ref {$j->reference}, Debit: {$j->debit}, Credit: {$j->credit}, Desc: {$j->description}");
                if (! $dryRun) {
                    $j->delete();
                }
                $changes++;
            }
            $this->info($dryRun ? '   [DRY-RUN] Baris jurnal pendapatan tak berpasangan akan dibersihkan.' : '   ✓ Baris jurnal pendapatan tak berpasangan berhasil dihapus.');
        } else {
            $this->line('   ✓ Jurnal INV-20260918-0002 / nominal Rp 165.053 sudah bersih di database.');
        }

        return $changes;
    }

    /**
     * 2. Tautkan item DO yang kehilangan sale_order_item_id, lalu perbarui cache delivered_quantity.
     */
    protected function remediateDeliveryOrderRelationsAndProgress(SaleOrderDeliveryProgress $deliveryProgress, bool $dryRun): int
    {
        $this->newLine();
        $this->info('2. Memeriksa Relasi Item DO ke SO dan Cache delivered_quantity...');
        $changes = 0;

        // Cari item DO yang sale_order_item_id masih NULL
        $orphanItems = DeliveryOrderItem::whereNull('sale_order_item_id')->get();
        if ($orphanItems->isNotEmpty()) {
            $this->warn("   Ditemukan {$orphanItems->count()} item DO tanpa tautan sale_order_item_id:");
            foreach ($orphanItems as $item) {
                $do = $item->deliveryOrder;
                if (! $do) {
                    continue;
                }

                // Cari SO yang terhubung ke DO ini lewat delivery_sales_orders
                $soIds = DB::table('delivery_sales_orders')
                    ->where('delivery_order_id', $do->id)
                    ->pluck('sales_order_id');

                // Cari item SO yang memiliki product_id sama
                $matchingSoItem = SaleOrderItem::whereIn('sale_order_id', $soIds)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($matchingSoItem) {
                    $this->line("   - Item DO ID {$item->id} (DO {$do->do_number}, Produk {$item->product_id}) -> Ditautkan ke Item SO ID {$matchingSoItem->id} (SO ID {$matchingSoItem->sale_order_id})");
                    if (! $dryRun) {
                        $item->update(['sale_order_item_id' => $matchingSoItem->id]);
                    }
                    $changes++;
                } else {
                    $this->line("   - Item DO ID {$item->id} (DO {$do->do_number}, Produk {$item->product_id}): Tidak ada kecocokan SO item langsung.");
                }
            }
        } else {
            $this->line('   ✓ Semua baris item Delivery Order telah memiliki relasi sale_order_item_id.');
        }

        // Khusus SO-00005 & SO-00006: periksa apakah ada pengiriman fisik yang tercatat
        $legacySos = SaleOrder::whereIn('so_number', ['SO-00005', 'SO-00006'])->get();
        foreach ($legacySos as $so) {
            $items = $so->items;
            foreach ($items as $item) {
                // Jika masih 0 dan ada DO terkait atau SO ditandai sudah dikirim
                $deliveredFromDo = (float) DeliveryOrderItem::where('sale_order_item_id', $item->id)
                    ->whereHas('deliveryOrder', fn ($q) => $q->whereIn('status', DeliveryOrder::DELIVERED_STATUSES))
                    ->sum('quantity');

                if ($deliveredFromDo > 0 && abs((float) $item->delivered_quantity - $deliveredFromDo) > 0.001) {
                    $this->line("   - {$so->so_number} (Item {$item->id}): delivered_quantity diperbarui {$item->delivered_quantity} -> {$deliveredFromDo}");
                    if (! $dryRun) {
                        $item->update(['delivered_quantity' => $deliveredFromDo]);
                    }
                    $changes++;
                }
            }
        }

        // Sinkronisasi seluruh cache SO
        $soQuery = SaleOrder::withoutGlobalScopes()->whereNull('deleted_at');
        if ($targetSo = $this->option('so')) {
            $soQuery->where('so_number', $targetSo);
        }

        $allSos = $soQuery->get();
        $resynced = 0;
        foreach ($allSos as $so) {
            $summary = $deliveryProgress->forSaleOrder($so);
            foreach ($summary['items'] as $itemId => $row) {
                $current = (float) DB::table('sale_order_items')->where('id', $itemId)->value('delivered_quantity');
                if (abs($current - $row['delivered']) > 0.0001) {
                    if (! $dryRun) {
                        DB::table('sale_order_items')->where('id', $itemId)->update(['delivered_quantity' => $row['delivered']]);
                    }
                    $resynced++;
                    $changes++;
                }
            }
        }

        if ($resynced > 0) {
            $this->info($dryRun ? "   [DRY-RUN] {$resynced} item SO akan disinkronkan delivered_quantity-nya." : "   ✓ {$resynced} item SO berhasil disinkronkan delivered_quantity-nya.");
        } else {
            $this->line('   ✓ Seluruh cache delivered_quantity pada Sales Order sudah sinkron dengan Delivery Order.');
        }

        return $changes;
    }

    /**
     * 3. Periksa PaymentRequest yang seluruh invoice-nya sudah lunas namun status PR belum lunas.
     */
    protected function remediatePaymentRequestStatuses(bool $dryRun): int
    {
        $this->newLine();
        $this->info('3. Memeriksa Status Permintaan Pembayaran (Payment Request)...');
        $changes = 0;

        $paymentRequests = PaymentRequest::whereNotIn('status', ['paid', 'rejected'])->get();
        foreach ($paymentRequests as $pr) {
            $selectedInvoiceIds = $pr->selected_invoices ?? [];
            if (empty($selectedInvoiceIds)) {
                continue;
            }

            // Cek apakah semua invoice di list sudah berstatus 'paid'
            $unpaidCount = Invoice::whereIn('id', $selectedInvoiceIds)
                ->where('status', '!=', 'paid')
                ->count();

            if ($unpaidCount === 0) {
                $this->warn("   PR {$pr->request_number} (ID {$pr->id}): Seluruh fakturnya sudah LUNAS, namun status PR masih \"{$pr->status}\".");
                $this->line("   - Mengubah status PR {$pr->request_number} menjadi \"paid\" (Lunas).");
                if (! $dryRun) {
                    $pr->update(['status' => 'paid']);
                }
                $changes++;
            }
        }

        if ($changes === 0) {
            $this->line('   ✓ Seluruh status Permintaan Pembayaran (PR) sudah sinkron dengan status invoice.');
        } else {
            $this->info($dryRun ? "   [DRY-RUN] {$changes} status PR akan diperbarui menjadi Lunas." : "   ✓ {$changes} status PR berhasil diperbarui menjadi Lunas.");
        }

        return $changes;
    }

    /**
     * 4. Periksa dan hapus entri retur uji coba (NR-20260922-8073 / Retur #17).
     */
    protected function remediateTestPurchaseReturn(bool $dryRun): int
    {
        $this->newLine();
        $this->info('4. Memeriksa Data Uji Retur Pembelian (NR-20260922-8073 / Retur #17)...');
        $changes = 0;

        $testReturn = PurchaseReturn::withTrashed()
            ->where(function ($q) {
                $q->where('nota_retur', 'like', '%NR-20260922-8073%')
                    ->orWhere('id', 17);
            })->first();

        if ($testReturn) {
            $this->warn("   Ditemukan entri retur uji coba: ID {$testReturn->id}, Nota: {$testReturn->nota_retur}");
            if (! $dryRun) {
                $testReturn->purchaseReturnItem()->forceDelete();
                $testReturn->forceDelete();
            }
            $changes++;
            $this->info($dryRun ? '   [DRY-RUN] Entri retur uji coba #17 akan dihapus permanen.' : '   ✓ Entri retur uji coba #17 berhasil dihapus permanen.');
        } else {
            $this->line('   ✓ Data uji retur pembelian NR-20260922-8073 / #17 sudah tidak ada di database.');
        }

        return $changes;
    }

    /**
     * 5. Sinkronisasi cadangan stok (qty_reserved) di inventory_stocks dengan reservasi aktual di stock_reservations.
     */
    protected function remediatePhantomStockReservations(bool $dryRun): int
    {
        $this->newLine();
        $this->info('5. Memeriksa dan Menghitung Ulang Cadangan Stok (qty_reserved)...');
        $changes = 0;

        $stocksWithReservation = DB::table('inventory_stocks')
            ->whereNull('deleted_at')
            ->where('qty_reserved', '>', 0)
            ->get();

        if ($stocksWithReservation->isNotEmpty()) {
            $this->warn("   Ditemukan {$stocksWithReservation->count()} baris stok dengan nilai cadangan (qty_reserved > 0).");

            foreach ($stocksWithReservation as $stock) {
                // Hitung reservasi aktif sebenarnya dari tabel stock_reservations
                $actualReserved = (float) DB::table('stock_reservations')
                    ->where('product_id', $stock->product_id)
                    ->where('warehouse_id', $stock->warehouse_id)
                    ->when($stock->rak_id, fn ($q) => $q->where('rak_id', $stock->rak_id))
                    ->sum('quantity');

                if (abs((float) $stock->qty_reserved - $actualReserved) > 0.001) {
                    $product = DB::table('products')->where('id', $stock->product_id)->first();
                    $warehouse = DB::table('warehouses')->where('id', $stock->warehouse_id)->first();
                    $productName = $product ? $product->name : "ID {$stock->product_id}";
                    $warehouseName = $warehouse ? $warehouse->name : "ID {$stock->warehouse_id}";

                    $this->line("   - Stok ID {$stock->id} [{$productName} @ {$warehouseName}]: qty_reserved {$stock->qty_reserved} -> {$actualReserved}");

                    if (! $dryRun) {
                        DB::table('inventory_stocks')
                            ->where('id', $stock->id)
                            ->update([
                                'qty_reserved' => $actualReserved,
                                'updated_at' => now(),
                            ]);
                    }
                    $changes++;
                }
            }

            $this->info($dryRun ? "   [DRY-RUN] {$changes} baris cadangan stok phantom akan disinkronkan." : "   ✓ {$changes} baris cadangan stok berhasil disinkronkan ke nilai aktual.");
        } else {
            $this->line('   ✓ Tidak ada baris stok dengan cadangan phantom.');
        }

        return $changes;
    }

    /**
     * 6. Alihkan pembayaran vendor dan jurnal terkait yang menggunakan akun induk (1100 / 1110) ke akun kas/bank operasional.
     */
    protected function remediateVendorPaymentsWithParentCoa(bool $dryRun): int
    {
        $this->newLine();
        $this->info('6. Memeriksa Penggunaan Akun Induk pada Pembayaran Vendor (Vendor Payment)...');
        $changes = 0;

        // Akun induk yang tidak boleh dipakai posting langsung: 1100, 1110, 1111, 1112
        $parentCoaIds = DB::table('chart_of_accounts')
            ->whereIn('code', ['1100', '1110', '1111', '1112'])
            ->pluck('id')
            ->toArray();

        // Cari akun operasional Bank BCA Operasional (1112.01)
        $targetBcaCoa = DB::table('chart_of_accounts')
            ->where('code', '1112.01')
            ->first();
        $targetCoaId = $targetBcaCoa ? $targetBcaCoa->id : 93;

        $invalidPayments = DB::table('vendor_payments')
            ->whereIn('coa_id', $parentCoaIds)
            ->get();

        if ($invalidPayments->isNotEmpty()) {
            $this->warn("   Ditemukan {$invalidPayments->count()} pembayaran vendor menggunakan akun induk:");
            foreach ($invalidPayments as $vp) {
                $this->line("   - VP {$vp->payment_number} (ID {$vp->id}): coa_id {$vp->coa_id} -> {$targetCoaId} (Bank BCA Operasional)");
                if (! $dryRun) {
                    DB::table('vendor_payments')->where('id', $vp->id)->update([
                        'coa_id' => $targetCoaId,
                        'updated_at' => now(),
                    ]);

                    // Perbarui juga jurnal kredit kas/bank yang bersumber dari pembayaran ini
                    DB::table('journal_entries')
                        ->where('source_type', 'App\Models\VendorPayment')
                        ->where('source_id', $vp->id)
                        ->whereIn('coa_id', $parentCoaIds)
                        ->update([
                            'coa_id' => $targetCoaId,
                            'updated_at' => now(),
                        ]);

                    DB::table('journal_entries')
                        ->where('reference', $vp->payment_number)
                        ->whereIn('coa_id', $parentCoaIds)
                        ->update([
                            'coa_id' => $targetCoaId,
                            'updated_at' => now(),
                        ]);
                }
                $changes++;
            }
            $this->info($dryRun ? "   [DRY-RUN] {$changes} transaksi pembayaran vendor akan dialihkan ke akun operasional." : "   ✓ {$changes} transaksi pembayaran vendor dialihkan ke akun operasional.");
        } else {
            $this->line('   ✓ Seluruh pembayaran vendor telah menggunakan akun kas/bank operasional yang valid.');
        }

        return $changes;
    }

    /**
     * 7. Bersihkan dokumen uji coba tambahan (CR-2026-0001 & DO-20260922-0003).
     */
    protected function remediateTestDocuments(bool $dryRun): int
    {
        $this->newLine();
        $this->info('7. Memeriksa Dokumen Uji Coba Tambahan (CR-2026-0001 & DO-20260922-0003)...');
        $changes = 0;

        // A. Cek Retur Pelanggan CR-2026-0001
        $testReturns = DB::table('customer_returns')
            ->where('return_number', 'like', '%CR-2026-0001%')
            ->get();

        foreach ($testReturns as $tr) {
            $this->warn("   Ditemukan dokumen retur pelanggan uji coba: ID {$tr->id} ({$tr->return_number})");
            if (! $dryRun) {
                // Hapus item
                DB::table('customer_return_items')->where('customer_return_id', $tr->id)->delete();
                // Hapus jurnal terkait
                DB::table('journal_entries')
                    ->where(function ($q) use ($tr) {
                        $q->where('source_type', 'App\Models\CustomerReturn')->where('source_id', $tr->id)
                            ->orWhere('reference', $tr->return_number);
                    })->delete();
                // Hapus retur
                DB::table('customer_returns')->where('id', $tr->id)->delete();
            }
            $changes++;
        }

        // B. Cek DO-20260922-0003
        $testDos = DB::table('delivery_orders')
            ->where('do_number', 'like', '%DO-20260922-0003%')
            ->get();

        foreach ($testDos as $td) {
            $this->warn("   Ditemukan dokumen DO uji coba: ID {$td->id} ({$td->do_number})");
            if (! $dryRun) {
                DB::table('delivery_order_items')->where('delivery_order_id', $td->id)->delete();
                DB::table('delivery_sales_orders')->where('delivery_order_id', $td->id)->delete();
                DB::table('delivery_orders')->where('id', $td->id)->delete();
            }
            $changes++;
        }

        // C. Cek Sales Order Uji Coba Menggantung (SO-00006 & SO-00007)
        $testSos = SaleOrder::whereIn('so_number', ['SO-00006', 'SO-00007'])
            ->where('status', 'draft')
            ->get();

        foreach ($testSos as $tso) {
            $hasDo = DB::table('delivery_sales_orders')->where('sales_order_id', $tso->id)->exists();
            if (! $hasDo) {
                $this->warn("   Ditemukan SO uji coba menggantung (status draft tanpa DO): {$tso->so_number}");
                $this->line("   - Mengubah status {$tso->so_number} menjadi 'canceled' (Dibatalkan).");
                if (! $dryRun) {
                    $tso->update(['status' => 'canceled']);
                }
                $changes++;
            }
        }

        if ($changes === 0) {
            $this->line('   ✓ Tidak ditemukan dokumen uji coba tersisa (CR-2026-0001 / DO-20260922-0003 / SO uji coba).');
        } else {
            $this->info($dryRun ? "   [DRY-RUN] {$changes} dokumen uji coba akan dibersihkan." : "   ✓ {$changes} dokumen uji coba berhasil dibersihkan.");
        }

        return $changes;
    }
}
