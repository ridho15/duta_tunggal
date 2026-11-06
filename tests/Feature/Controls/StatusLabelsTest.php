<?php

/**
 * T7.1 — Label status terpusat (usulan 15): pemetaan, tampilan layar Bahasa Indonesia, dan PEMINDAI yang gagal bila ada kolom/entri status
 * Filament tanpa pemetaan label (di luar daftar utang modul non-penjualan) atau string status mentah pada Blade cetak penjualan.
 */

use App\Support\StatusLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('label per domain: konstanta model + tambahan; tidak dikenal → judul (bukan mentah/Inggris); kosong → "-"', function () {
    expect(StatusLabels::label('invoice', 'unpaid'))->toBe('Belum Dibayar')
        ->and(StatusLabels::label('invoice', 'partially_paid'))->toBe('Dibayar Sebagian')
        ->and(StatusLabels::label('invoice', 'cancelled'))->toBe('Dibatalkan')
        ->and(StatusLabels::label('customer_receipt', 'Paid'))->toBe('Lunas')
        ->and(StatusLabels::label('customer_receipt', 'Cancelled'))->toBe('Dibatalkan')
        ->and(StatusLabels::label('warehouse_confirmation', 'partial_confirmed'))->toBe('Dikonfirmasi Sebagian')
        ->and(StatusLabels::label('other_sale', 'posted'))->toBe('Diposting')
        ->and(StatusLabels::label('deposit', 'active'))->toBe('Aktif')
        ->and(StatusLabels::label('sale_order', 'request_approve'))->toBe('Menunggu approve kasir')
        ->and(StatusLabels::label('delivery_order', 'request_stock'))->toBe('Menunggu Konfirmasi Stok')
        ->and(StatusLabels::label('credit_note', 'issued'))->toBe('Terbit')
        ->and(StatusLabels::label('customer_return', 'qc_inspection'))->toBe('Inspeksi QC')
        ->and(StatusLabels::label('invoice', 'some_new_state'))->toBe('Some New State')
        ->and(StatusLabels::label('invoice', null))->toBe('-')->and(StatusLabels::label('invoice', ''))->toBe('-')
        ->and(StatusLabels::formatter('invoice')('paid'))->toBe('Lunas');
});

/** Modul non-penjualan yang MASIH memiliki kolom status mentah (utang; dikerjakan bertahap — daftar ini hanya boleh menyusut). */
const STATUS_SCAN_BACKLOG = [
    'AssetDisposalResource.php', 'AssetResource.php', 'AssetTransferResource.php', 'BankReconciliationResource.php', 'CashBankTransferResource.php',
    'LegacyTransactionArchiveResource.php', 'MaterialIssueResource', 'PurchaseReturnResource.php', 'QualityControlManufactureResource', 'QualityControlPurchaseResource.php',
    'StockAdjustmentResource.php', 'VendorPaymentResource',
];

it('pemindai: Blade cetak penjualan tidak mencetak status mentah (ucfirst/nilai langsung) — harus lewat label', function () {
    foreach (['sale-order-invoice', 'delivery-order', 'kwitansi', 'credit-note', 'customer-return', 'sales-order', 'quotation', 'surat-jalan'] as $name) {
        $source = file_get_contents(resource_path("views/pdf/{$name}.blade.php"));
        expect(preg_match('/ucfirst\(\s*\(?\s*(string\))?\s*\$[a-zA-Z_>\-]*status/', $source))->toBe(0, "{$name}: ucfirst(status)")
            ->and(preg_match('/\{\{\s*\$[a-zA-Z_]+->status\s*\}\}/', $source))->toBe(0, "{$name}: status mentah");
    }
});
