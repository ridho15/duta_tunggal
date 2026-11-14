<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAuditStage5Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;
    protected Cabang $cabangB;
    protected User $superAdmin;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $accumDepCoa;
    protected ChartOfAccount $depExpenseCoa;
    protected ChartOfAccount $kasCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::create([
            'nama' => 'Cabang Utama Jakarta',
            'kode' => 'CU-JKT',
            'alamat' => 'Jl. Thamrin No. 10 Jakarta',
            'status' => 1,
        ]);

        $this->cabangB = Cabang::create([
            'nama' => 'Cabang Gudang Surabaya',
            'kode' => 'CG-SBY',
            'alamat' => 'Jl. Rungkut Industri No. 5 Surabaya',
            'status' => 1,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator Master & Aset',
            'cabang_id' => $this->cabangA->id,
            'manage_type' => ['all'],
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->assetCoa = ChartOfAccount::create([
            'code' => '1210-001',
            'name' => 'Aset Tetap Peralatan Kantor',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->accumDepCoa = ChartOfAccount::create([
            'code' => '1219-001',
            'name' => 'Akumulasi Penyusutan Peralatan',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->depExpenseCoa = ChartOfAccount::create([
            'code' => '5210-001',
            'name' => 'Beban Penyusutan Peralatan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->kasCoa = ChartOfAccount::create([
            'code' => '1111-001',
            'name' => 'Kas Operasional Kantor',
            'type' => 'Asset',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_void_asset_disposal_and_restore_asset_and_clean_gl(): void
    {
        $asset = Asset::create([
            'name' => 'Laptop Dell XPS 15 Auditor',
            'code' => 'AST-TEST-001',
            'cabang_id' => $this->cabangA->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumDepCoa->id,
            'depreciation_expense_coa_id' => $this->depExpenseCoa->id,
            'purchase_cost' => 20000000,
            'accumulated_depreciation' => 5000000,
            'book_value' => 15000000,
            'salvage_value' => 1000000,
            'useful_life_years' => 4,
            'purchase_date' => now()->subYears(1)->toDateString(),
            'usage_date' => now()->subYears(1)->toDateString(),
            'status' => 'disposed',
            'depreciation_method' => 'straight_line',
        ]);

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => now()->toDateString(),
            'disposal_type' => 'sale',
            'sale_price' => 16000000,
            'book_value_at_disposal' => 15000000,
            'gain_loss_amount' => 1000000,
            'notes' => 'Penjualan laptop ex-auditor',
            'status' => 'completed',
            'approved_by' => $this->superAdmin->id,
            'approved_at' => now(),
        ]);

        JournalEntry::create([
            'coa_id' => $this->assetCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'DISP-' . $disposal->id,
            'description' => 'Disposal credit asset cost',
            'debit' => 0,
            'credit' => 20000000,
            'journal_type' => 'asset_disposal',
            'source_type' => AssetDisposal::class,
            'source_id' => $disposal->id,
            'cabang_id' => $this->cabangA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('keuangan.asset.disposal.void', $disposal->id), [
                'reason' => 'Salah pilih unit laptop yang dijual, transaksi dibatalkan',
            ]);

        $response->assertRedirect(route('keuangan.asset.disposal.show', $disposal->id));
        $response->assertSessionHas('success');

        $disposal->refresh();
        $this->assertTrue($disposal->isCancelled());
        $this->assertEquals('Salah pilih unit laptop yang dijual, transaksi dibatalkan', $disposal->void_reason);
        $this->assertEquals($this->superAdmin->id, $disposal->voided_by);

        $asset->refresh();
        $this->assertEquals('active', $asset->status);

        // Jurnal disposal terhapus bersih dari GL
        $this->assertEquals(0, JournalEntry::where('source_type', AssetDisposal::class)->where('source_id', $disposal->id)->count());
    }

    public function test_void_asset_disposal_requires_at_least_5_chars_reason(): void
    {
        $asset = Asset::create([
            'name' => 'Meja Rapat Kayu Jati',
            'code' => 'AST-TEST-002',
            'cabang_id' => $this->cabangA->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumDepCoa->id,
            'depreciation_expense_coa_id' => $this->depExpenseCoa->id,
            'purchase_cost' => 5000000,
            'accumulated_depreciation' => 1000000,
            'book_value' => 4000000,
            'salvage_value' => 500000,
            'useful_life_years' => 5,
            'purchase_date' => now()->subYears(1)->toDateString(),
            'usage_date' => now()->subYears(1)->toDateString(),
            'status' => 'disposed',
            'depreciation_method' => 'straight_line',
        ]);

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => now()->toDateString(),
            'disposal_type' => 'scrap',
            'book_value_at_disposal' => 4000000,
            'gain_loss_amount' => -4000000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('keuangan.asset.disposal.void', $disposal->id), [
                'reason' => 'Tdk',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse($disposal->fresh()->isCancelled());
    }

    public function test_super_admin_can_void_completed_asset_transfer_and_revert_cabang(): void
    {
        $asset = Asset::create([
            'name' => 'Forklift Gudang Utama',
            'code' => 'AST-FORK-001',
            'cabang_id' => $this->cabangB->id, // Saat ini tercatat di cabang B setelah mutasi
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumDepCoa->id,
            'depreciation_expense_coa_id' => $this->depExpenseCoa->id,
            'purchase_cost' => 150000000,
            'accumulated_depreciation' => 10000000,
            'book_value' => 140000000,
            'salvage_value' => 20000000,
            'useful_life_years' => 8,
            'purchase_date' => now()->subYears(2)->toDateString(),
            'usage_date' => now()->subYears(2)->toDateString(),
            'status' => 'active',
            'depreciation_method' => 'straight_line',
        ]);

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => now()->toDateString(),
            'reason' => 'Mutasi forklift penunjang bongkar muat Surabaya',
            'requested_by' => $this->superAdmin->id,
            'approved_by' => $this->superAdmin->id,
            'approved_at' => now(),
            'status' => 'completed',
            'completed_by' => $this->superAdmin->id,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('keuangan.asset.transfer.void', $transfer->id), [
                'reason' => 'Truk pengangkut forklift mogok di tol dan pengiriman dipulangkan ke Jakarta',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $transfer->refresh();
        $this->assertTrue($transfer->isCancelled());
        $this->assertEquals($this->superAdmin->id, $transfer->voided_by);

        // Kepemilikan cabang aset berhasil dikembalikan ke cabang A (from_cabang_id)
        $asset->refresh();
        $this->assertEquals($this->cabangA->id, $asset->cabang_id);
    }

    public function test_deleting_asset_with_acquisition_journal_cleans_gl(): void
    {
        $asset = Asset::create([
            'name' => 'Printer Barcode Zebra Batal Pakai',
            'code' => 'AST-PRN-001',
            'cabang_id' => $this->cabangA->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumDepCoa->id,
            'depreciation_expense_coa_id' => $this->depExpenseCoa->id,
            'purchase_cost' => 6500000,
            'accumulated_depreciation' => 0,
            'book_value' => 6500000,
            'salvage_value' => 500000,
            'useful_life_years' => 3,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'status' => 'active',
            'depreciation_method' => 'straight_line',
        ]);

        JournalEntry::create([
            'coa_id' => $this->assetCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'AST-ACQ-' . $asset->id,
            'description' => 'Jurnal Perolehan Aset Tetap',
            'debit' => 6500000,
            'credit' => 0,
            'journal_type' => 'manual',
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'cabang_id' => $this->cabangA->id,
        ]);

        $this->assertEquals(1, JournalEntry::where('source_type', 'App\Models\Asset')->where('source_id', $asset->id)->count());

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('keuangan.asset.destroy', $asset->id));

        $response->assertRedirect(route('keuangan.asset.index'));
        $response->assertSessionHas('success');

        // Aset terhapus soft-delete
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);

        // Jurnal perolehan di GL ikut terhapus bersih
        $this->assertEquals(0, JournalEntry::where('source_type', 'App\Models\Asset')->where('source_id', $asset->id)->count());
    }

    public function test_super_admin_can_toggle_supplier_active_status(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-TEST-001',
            'perusahaan' => 'PT Mitra Baja Mandiri',
            'address' => 'Kawasan Industri Pulogadung',
            'phone' => '0214600123',
            'is_active' => true,
            'cabang_id' => $this->cabangA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('master.suppliers.toggle-active', $supplier->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse((bool) $supplier->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->superAdmin)
            ->post(route('master.suppliers.toggle-active', $supplier->id));

        $this->assertTrue((bool) $supplier->fresh()->is_active);
    }

    public function test_super_admin_can_merge_duplicate_suppliers(): void
    {
        $survivor = Supplier::create([
            'code' => 'SUP-SURVIVOR-01',
            'perusahaan' => 'PT Tembaga Mas Prima (Pusat)',
            'address' => 'Jl. Industri Baja No. 1',
            'phone' => '0218888001',
            'is_active' => true,
            'cabang_id' => $this->cabangA->id,
        ]);

        $merged = Supplier::create([
            'code' => 'SUP-MERGED-02',
            'perusahaan' => 'PT Tembaga Mas Prima (Duplikat Salah Ketik)',
            'address' => 'Jl. Industri Baja No. 1 Jakarta',
            'phone' => '0218888002',
            'is_active' => true,
            'cabang_id' => $this->cabangA->id,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-MERGE-TEST-01',
            'supplier_id' => $merged->id,
            'cabang_id' => $this->cabangA->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 50000000,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('master.suppliers.merge', $merged->id), [
                'target_supplier_id' => $survivor->id,
                'reason' => 'Konsolidasi supplier ganda karena duplikasi registrasi',
            ]);

        $response->assertRedirect(route('master.suppliers.show', $survivor->id));
        $response->assertSessionHas('success');

        // PO otomatis dialihkan ke survivor
        $this->assertEquals($survivor->id, $po->fresh()->supplier_id);

        // Supplier duplikat di-soft delete dan status nonaktif
        $merged->refresh();
        $this->assertEquals($survivor->id, $merged->merged_into);
        $this->assertFalse((bool) $merged->is_active);
        $this->assertSoftDeleted('suppliers', ['id' => $merged->id]);
    }

    public function test_trial_balance_service_calculates_balance_and_difference(): void
    {
        $service = app(TrialBalanceService::class);
        $result = $service->generate([
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertArrayHasKey('is_balanced', $result);
        $this->assertArrayHasKey('difference', $result);
        $this->assertArrayHasKey('grand_totals', $result);
        $this->assertIsBool($result['is_balanced']);
        $this->assertIsFloat($result['difference']);
    }
}
