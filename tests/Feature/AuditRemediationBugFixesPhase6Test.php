<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AccountingPeriodService;
use App\Services\AssetDepreciationService;
use App\Services\CustomerReturnService;
use App\Services\PosShiftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditRemediationBugFixesPhase6Test extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $deprExpenseCoa;
    protected ChartOfAccount $accumDeprCoa;
    protected ChartOfAccount $retainedEarningsCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-PH6'],
            ['nama' => 'Cabang Test Phase 6', 'alamat' => 'Jl. Test Phase 6', 'telepon' => '021990011', 'status' => true]
        );

        $this->warehouse = Warehouse::firstOrCreate(
            ['kode' => 'WH-PH6'],
            [
                'name' => 'Gudang Phase 6',
                'cabang_id' => $this->cabang->id,
                'location' => 'Area Gudang 6',
                'telepon' => '021990022',
                'status' => 1,
            ]
        );

        $this->assetCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1300.99'],
            ['name' => 'Aset Tetap Uji', 'type' => 'Asset', 'level' => 3, 'status' => 'active']
        );

        $this->deprExpenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6100.99'],
            ['name' => 'Beban Penyusutan Uji', 'type' => 'Expense', 'level' => 3, 'status' => 'active']
        );

        $this->accumDeprCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1390.99'],
            ['name' => 'Akumulasi Penyusutan Uji', 'type' => 'Asset', 'level' => 3, 'status' => 'active']
        );

        $this->retainedEarningsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '3400'],
            ['name' => 'Laba Ditahan', 'type' => 'Equity', 'level' => 3, 'status' => 'active']
        );

        $superRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $this->user->assignRole($superRole);
    }

    /**
     * TEST BUG-NEW-06: Penyusutan aset bulan terakhir tidak abortus prematur,
     * nominal disesuaikan dengan sisa buku, dan status berpindah ke fully_depreciated.
     */
    public function test_bug_new_06_asset_depreciation_final_month_caps_and_transitions_status(): void
    {
        $asset = Asset::create([
            'code' => 'AST-TEST-FINAL',
            'name' => 'Mesin Uji Akhir Periode',
            'cabang_id' => $this->cabang->id,
            'asset_coa_id' => $this->assetCoa->id,
            'depreciation_expense_coa_id' => $this->deprExpenseCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumDeprCoa->id,
            'purchase_date' => now()->subMonths(12)->toDateString(),
            'usage_date' => now()->subMonths(12)->toDateString(),
            'purchase_cost' => 10000000,
            'salvage_value' => 1000000, // Basis susut: 9.000.000
            'useful_life_years' => 1,
            'depreciation_method' => 'straight_line',
            'annual_depreciation' => 9000000,
            'monthly_depreciation' => 900000,
            'accumulated_depreciation' => 8500000, // Sisa susut tinggal 500.000 (< monthly_depreciation 900.000)
            'book_value' => 1500000,
            'status' => 'active',
        ]);

        // Catat entri akumulasi penyusutan sebelumnya
        AssetDepreciation::create([
            'asset_id' => $asset->id,
            'depreciation_date' => now()->subMonth(),
            'period_month' => now()->subMonth()->month,
            'period_year' => now()->subMonth()->year,
            'amount' => 8500000,
            'accumulated_total' => 8500000,
            'book_value' => 1500000,
            'status' => 'recorded',
        ]);

        $service = app(AssetDepreciationService::class);
        $entry = $service->generateMonthlyDepreciation($asset, Carbon::now());

        $this->assertNotNull($entry);
        $this->assertEquals(500000, (float) $entry->amount, 'Nominal susut bulan terakhir harus di-cap sebesar sisa nilai yang dapat disusutkan');
        $this->assertEquals(9000000, (float) $entry->accumulated_total);
        $this->assertEquals(1000000, (float) $entry->book_value);

        $asset->refresh();
        $this->assertEquals('fully_depreciated', $asset->status, 'Status aset harus otomatis menjadi fully_depreciated');

        // Panggilan kedua pada bulan berikutnya harus melempar exception 'Aset sudah disusutkan penuh' dengan status fully_depreciated tetap terjaga
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Aset sudah disusutkan penuh');
        $service->generateMonthlyDepreciation($asset, Carbon::now()->addMonth());
    }

    /**
     * TEST BUG-NEW-07: Pembatalan SPK tidak menghapus record ke tong sampah,
     * dan memblokir pembatalan jika ada bahan baku yang sudah dikeluarkan dari gudang.
     */
    public function test_bug_new_07_manufacturing_order_cancel_safely_updates_status_and_blocks_active_materials(): void
    {
        $uom = UnitOfMeasure::firstOrCreate(['name' => 'PCS'], ['abbreviation' => 'pcs']);
        $category = ProductCategory::firstOrCreate(['kode' => 'CAT-PH6'], ['name' => 'Kategori Phase 6']);
        $product = Product::firstOrCreate(
            ['sku' => 'PRD-MO-PH6'],
            [
                'name' => 'Produk Hasil SPK',
                'product_category_id' => $category->id,
                'uom_id' => $uom->id,
                'sell_price' => 200000,
                'cost_price' => 100000,
                'status' => 'active',
                'kode_merk' => 'MRK-PH6',
            ]
        );

        $mo = ManufacturingOrder::create([
            'mo_number' => 'SPK-TEST-CANCEL-01',
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'planned_quantity' => 50,
            'status' => 'in_progress',
            'created_by' => $this->user->id,
        ]);

        // Simulasikan ada material issue yang sudah approved / completed
        MaterialIssue::create([
            'type' => 'issue',
            'issue_number' => 'MI-TEST-PH6',
            'manufacturing_order_id' => $mo->id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => MaterialIssue::STATUS_APPROVED,
            'total_cost' => 500000,
            'created_by' => $this->user->id,
        ]);

        // Coba batalkan SPK saat material issue aktif -> Harus DITOLAK
        $response = $this->actingAs($this->user)->post(route('manufaktur.spk.cancel', $mo));
        $response->assertSessionHas('error');
        $this->assertTrue(str_contains(session('error'), 'bahan baku sudah dikeluarkan'));

        // Ubah material issue ke rejected (retur selesai / dibatalkan)
        MaterialIssue::where('manufacturing_order_id', $mo->id)->update(['status' => MaterialIssue::STATUS_REJECTED]);

        // Sekarang batalkan SPK -> Harus BERHASIL tanpa menghapus baris SPK
        $cancelResponse = $this->actingAs($this->user)->post(route('manufaktur.spk.cancel', $mo));
        $cancelResponse->assertRedirect(route('manufaktur.spk.index'));
        $cancelResponse->assertSessionHas('success');

        $mo->refresh();
        $this->assertFalse($mo->trashed(), 'SPK tidak boleh dihapus ke tong sampah saat dibatalkan');
        $this->assertEquals('cancelled', $mo->status, 'Status SPK harus diubah menjadi cancelled');
    }

    /**
     * TEST BUG-NEW-08: Pembukaan shift kasir tidak dapat duplikat konkuren,
     * dan transaksi kasir memblokir request jika tidak ada shift aktif.
     */
    public function test_bug_new_08_pos_shift_opening_concurrency_and_order_requires_active_shift(): void
    {
        $shiftService = app(PosShiftService::class);

        // Buka shift pertama
        $shift = $shiftService->openShift($this->user, 200000, $this->warehouse->id, $this->cabang->id);
        $this->assertEquals('open', $shift->status);
        $this->assertEquals(200000, (float) $shift->starting_cash);

        // Percobaan buka shift kedua untuk user yang sama harus ditolak
        try {
            $shiftService->openShift($this->user, 100000, $this->warehouse->id, $this->cabang->id);
            $this->fail('Harus melempar ValidationException ketika kasir masih memiliki shift aktif');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starting_cash', $e->errors());
        }

        // Tutup shift kasir
        $shiftService->closeShift($shift, 200000);
        $this->assertEquals('closed', $shift->fresh()->status);

        // Coba simpan transaksi POS tanpa shift aktif -> Harus DITOLAK
        $uom = UnitOfMeasure::firstOrCreate(['name' => 'PCS'], ['abbreviation' => 'pcs']);
        $category = ProductCategory::firstOrCreate(['kode' => 'CAT-PH6'], ['name' => 'Kategori Phase 6']);
        $product = Product::firstOrCreate(
            ['sku' => 'PRD-POS-PH6'],
            [
                'name' => 'Barang POS Uji',
                'product_category_id' => $category->id,
                'uom_id' => $uom->id,
                'sell_price' => 50000,
                'cost_price' => 30000,
                'status' => 'active',
                'kode_merk' => 'MRK-PH6',
            ]
        );

        $orderPayload = [
            'payment_method' => 'cash',
            'payment_amount' => 50000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('kasir.pos.store'), $orderPayload);
        $response->assertSessionHasErrors(['shift']);
    }

    /**
     * TEST BUG-NEW-09: Customer return completion terkunci atomik
     * dan menolak eksekusi ganda.
     */
    public function test_bug_new_09_customer_return_completion_is_atomic_and_prevents_duplicate(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-PH6-TEST',
            'name' => 'Customer Retur Test Phase 6',
            'perusahaan' => 'PT Customer Test Phase 6',
            'cabang_id' => $this->cabang->id,
            'tipe_pembayaran' => 'Bebas',
            'address' => 'Jl. Retur Test Phase 6',
            'phone' => '021998877',
            'nik_npwp' => '1234567890123456',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-RET-PH6',
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'status' => Invoice::STATUS_PAID,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100000,
            'total' => 100000,
            'tipe_pembayaran' => 'Cash',
        ]);

        $retur = CustomerReturn::create([
            'return_number' => 'RET-TEST-PH6',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Uji coba locking retur',
            'status' => CustomerReturn::STATUS_APPROVED,
        ]);

        // Simulasikan status sudah selesai
        $retur->update([
            'status' => CustomerReturn::STATUS_COMPLETED,
            'stock_restored_at' => now(),
        ]);

        $service = app(CustomerReturnService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has already been processed');
        $service->processCompletion($retur);
    }

    /**
     * TEST BUG-NEW-10: Penutupan periode akuntansi bersifat idempoten,
     * membersihkan entri penutup lama saat ditutup ulang dan tidak menduplikasi closing entries.
     */
    public function test_bug_new_10_accounting_period_closing_is_idempotent_and_cleans_reopened_entries(): void
    {
        $revenueCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4100.99'],
            ['name' => 'Pendapatan Uji Phase 6', 'type' => 'Revenue', 'level' => 3, 'status' => 'active']
        );

        $period = AccountingPeriod::create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'cabang_id' => $this->cabang->id,
            'status' => 'open',
        ]);

        // Buat satu transaksi pendapatan
        JournalEntry::create([
            'coa_id' => $revenueCoa->id,
            'date' => '2026-01-15',
            'reference' => 'TEST-REV',
            'description' => 'Pendapatan Jual Test',
            'debit' => 0,
            'credit' => 1000000,
            'journal_type' => 'sales',
            'cabang_id' => $this->cabang->id,
        ]);

        $periodService = app(AccountingPeriodService::class);

        // Tutup periode (generate closing entries)
        $period = $periodService->closePeriod($period, $this->user->id, true);
        $this->assertEquals('closed', $period->status);

        $closingEntriesCount1 = JournalEntry::where('source_type', AccountingPeriod::class)
            ->where('source_id', $period->id)
            ->where('journal_type', 'closing')
            ->count();
        $this->assertGreaterThan(0, $closingEntriesCount1);

        // Buka kembali periode (reopen)
        $period = $periodService->reopenPeriod($period);
        $this->assertEquals('open', $period->status);

        // Entri penutup harus sudah dibersihkan setelah reopen
        $closingEntriesAfterReopen = JournalEntry::where('source_type', AccountingPeriod::class)
            ->where('source_id', $period->id)
            ->where('journal_type', 'closing')
            ->count();
        $this->assertEquals(0, $closingEntriesAfterReopen, 'Entri penutup lama harus dibersihkan saat periode dibuka kembali');

        // Tutup ulang periode
        $period = $periodService->closePeriod($period, $this->user->id, true);
        $this->assertEquals('closed', $period->status);

        $closingEntriesCount2 = JournalEntry::where('source_type', AccountingPeriod::class)
            ->where('source_id', $period->id)
            ->where('journal_type', 'closing')
            ->count();
        $this->assertEquals($closingEntriesCount1, $closingEntriesCount2, 'Jumlah entri penutup tidak boleh berlipat ganda saat ditutup ulang');
    }
}
