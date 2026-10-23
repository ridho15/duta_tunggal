<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDisposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $accumulatedCoa;
    protected ChartOfAccount $depreciationExpenseCoa;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $gainCoa;
    protected ChartOfAccount $lossCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create([
            'kode' => 'CB01',
            'nama' => 'Cabang Pusat',
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin.disposal@dutastunggal.com',
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);

        // COA Setup
        $this->assetCoa = ChartOfAccount::factory()->create([
            'code' => '1210.01',
            'name' => 'Mesin Pabrik',
            'type' => 'Asset',
        ]);

        $this->accumulatedCoa = ChartOfAccount::factory()->create([
            'code' => '1220.01',
            'name' => 'Akumulasi Penyusutan Mesin',
            'type' => 'Asset',
        ]);

        $this->depreciationExpenseCoa = ChartOfAccount::factory()->create([
            'code' => '6311',
            'name' => 'Beban Penyusutan Mesin',
            'type' => 'Expense',
        ]);

        $this->cashCoa = ChartOfAccount::factory()->create([
            'code' => '1101',
            'name' => 'Kas Besar',
            'type' => 'Asset',
        ]);

        $this->gainCoa = ChartOfAccount::factory()->create([
            'code' => '4190',
            'name' => 'Keuntungan Pelepasan Aset',
            'type' => 'Revenue',
        ]);

        $this->lossCoa = ChartOfAccount::factory()->create([
            'code' => '5290',
            'name' => 'Kerugian Pelepasan Aset',
            'type' => 'Expense',
        ]);
    }

    protected function createAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'code' => 'AST-DSP-' . uniqid(),
            'name' => 'Mesin Press Otomatis',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => Carbon::now()->subYears(3)->toDateString(),
            'usage_date' => Carbon::now()->subYears(3)->toDateString(),
            'purchase_cost' => 100000000,
            'salvage_value' => 10000000,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
            'annual_depreciation' => 18000000,
            'monthly_depreciation' => 1500000,
            'accumulated_depreciation' => 60000000,
            'book_value' => 40000000,
            'status' => 'active',
        ], $overrides));
    }

    public function test_user_can_view_disposal_index_page_and_kpis(): void
    {
        $asset = $this->createAsset();

        AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => Carbon::now()->toDateString(),
            'disposal_type' => 'sale',
            'sale_price' => 50000000,
            'book_value_at_disposal' => 40000000,
            'gain_loss_amount' => 10000000,
            'notes' => 'Dijual lelang',
            'approved_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.disposal.index'));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.disposal.index');
        $response->assertViewHas('disposals');
        $response->assertSee('Pelepasan');
        $response->assertSee('Disposal Aset');
        $response->assertSee('Mesin Press Otomatis');
    }

    public function test_user_can_view_create_disposal_page(): void
    {
        $asset = $this->createAsset();

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.disposal.create', ['asset_id' => $asset->id]));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.disposal.create');
        $response->assertSee($asset->name);
        $response->assertSee('Pencatatan Pelepasan');
    }

    public function test_user_can_store_disposal_sale_with_gain_and_post_balanced_journals(): void
    {
        $asset = $this->createAsset([
            'purchase_cost' => 100000000,
            'accumulated_depreciation' => 60000000,
            'book_value' => 40000000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.disposal.store'), [
                'asset_id' => $asset->id,
                'disposal_date' => Carbon::now()->toDateString(),
                'disposal_type' => 'sale',
                'sale_price' => '55.000.000', // Uji format string rupiah
                'notes' => 'Penjualan mesin di atas nilai buku',
            ]);

        $disposal = AssetDisposal::where('asset_id', $asset->id)->first();
        $this->assertNotNull($disposal);
        $this->assertEquals('completed', $disposal->status);
        $this->assertEquals(55000000, (float) $disposal->sale_price);
        $this->assertEquals(40000000, (float) $disposal->book_value_at_disposal);
        $this->assertEquals(15000000, (float) $disposal->gain_loss_amount);

        // Status aset berubah menjadi 'disposed'
        $this->assertEquals('disposed', $asset->fresh()->status);

        // Verifikasi entri jurnal akuntansi seimbang (Debit = Kredit)
        $journals = JournalEntry::where('source_type', AssetDisposal::class)
            ->where('source_id', $disposal->id)
            ->get();

        $this->assertCount(4, $journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');

        // Dr Kas (55M) + Dr Akumulasi (60M) = 115M
        // Cr Aset Tetap (100M) + Cr Laba Pelepasan (15M) = 115M
        $this->assertEquals(115000000, $totalDebit);
        $this->assertEquals(115000000, $totalCredit);

        $response->assertRedirect(route('keuangan.asset.disposal.show', $disposal));
        $response->assertSessionHas('success');
    }

    public function test_user_can_store_disposal_scrap_with_loss_and_post_balanced_journals(): void
    {
        $asset = $this->createAsset([
            'purchase_cost' => 50000000,
            'accumulated_depreciation' => 30000000,
            'book_value' => 20000000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.disposal.store'), [
                'asset_id' => $asset->id,
                'disposal_date' => Carbon::now()->toDateString(),
                'disposal_type' => 'scrap',
                'notes' => 'Penghapusan aset karena rusak total terbakar',
            ]);

        $disposal = AssetDisposal::where('asset_id', $asset->id)->first();
        $this->assertNotNull($disposal);
        $this->assertEquals('completed', $disposal->status);
        $this->assertEquals(-20000000, (float) $disposal->gain_loss_amount);

        // Verifikasi jurnal seimbang
        $journals = JournalEntry::where('source_type', AssetDisposal::class)
            ->where('source_id', $disposal->id)
            ->get();

        $this->assertCount(3, $journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');

        // Dr Akumulasi (30M) + Dr Kerugian (20M) = 50M
        // Cr Aset Tetap (50M) = 50M
        $this->assertEquals(50000000, $totalDebit);
        $this->assertEquals(50000000, $totalCredit);
        $this->assertEquals('disposed', $asset->fresh()->status);
    }

    public function test_cannot_dispose_already_disposed_asset(): void
    {
        $asset = $this->createAsset();

        // Disposal pertama
        $this->actingAs($this->user)
            ->post(route('keuangan.asset.disposal.store'), [
                'asset_id' => $asset->id,
                'disposal_date' => Carbon::now()->toDateString(),
                'disposal_type' => 'scrap',
            ]);

        $this->assertEquals(1, AssetDisposal::where('asset_id', $asset->id)->count());

        // Percobaan disposal kedua pada aset yang sama
        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.disposal.store'), [
                'asset_id' => $asset->id,
                'disposal_date' => Carbon::now()->toDateString(),
                'disposal_type' => 'scrap',
            ]);

        $response->assertSessionHasErrors(['asset_id']);
        $this->assertEquals(1, AssetDisposal::where('asset_id', $asset->id)->count());
    }

    public function test_user_can_view_disposal_show_page_and_journals(): void
    {
        $asset = $this->createAsset();

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => Carbon::now()->toDateString(),
            'disposal_type' => 'scrap',
            'book_value_at_disposal' => 40000000,
            'gain_loss_amount' => -40000000,
            'notes' => 'Uji tampil rincian disposal',
            'approved_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.disposal.show');
        $response->assertSee('Disposed');
        $response->assertSee('Log Entri Jurnal Akuntansi Pelepasan Aset');
    }

    public function test_user_can_stream_disposal_pdf(): void
    {
        $asset = $this->createAsset();

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => Carbon::now()->toDateString(),
            'disposal_type' => 'scrap',
            'book_value_at_disposal' => 40000000,
            'gain_loss_amount' => -40000000,
            'approved_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.disposal.pdf', $disposal));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }
}
