<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDepreciationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $accumulatedCoa;
    protected ChartOfAccount $expenseCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
        ]);

        $this->user = User::factory()->create([
            'email' => 'finance.asset@dutastunggal.com',
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);

        // COA Setup
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
    }

    protected function createSampleAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'code' => 'AST-' . uniqid(),
            'name' => 'Mesin Produksi Sample',
            'cabang_id' => $this->cabang->id,
            'purchase_date' => Carbon::now()->subMonths(3)->toDateString(),
            'usage_date' => Carbon::now()->subMonths(3)->toDateString(),
            'purchase_cost' => 60000000,
            'salvage_value' => 6000000,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 10800000,
            'monthly_depreciation' => 900000,
            'accumulated_depreciation' => 0,
            'book_value' => 60000000,
            'status' => 'active',
        ], $overrides));
    }

    public function test_user_can_view_depreciation_index_page_and_kpis(): void
    {
        $asset = $this->createSampleAsset();

        // Buat 1 entri penyusutan
        AssetDepreciation::create([
            'asset_id' => $asset->id,
            'depreciation_date' => Carbon::now()->startOfMonth()->toDateString(),
            'period_month' => Carbon::now()->month,
            'period_year' => Carbon::now()->year,
            'amount' => 900000,
            'accumulated_total' => 900000,
            'book_value' => 59100000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.depreciation.index', [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.depreciation.index');
        $response->assertViewHas('depreciations');
        $response->assertSee('Pusat Depresiasi');
        $response->assertSee('Penyusutan Bulanan');
        $response->assertSee('Mesin Produksi Sample');
    }

    public function test_user_can_execute_batch_monthly_depreciation(): void
    {
        $asset1 = $this->createSampleAsset(['name' => 'Aset Uji Batch 1']);
        $asset2 = $this->createSampleAsset(['name' => 'Aset Uji Batch 2']);

        $depDate = Carbon::now()->startOfMonth()->addDays(10)->toDateString();

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.process-monthly'), [
                'depreciation_date' => $depDate,
                'cabang_id' => $this->cabang->id,
            ]);

        $response->assertRedirect(route('keuangan.asset.depreciation.index', [
            'period_month' => Carbon::parse($depDate)->month,
            'period_year' => Carbon::parse($depDate)->year,
        ]));
        $response->assertSessionHas('success');

        // Pastikan entri depresiasi terbentuk untuk kedua aset
        $this->assertEquals(2, AssetDepreciation::where('status', 'recorded')->count());

        // Pastikan jurnal buku besar terbit untuk kedua aset (2 baris jurnal per depresiasi: Dr & Cr)
        $journals = JournalEntry::where('source_type', AssetDepreciation::class)->get();
        $this->assertCount(4, $journals);

        // Pastikan nilai akumulasi pada aset telah diperbarui
        $this->assertEquals(900000, (float) $asset1->fresh()->accumulated_depreciation);
        $this->assertEquals(59100000, (float) $asset1->fresh()->book_value);
        $this->assertEquals(900000, (float) $asset2->fresh()->accumulated_depreciation);
        $this->assertEquals(59100000, (float) $asset2->fresh()->book_value);
    }

    public function test_user_can_execute_single_asset_depreciation(): void
    {
        $asset = $this->createSampleAsset(['name' => 'Aset Single Run']);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.process-single', $asset), [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(1, AssetDepreciation::where('asset_id', $asset->id)->count());
        $dep = AssetDepreciation::where('asset_id', $asset->id)->first();
        $this->assertEquals('recorded', $dep->status);
        $this->assertEquals(900000, (float) $dep->amount);

        // Verifikasi entri jurnal
        $journals = JournalEntry::where('source_type', AssetDepreciation::class)
            ->where('source_id', $dep->id)
            ->get();
        $this->assertCount(2, $journals);

        $debitJournal = $journals->firstWhere('debit', '>', 0);
        $creditJournal = $journals->firstWhere('credit', '>', 0);

        $this->assertEquals($this->expenseCoa->id, $debitJournal->coa_id);
        $this->assertEquals(900000, (float) $debitJournal->debit);

        $this->assertEquals($this->accumulatedCoa->id, $creditJournal->coa_id);
        $this->assertEquals(900000, (float) $creditJournal->credit);
    }

    public function test_depreciation_prevents_duplicate_entry_in_same_month(): void
    {
        $asset = $this->createSampleAsset(['name' => 'Aset Anti Duplikat']);

        // Eksekusi pertama
        $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.process-single', $asset), [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]);

        $this->assertEquals(1, AssetDepreciation::where('asset_id', $asset->id)->count());

        // Eksekusi kedua pada bulan yang sama
        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.process-single', $asset), [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(1, AssetDepreciation::where('asset_id', $asset->id)->count());
    }

    public function test_user_can_reverse_depreciation_and_delete_journal(): void
    {
        $asset = $this->createSampleAsset(['name' => 'Aset Reversal']);

        // Eksekusi depresiasi
        $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.process-single', $asset), [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]);

        $dep = AssetDepreciation::where('asset_id', $asset->id)->first();
        $this->assertEquals('recorded', $dep->status);
        $this->assertEquals(900000, (float) $asset->fresh()->accumulated_depreciation);

        // Eksekusi Reversal
        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.depreciation.reverse', $dep));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan status menjadi reversed
        $this->assertEquals('reversed', $dep->fresh()->status);

        // Pastikan jurnal dibersihkan dari buku besar
        $journals = JournalEntry::where('source_type', AssetDepreciation::class)
            ->where('source_id', $dep->id)
            ->get();
        $this->assertCount(0, $journals);

        // Pastikan akumulasi dan nilai buku aset pulih
        $asset->refresh();
        $this->assertEquals(0, (float) $asset->accumulated_depreciation);
        $this->assertEquals(60000000, (float) $asset->book_value);
    }

    public function test_user_can_stream_depreciation_pdf(): void
    {
        $asset = $this->createSampleAsset(['name' => 'Aset Cetak PDF']);

        AssetDepreciation::create([
            'asset_id' => $asset->id,
            'depreciation_date' => Carbon::now()->toDateString(),
            'period_month' => Carbon::now()->month,
            'period_year' => Carbon::now()->year,
            'amount' => 900000,
            'accumulated_total' => 900000,
            'book_value' => 59100000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.depreciation.pdf', [
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_asset_show_page_displays_depreciation_history_and_reversal_button(): void
    {
        $asset = $this->createSampleAsset(['name' => 'Aset Riwayat Show']);

        $dep = AssetDepreciation::create([
            'asset_id' => $asset->id,
            'depreciation_date' => Carbon::now()->toDateString(),
            'period_month' => Carbon::now()->month,
            'period_year' => Carbon::now()->year,
            'amount' => 900000,
            'accumulated_total' => 900000,
            'book_value' => 59100000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.show', $asset));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Penyusutan Bulanan');
        $response->assertSee('Tercatat');
        $response->assertSee(route('keuangan.asset.depreciation.reverse', $dep->id));
        $response->assertSee('Batalkan (Reversal)');
    }
}
