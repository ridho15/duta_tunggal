<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $accumulatedCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $apCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin.asset@dutastunggal.com',
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);

        // Setup COA
        $this->assetCoa = ChartOfAccount::factory()->create([
            'code' => '1210.01',
            'name' => 'Peralatan Kantor (OE)',
            'type' => 'Asset',
        ]);

        $this->accumulatedCoa = ChartOfAccount::factory()->create([
            'code' => '1220.01',
            'name' => 'Akumulasi Penyusutan Peralatan',
            'type' => 'Asset',
        ]);

        $this->expenseCoa = ChartOfAccount::factory()->create([
            'code' => '6311',
            'name' => 'Beban Penyusutan Peralatan',
            'type' => 'Expense',
        ]);

        $this->apCoa = ChartOfAccount::factory()->create([
            'code' => '2110.01',
            'name' => 'Hutang Usaha (AP)',
            'type' => 'Liability',
        ]);
    }

    public function test_user_can_view_asset_index_page_and_kpis(): void
    {
        // Buat 2 aset sampel
        Asset::create([
            'code' => 'AST-0001',
            'name' => 'Laptop ThinkPad E14',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 15000000,
            'salvage_value' => 750000,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 3562500,
            'monthly_depreciation' => 296875,
            'accumulated_depreciation' => 0,
            'book_value' => 15000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.index'));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.index');
        $response->assertViewHas('assets');
        $response->assertViewHas('kpis');
        $response->assertSee('AST-0001');
        $response->assertSee('Laptop ThinkPad E14');
        $response->assertSee('Nilai Perolehan');
    }

    public function test_user_can_view_asset_create_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.create'));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.create');
        $response->assertSee('Registrasi Aset Tetap Baru');
        $response->assertSee('Metode Penyusutan');
    }

    public function test_user_can_store_new_asset_and_auto_post_acquisition_journal(): void
    {
        $postData = [
            'code' => 'AST-9999',
            'name' => 'Genset Cummins 50 kVA',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => '2026-04-01',
            'usage_date' => '2026-04-01',
            'purchase_cost' => '50.000.000,00', // Uji parsing string rupiah
            'salvage_value' => '2.500.000,00',
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'credit_coa_id' => $this->apCoa->id,
            'notes' => 'Genset cadangan pabrik cabang utama',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.store'), $postData);

        $asset = Asset::where('code', 'AST-9999')->first();
        $this->assertNotNull($asset, 'Aset baru harus tersimpan di database.');
        $response->assertRedirect(route('keuangan.asset.show', $asset));

        $this->assertEquals(50000000, (float) $asset->purchase_cost);
        $this->assertEquals(2500000, (float) $asset->salvage_value);
        $this->assertEquals(50000000, (float) $asset->book_value);
        $this->assertEquals(9500000, (float) $asset->annual_depreciation); // (50jt - 2.5jt)/5 = 9.5jt/thn
        $this->assertEquals(791666.67, round((float) $asset->monthly_depreciation, 2));

        // Verifikasi Jurnal Akuisisi Otomatis
        $journals = JournalEntry::where('source_type', 'App\Models\Asset')
            ->where('source_id', $asset->id)
            ->where('journal_type', 'asset_acquisition')
            ->get();

        $this->assertCount(2, $journals, 'Harus terbentuk 2 baris jurnal akuisisi (Debit & Kredit seimbang).');

        $debitEntry = $journals->where('coa_id', $this->assetCoa->id)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals(50000000, (float) $debitEntry->debit);
        $this->assertEquals(0, (float) $debitEntry->credit);

        $creditEntry = $journals->where('coa_id', $this->apCoa->id)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals(0, (float) $creditEntry->debit);
        $this->assertEquals(50000000, (float) $creditEntry->credit);
    }

    public function test_user_can_view_asset_show_detail_page(): void
    {
        $asset = Asset::create([
            'code' => 'AST-0002',
            'name' => 'Printer Barcode Zebra',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 5000000,
            'salvage_value' => 250000,
            'useful_life_years' => 3,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 1583333.33,
            'monthly_depreciation' => 131944.44,
            'accumulated_depreciation' => 0,
            'book_value' => 5000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.show', $asset));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.show');
        $response->assertSee('AST-0002');
        $response->assertSee('Printer Barcode Zebra');
        $response->assertSee('Siklus Hidup dan Parameter Depresiasi');
    }

    public function test_user_can_update_asset(): void
    {
        $asset = Asset::create([
            'code' => 'AST-0003',
            'name' => 'Meja Rapat Kayu',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 4000000,
            'salvage_value' => 200000,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 950000,
            'monthly_depreciation' => 79166.67,
            'accumulated_depreciation' => 0,
            'book_value' => 4000000,
            'status' => 'active',
        ]);

        $updateData = [
            'code' => 'AST-0003',
            'name' => 'Meja Rapat Kayu Jati Premium',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 4500000,
            'salvage_value' => 225000,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'status' => 'active',
            'notes' => 'Diperbarui dengan finishing pernis baru',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('keuangan.asset.update', $asset), $updateData);

        $response->assertRedirect(route('keuangan.asset.show', $asset));
        $asset->refresh();

        $this->assertEquals('Meja Rapat Kayu Jati Premium', $asset->name);
        $this->assertEquals(4500000, (float) $asset->purchase_cost);
    }

    public function test_user_can_stream_asset_pdf(): void
    {
        $asset = Asset::create([
            'code' => 'AST-0004',
            'name' => 'Server Rack Dell PowerEdge',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 30000000,
            'salvage_value' => 1500000,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 5700000,
            'monthly_depreciation' => 475000,
            'accumulated_depreciation' => 0,
            'book_value' => 30000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.pdf', $asset));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'application/pdf'));
    }

    public function test_user_can_calculate_depreciation_action(): void
    {
        $asset = Asset::create([
            'code' => 'AST-0005',
            'name' => 'AC Split Daikin 2 PK',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => now()->toDateString(),
            'usage_date' => now()->toDateString(),
            'purchase_cost' => 8000000,
            'salvage_value' => 400000,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 0,
            'monthly_depreciation' => 0,
            'accumulated_depreciation' => 0,
            'book_value' => 8000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.calculate-depreciation', $asset));

        $response->assertRedirect();
        $asset->refresh();

        $this->assertEquals(1900000, (float) $asset->annual_depreciation); // (8jt - 400rb) / 4 = 1.9jt
        $this->assertEquals(158333.33, round((float) $asset->monthly_depreciation, 2));
    }
}
