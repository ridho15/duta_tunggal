<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabangA;
    protected Cabang $cabangB;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $accumulatedCoa;
    protected ChartOfAccount $expenseCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::factory()->create([
            'kode' => 'CBA',
            'nama' => 'Cabang Asal A',
            'status' => 1,
        ]);

        $this->cabangB = Cabang::factory()->create([
            'kode' => 'CBB',
            'nama' => 'Cabang Tujuan B',
            'status' => 1,
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin.transfer@dutastunggal.com',
            'cabang_id' => $this->cabangA->id,
            'manage_type' => ['all'],
        ]);

        $this->assetCoa = ChartOfAccount::factory()->create([
            'code' => '1210.01',
            'name' => 'Peralatan Kantor',
            'type' => 'Asset',
        ]);

        $this->accumulatedCoa = ChartOfAccount::factory()->create([
            'code' => '1220.01',
            'name' => 'Akumulasi Penyusutan',
            'type' => 'Asset',
        ]);

        $this->expenseCoa = ChartOfAccount::factory()->create([
            'code' => '6311',
            'name' => 'Beban Penyusutan',
            'type' => 'Expense',
        ]);
    }

    protected function createAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'code' => 'AST-TRF-' . uniqid(),
            'name' => 'Laptop ThinkPad X1',
            'cabang_id' => $this->cabangA->id,
            'purchase_date' => Carbon::now()->subMonths(6)->toDateString(),
            'usage_date' => Carbon::now()->subMonths(6)->toDateString(),
            'purchase_cost' => 20000000,
            'salvage_value' => 2000000,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedCoa->id,
            'depreciation_expense_coa_id' => $this->expenseCoa->id,
            'annual_depreciation' => 4500000,
            'monthly_depreciation' => 375000,
            'accumulated_depreciation' => 0,
            'book_value' => 20000000,
            'status' => 'active',
        ], $overrides));
    }

    public function test_user_can_view_transfer_index_page_and_kpis(): void
    {
        $asset = $this->createAsset();

        AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => Carbon::now()->toDateString(),
            'reason' => 'Pemindahan aset untuk cabang baru',
            'requested_by' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.transfer.index'));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.transfer.index');
        $response->assertViewHas('transfers');
        $response->assertViewHas('kpis');
        $response->assertSee('Mutasi');
        $response->assertSee('Laptop ThinkPad X1');
        $response->assertSee('Menunggu Persetujuan');
    }

    public function test_user_can_view_create_transfer_page(): void
    {
        $asset = $this->createAsset();

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.transfer.create', ['asset_id' => $asset->id]));

        $response->assertStatus(200);
        $response->assertViewIs('keuangan.asset.transfer.create');
        $response->assertSee($asset->name);
        $response->assertSee($this->cabangB->nama);
    }

    public function test_user_can_store_transfer_request(): void
    {
        $asset = $this->createAsset();

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.transfer.store'), [
                'asset_id' => $asset->id,
                'to_cabang_id' => $this->cabangB->id,
                'transfer_date' => Carbon::now()->toDateString(),
                'reason' => 'Kebutuhan mendesak inventaris di Cabang B',
            ]);

        $transfer = AssetTransfer::where('asset_id', $asset->id)->first();
        $this->assertNotNull($transfer);
        $this->assertEquals('pending', $transfer->status);
        $this->assertEquals($this->cabangA->id, $transfer->from_cabang_id);
        $this->assertEquals($this->cabangB->id, $transfer->to_cabang_id);

        $response->assertRedirect(route('keuangan.asset.transfer.show', $transfer));
        $response->assertSessionHas('success');
    }

    public function test_user_cannot_transfer_to_same_cabang(): void
    {
        $asset = $this->createAsset();

        // Coba transfer ke cabang yang sama (cabangA)
        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.transfer.store'), [
                'asset_id' => $asset->id,
                'to_cabang_id' => $this->cabangA->id,
                'transfer_date' => Carbon::now()->toDateString(),
                'reason' => 'Transfer iseng ke cabang yang sama',
            ]);

        $response->assertSessionHasErrors(['to_cabang_id']);
        $this->assertEquals(0, AssetTransfer::count());
    }

    public function test_user_can_approve_transfer_request(): void
    {
        $asset = $this->createAsset();

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => Carbon::now()->toDateString(),
            'reason' => 'Mutasi disetujui manajemen',
            'requested_by' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.transfer.approve', $transfer));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('approved', $transfer->fresh()->status);
        $this->assertEquals($this->user->id, $transfer->fresh()->approved_by);
        $this->assertNotNull($transfer->fresh()->approved_at);
    }

    public function test_user_can_complete_transfer_and_updates_asset_cabang(): void
    {
        $asset = $this->createAsset();
        $this->assertEquals($this->cabangA->id, $asset->cabang_id);

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => Carbon::now()->toDateString(),
            'reason' => 'Mutasi dalam perjalanan',
            'requested_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'approved_at' => Carbon::now(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.transfer.complete', $transfer));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertEquals($this->user->id, $transfer->fresh()->completed_by);
        $this->assertNotNull($transfer->fresh()->completed_at);

        // Lokasi cabang aset resmi berpindah ke cabang B
        $this->assertEquals($this->cabangB->id, $asset->fresh()->cabang_id);
    }

    public function test_user_can_cancel_transfer_with_reason(): void
    {
        $asset = $this->createAsset();

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => Carbon::now()->toDateString(),
            'reason' => 'Pengajuan awal mutasi',
            'requested_by' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.asset.transfer.cancel', $transfer), [
                'cancel_reason' => 'Dibatalkan karena cabang asal masih membutuhkan mesin tersebut',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('cancelled', $transfer->fresh()->status);
        $this->assertStringContainsString('Dibatalkan', $transfer->fresh()->reason);
    }

    public function test_user_can_stream_transfer_pdf(): void
    {
        $asset = $this->createAsset();

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_cabang_id' => $this->cabangA->id,
            'to_cabang_id' => $this->cabangB->id,
            'transfer_date' => Carbon::now()->toDateString(),
            'reason' => 'Cetak berita acara mutasi',
            'requested_by' => $this->user->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.asset.transfer.pdf', $transfer));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }
}
