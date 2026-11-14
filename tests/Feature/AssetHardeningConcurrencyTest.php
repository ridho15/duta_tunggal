<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AssetDisposalService;
use App\Services\AssetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetHardeningConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected AssetDisposalService $disposalService;
    protected AssetTransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disposalService = new AssetDisposalService();
        $this->transferService = new AssetTransferService();
    }

    private function createAssetFixture(string $status = 'active'): array
    {
        $cabangA = Cabang::factory()->create(['nama' => 'Cabang Utama']);
        $cabangB = Cabang::factory()->create(['nama' => 'Cabang Kedua']);
        $user = User::factory()->create(['cabang_id' => $cabangA->id]);

        $assetCoa = ChartOfAccount::factory()->create(['code' => '1201', 'type' => 'Asset']);
        $depreciationCoa = ChartOfAccount::factory()->create(['code' => '1209', 'type' => 'Asset']);
        $expenseCoa = ChartOfAccount::factory()->create(['code' => '6101', 'type' => 'Expense']);
        $lossCoa = ChartOfAccount::factory()->create(['code' => '5290', 'type' => 'Expense', 'name' => 'Loss on Asset Disposal']);
        $cashCoa = ChartOfAccount::factory()->create(['code' => '1101', 'type' => 'Asset', 'name' => 'Kas']);
        $gainLossCoa = ChartOfAccount::factory()->create(['code' => '4101', 'type' => 'Revenue', 'name' => 'Gain Loss Disposal']);

        $asset = Asset::factory()->create([
            'name' => 'Mesin CNC Produksi',
            'code' => 'AST-TEST-' . rand(1000, 9999),
            'purchase_cost' => 50000000,
            'accumulated_depreciation' => 10000000,
            'status' => $status,
            'cabang_id' => $cabangA->id,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => $depreciationCoa->id,
            'depreciation_expense_coa_id' => $expenseCoa->id,
        ]);

        return compact('cabangA', 'cabangB', 'user', 'asset');
    }

    #[Test]
    public function it_blocks_duplicate_disposal_on_already_disposed_asset(): void
    {
        $data = $this->createAssetFixture('active');
        $this->actingAs($data['user']);

        // First disposal succeeds
        $disposal = $this->disposalService->createDisposal($data['asset'], [
            'disposal_date' => now()->toDateString(),
            'disposal_type' => 'scrap',
            'notes' => 'Pelepasan karena rusak permanen',
        ]);

        $this->assertNotNull($disposal);
        $this->assertEquals('disposed', $data['asset']->fresh()->status);

        // Second disposal call concurrently on the same asset must be blocked by row-level guard
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aset ini sudah pernah dihapusbukukan (disposed).');

        $this->disposalService->createDisposal($data['asset']->fresh(), [
            'disposal_date' => now()->toDateString(),
            'disposal_type' => 'scrap',
            'notes' => 'Pelepasan ganda yang seharusnya dicegah',
        ]);
    }

    #[Test]
    public function it_blocks_asset_transfer_request_on_non_active_or_pending_asset(): void
    {
        $data = $this->createAssetFixture('disposed');
        $this->actingAs($data['user']);

        // Cannot create transfer on disposed asset
        try {
            $this->transferService->createTransferRequest($data['asset'], $data['cabangB']->id, [
                'transfer_date' => now()->toDateString(),
                'reason' => 'Pindah ke cabang kedua',
            ]);
            $this->fail('Expected exception for non-active asset transfer');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Hanya aset berstatus aktif yang dapat dimutasi', $e->getMessage());
        }

        // Activate asset and create pending transfer
        $data['asset']->update(['status' => 'active']);
        $transfer = $this->transferService->createTransferRequest($data['asset'], $data['cabangB']->id, [
            'transfer_date' => now()->toDateString(),
            'reason' => 'Pindah ke cabang kedua',
        ]);
        $this->assertEquals('pending', $transfer->status);

        // Second transfer request concurrently must be rejected
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Asset sedang dalam proses transfer');

        $this->transferService->createTransferRequest($data['asset']->fresh(), $data['cabangB']->id, [
            'transfer_date' => now()->toDateString(),
            'reason' => 'Transfer duplikat simultan',
        ]);
    }

    #[Test]
    public function it_enforces_atomic_workflow_guards_on_transfer_approval_and_completion(): void
    {
        $data = $this->createAssetFixture('active');
        $this->actingAs($data['user']);

        $transfer = $this->transferService->createTransferRequest($data['asset'], $data['cabangB']->id, [
            'transfer_date' => now()->toDateString(),
            'reason' => 'Mutasi cabang',
        ]);

        // Attempting to complete directly while still pending must fail
        try {
            $this->transferService->completeTransfer($transfer);
            $this->fail('Expected exception when completing non-approved transfer');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Transfer harus disetujui terlebih dahulu', $e->getMessage());
        }

        // Approve transfer
        $approved = $this->transferService->approveTransfer($transfer);
        $this->assertEquals('approved', $approved->status);

        // Attempting to approve again must fail
        try {
            $this->transferService->approveTransfer($approved);
            $this->fail('Expected exception when re-approving transfer');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Transfer request tidak dalam status pending', $e->getMessage());
        }

        // Complete transfer moves asset branch atomically
        $completed = $this->transferService->completeTransfer($approved);
        $this->assertEquals('completed', $completed->status);
        $this->assertEquals($data['cabangB']->id, $data['asset']->fresh()->cabang_id);
    }
}
