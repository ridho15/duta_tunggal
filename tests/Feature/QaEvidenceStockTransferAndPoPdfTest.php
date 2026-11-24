<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use App\Services\StockAvailability;

class QaEvidenceStockTransferAndPoPdfTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * Skenario 1: Coba 1 Mutasi -> Setujui -> Kirim Sebagian (70 dari 100) -> Terima di Gudang Tujuan
     */
    public function test_stock_transfer_four_phase_workflow_execution()
    {
        $ctx = stkContext();
        stkSetStock($ctx['product'], $ctx['warehouse'], 120.0);
        $tujuan = stkWarehouse($ctx, 'Gudang Tujuan');
        $svc = app(StockTransferService::class);

        // A. BUAT MUTASI BARU (100 unit)
        $st = StockTransfer::create([
            'transfer_number' => 'ST-QA-' . uniqid(),
            'from_warehouse_id' => $ctx['warehouse']->id,
            'to_warehouse_id' => $tujuan->id,
            'transfer_date' => now()->toDateString(),
            'notes' => 'Uji Mutasi 4 Tahap QA Evidence',
            'status' => 'Draft',
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $st->id,
            'product_id' => $ctx['product']->id,
            'quantity' => 100.0,
            'from_warehouse_id' => $ctx['warehouse']->id,
            'to_warehouse_id' => $tujuan->id,
        ]);

        $this->assertEquals('Draft', $st->status);
        $this->assertEquals(100.0, $item->remainingToShip());

        // B. AJUKAN PERMINTAAN (REQUEST)
        $svc->requestTransfer($st->fresh());
        $this->assertEquals('Request', $st->fresh()->status);

        // C. SETUJUI MUTASI (APPROVE) -> Stok fisik asal TETAP 120, stok dicadangkan bertambah 100, stok bebas 20
        $svc->approveStockTransfer($st->fresh());
        $this->assertEquals('Approved', $st->fresh()->status);

        $asal = stkStock($ctx['product'], $ctx['warehouse']);
        $this->assertEquals(120.0, $asal['available'], 'Stok fisik asal harus tetap 120 saat disetujui');
        $this->assertEquals(100.0, $asal['reserved'], 'Stok dicadangkan asal harus 100 saat disetujui');
        $this->assertEquals(20.0, $asal['free'], 'Stok bebas asal harus berkurang menjadi 20');
        $this->assertEquals(0.0, stkStock($ctx['product'], $tujuan)['available'], 'Stok tujuan belum bertambah');

        // D. KIRIM SEBAGIAN OLEH GUDANG ASAL (Mobil hanya muat 70 dari 100 unit)
        $shipment = $svc->shipTransfer($st->fresh(), [$item->id => 70.0], 'Mobil penuh tahap 1: 70 unit');

        $this->assertEquals('Shipped', $st->fresh()->status);
        $this->assertEquals('Dikirim Sebagian', $st->fresh()->statusLabel());
        $this->assertEquals(30.0, $item->fresh()->remainingToShip(), 'Sisa belum dikirim harus 30 unit');

        // Verifikasi fisik asal turun 70 jadi 50, reserved sisa 30
        $asalAfterShip = stkStock($ctx['product'], $ctx['warehouse']);
        $this->assertEquals(50.0, $asalAfterShip['available'], 'Stok fisik asal harus turun menjadi 50');
        $this->assertEquals(30.0, $asalAfterShip['reserved'], 'Stok reserved asal harus sisa 30');

        // Verifikasi barang ada di gudang transit (70 unit) dan bukan stok jual
        $transit = $svc->transitWarehouse($tujuan->cabang_id);
        $this->assertEquals(70.0, stkStock($ctx['product'], $transit)['available'], 'Barang di gudang transit harus 70 unit');
        $this->assertNotContains($transit->id, app(StockAvailability::class)->accessibleWarehouseIds(null), 'Transit bukan stok jual');

        // E. TERIMA OLEH GUDANG TUJUAN (Receive Shipment 70 unit)
        $svc->receiveShipment($shipment);

        // Verifikasi transit kembali 0, dan gudang tujuan bertambah 70
        $this->assertEquals(0.0, stkStock($ctx['product'], $transit)['available'], 'Stok di transit harus 0 setelah diterima');
        $this->assertEquals(70.0, stkStock($ctx['product'], $tujuan)['available'], 'Stok gudang tujuan harus bertambah 70 unit');

        // Status transfer tetap Shipped karena masih ada sisa 30 unit dicadangkan
        $this->assertEquals('Shipped', $st->fresh()->status);
        $this->assertEquals(30.0, $item->fresh()->remainingToShip());

        // F. TUTUP SISA (Sisa 30 batal dikirim) -> Cadangan 30 dilepas, mutasi menjadi Selesai (Completed)
        $svc->closeRemaining($st->fresh(), 'Mobil kedua batal jalan, sisa ditutup');
        $this->assertEquals('Completed', $st->fresh()->status);
        $this->assertEquals('Selesai (Diterima)', $st->fresh()->statusLabel());

        $asalFinal = stkStock($ctx['product'], $ctx['warehouse']);
        $this->assertEquals(50.0, $asalFinal['available'], 'Stok fisik asal tetap 50');
        $this->assertEquals(0.0, $asalFinal['reserved'], 'Cadangan asal harus dilepas jadi 0');
    }

    /**
     * Skenario 2: Cek PDF salah satu PO (Format Faktur Pajak, DPP Nilai Lain, PPN 12%)
     */
    public function test_purchase_order_pdf_tax_rendering_and_calculation()
    {
        $ctx = purContext();
        [$po, $poItem] = purOrder($ctx, 10, 20000);
        $poItem->forceFill(['tax' => 11, 'tipe_pajak' => 'Inklusif'])->save();

        $html = view('pdf.purchase-order', [
            'purchaseOrder' => $po->fresh(['supplier', 'cabang', 'purchaseOrderItem.product.uom', 'purchaseOrderCurrency.currency']),
            'isImport' => false,
        ])->render();

        $this->assertNotEmpty($html, 'HTML PDF PO tidak boleh kosong');
        $this->assertStringContainsString('PURCHASE ORDER', $html);
        $this->assertStringContainsString('Harga Jual (DPP)', $html);
        $this->assertStringContainsString('DPP Nilai Lain', $html);
        $this->assertStringContainsString('PPN 12% × DPP Nilai Lain', $html);
        $this->assertStringContainsString('GRAND TOTAL', $html);

        // Angka yang tertera harus tepat: 180.180, 165.165, 19.820, 200.000
        $text = preg_replace('/\s+/', ' ', strip_tags($html));
        $this->assertStringContainsString('180.180', $text);
        $this->assertStringContainsString('165.165', $text);
        $this->assertStringContainsString('19.820', $text);
        $this->assertStringContainsString('200.000', $text);

        // Pastikan tidak ada angka Rupiah dengan desimal berulang seperti ,1666666
        $this->assertDoesNotMatchRegularExpression('/,\d{3,}\s/', $html, 'Tampilan Rupiah maksimal 2 desimal');
    }
}
