<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\OrderRequestResource;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\PaymentRequest;
use App\Models\PurchaseReceipt;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use App\Services\SequentialNumberGenerator;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UATPhase2Batch4VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Currency $currency;
    protected Supplier $supplier;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Test',
            'kode' => 'CAB-UTAMA',
            'alamat' => 'Jl. Test No. 1',
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'exchange_rate' => 1]
        );

        $this->supplier = Supplier::factory()->create([
            'code' => 'SUP-BATCH4',
            'perusahaan' => 'PT Supplier Batch Empat',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Tester Batch 4',
            'email' => 'tester.batch4@example.com',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = \App\Models\Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
    }

    /**
     * @test
     * Memverifikasi SequentialNumberGenerator menghasilkan nomor berurutan (0001, 0002) tanpa random jump
     */
    public function test_issue_9_sequential_number_generator_produces_ordered_codes(): void
    {
        $today = now()->format('Ymd');
        $prefix = 'TEST-SEQ-';

        $code1 = SequentialNumberGenerator::generate('order_requests', 'request_number', $prefix, 4, 'Ymd');
        $this->assertEquals("{$prefix}{$today}-0001", $code1);

        // Simpan dokumen pertama
        OrderRequest::withoutGlobalScopes()->create([
            'request_number' => $code1,
            'request_date' => now()->toDateString(),
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $code2 = SequentialNumberGenerator::generate('order_requests', 'request_number', $prefix, 4, 'Ymd');
        $this->assertEquals("{$prefix}{$today}-0002", $code2);

        // Simpan dokumen kedua
        OrderRequest::withoutGlobalScopes()->create([
            'request_number' => $code2,
            'request_date' => now()->toDateString(),
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $code3 = SequentialNumberGenerator::generate('order_requests', 'request_number', $prefix, 4, 'Ymd');
        $this->assertEquals("{$prefix}{$today}-0003", $code3);
    }

    /**
     * @test
     * Memverifikasi Order Request digenerate secara sequential dengan format OR-YYYYMMDD-XXXX
     */
    public function test_issue_9_order_request_number_is_sequential_and_prefixed_or(): void
    {
        $today = now()->format('Ymd');
        $orNum1 = HelperController::generateRequestNumber();

        $this->assertStringStartsWith("OR-{$today}-", $orNum1);
        $this->assertMatchesRegularExpression('/^OR-\d{8}-\d{4}$/', $orNum1);

        OrderRequest::withoutGlobalScopes()->create([
            'request_number' => $orNum1,
            'request_date' => now()->toDateString(),
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $orNum2 = HelperController::generateRequestNumber();
        $this->assertNotEquals($orNum1, $orNum2);

        $seq1 = (int) substr($orNum1, -4);
        $seq2 = (int) substr($orNum2, -4);
        $this->assertEquals($seq1 + 1, $seq2, 'Nomor Order Request harus berurutan (seq2 = seq1 + 1)');
    }

    /**
     * @test
     * Memverifikasi Payment Request distandardisasi dengan prefix PAY-REQ-YYYYMMDD-XXXX berurutan
     */
    public function test_issue_9_payment_request_prefix_standardization_pay_req(): void
    {
        $today = now()->format('Ymd');
        $prNum1 = PaymentRequest::generateNumber();

        $this->assertStringStartsWith("PAY-REQ-{$today}-", $prNum1);
        $this->assertMatchesRegularExpression('/^PAY-REQ-\d{8}-\d{4}$/', $prNum1);

        PaymentRequest::withoutGlobalScopes()->create([
            'request_number' => $prNum1,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'requested_by' => $this->user->id,
            'request_date' => now()->toDateString(),
            'total_amount' => 500000,
            'selected_invoices' => [],
            'status' => 'draft',
        ]);

        $prNum2 = PaymentRequest::generateNumber();
        $this->assertStringStartsWith("PAY-REQ-{$today}-", $prNum2);

        $seq1 = (int) substr($prNum1, -4);
        $seq2 = (int) substr($prNum2, -4);
        $this->assertEquals($seq1 + 1, $seq2, 'Nomor Payment Request harus berurutan');
    }

    /**
     * @test
     * Memverifikasi HelperController::generateUniqueCode untuk QC berurutan (QC-P- / QC-M-)
     */
    public function test_issue_9_quality_control_number_is_sequential(): void
    {
        $today = date('Ymd');
        $prefix = "QC-P-{$today}-";

        $qc1 = HelperController::generateUniqueCode('quality_controls', 'qc_number', $prefix, 4);
        $this->assertEquals("{$prefix}0001", $qc1);

        \Illuminate\Support\Facades\DB::table('quality_controls')->insert([
            'qc_number' => $qc1,
            'status' => 0,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => 1,
            'cabang_id' => $this->cabang->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qc2 = HelperController::generateUniqueCode('quality_controls', 'qc_number', $prefix, 4);
        $this->assertEquals("{$prefix}0002", $qc2);
    }

    /**
     * @test
     * Memverifikasi QualityControlService menggunakan prefix GRN- untuk penerimaan barang otomatis dari QC
     */
    public function test_issue_9_qc_auto_receipt_uses_grn_prefix(): void
    {
        $today = now()->format('Ymd');
        $qcService = app(QualityControlService::class);

        // Refleksi method protected generateReceiptNumber
        $reflection = new \ReflectionClass($qcService);
        $method = $reflection->getMethod('generateReceiptNumber');
        $method->setAccessible(true);

        $grn1 = $method->invoke($qcService);
        $this->assertStringStartsWith("GRN-{$today}-", $grn1, 'Receipt dari QC harus menggunakan prefix GRN- bukan PR-');
        $this->assertMatchesRegularExpression('/^GRN-\d{8}-\d{4}$/', $grn1);

        // Buat dummy receipt
        PurchaseReceipt::withoutGlobalScopes()->create([
            'receipt_number' => $grn1,
            'purchase_order_id' => 1,
            'receipt_date' => now()->toDateString(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
        ]);

        $grn2 = $method->invoke($qcService);
        $seq1 = (int) substr($grn1, -4);
        $seq2 = (int) substr($grn2, -4);
        $this->assertEquals($seq1 + 1, $seq2, 'Receipt GRN berikutnya harus berurutan');
    }

    /**
     * @test
     * Isu 9: PurchaseReceiptService dan QualityControlService (test di atas) menulis ke tabel/kolom yang SAMA
     * (purchase_receipts.receipt_number) — dulu dengan prefix BERBEDA ('RN-' vs 'GRN-'), kini disatukan ke 'GRN-'
     * sehingga berbagi satu urutan tanpa memandang jalur pembuatannya.
     */
    public function test_issue_9_purchase_receipt_service_generates_sequential_grn_number(): void
    {
        $service = app(PurchaseReceiptService::class);
        $today = now()->format('Ymd');

        $grn1 = $service->generateReceiptNumber();
        $this->assertStringStartsWith("GRN-{$today}-", $grn1, 'PurchaseReceiptService harus memakai prefix GRN- yang sama dengan jalur QC, bukan RN-');

        PurchaseReceipt::withoutGlobalScopes()->create([
            'receipt_number' => $grn1,
            'purchase_order_id' => 1,
            'receipt_date' => now()->toDateString(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
        ]);

        $grn2 = $service->generateReceiptNumber();
        $seq1 = (int) substr($grn1, -4);
        $seq2 = (int) substr($grn2, -4);
        $this->assertEquals($seq1 + 1, $seq2, 'Nomor GRN- dari PurchaseReceiptService harus berurutan');
    }

    /**
     * @test
     * Isu 9: jalur QC auto-receipt dan jalur manual (PurchaseReceiptService) berbagi SATU urutan pada prefix GRN- yang
     * sama — tidak ada lagi dua prefix (RN-/GRN-) untuk satu dokumen yang sama.
     */
    public function test_issue_9_qc_and_manual_receipt_share_one_grn_sequence(): void
    {
        $qcService = app(QualityControlService::class);
        $reflection = new \ReflectionClass($qcService);
        $method = $reflection->getMethod('generateReceiptNumber');
        $method->setAccessible(true);

        $fromQc = $method->invoke($qcService);
        PurchaseReceipt::withoutGlobalScopes()->create([
            'receipt_number' => $fromQc, 'purchase_order_id' => 1, 'receipt_date' => now()->toDateString(),
            'received_by' => $this->user->id, 'status' => 'completed', 'cabang_id' => $this->cabang->id, 'currency_id' => $this->currency->id,
        ]);

        $fromManual = app(PurchaseReceiptService::class)->generateReceiptNumber();

        $this->assertStringStartsWith('GRN-', $fromQc);
        $this->assertStringStartsWith('GRN-', $fromManual);
        $this->assertEquals((int) substr($fromQc, -4) + 1, (int) substr($fromManual, -4),
            'Nomor dari jalur manual harus melanjutkan urutan yang dibuat jalur QC (satu urutan bersama)');
    }

    /**
     * @test
     * Memverifikasi form Order Request memiliki default callback untuk generate nomor otomatis
     */
    public function test_issue_9_order_request_form_has_default_request_number(): void
    {
        $fileContent = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));
        $this->assertStringContainsString(
            "TextInput::make('request_number')",
            $fileContent,
            'OrderRequestResource harus memiliki field request_number'
        );
        $this->assertStringContainsString(
            "->default(fn () => HelperController::generateRequestNumber())",
            $fileContent,
            'Field request_number harus memiliki default callback sequential'
        );
    }

    /**
     * @test
     * Memverifikasi DeliveryOrderResource::getEloquentQuery() memuat eager loading relasi untuk mencegah N+1
     */
    public function test_issue_12_delivery_order_get_eloquent_query_eager_loads_relations(): void
    {
        $query = DeliveryOrderResource::getEloquentQuery();
        $eagerLoads = $query->getEagerLoads();

        $this->assertArrayHasKey('salesOrders.customer', $eagerLoads, 'Query DO harus eager load salesOrders.customer');
        $this->assertArrayHasKey('salesOrders.createdBy', $eagerLoads, 'Query DO harus eager load salesOrders.createdBy');
        $this->assertArrayHasKey('driver', $eagerLoads, 'Query DO harus eager load driver');
        $this->assertArrayHasKey('vehicle', $eagerLoads, 'Query DO harus eager load vehicle');
        $this->assertArrayHasKey('suratJalan', $eagerLoads, 'Query DO harus eager load suratJalan');
    }

    /**
     * @test
     * Memverifikasi tombol aksi persetujuan di ViewDeliveryOrder, ViewOrderRequest, dan ViewSaleOrder memiliki proteksi wire:loading
     */
    public function test_issue_12_approval_action_buttons_have_loading_disabled_protection(): void
    {
        $viewDoContent = file_get_contents(app_path('Filament/Resources/DeliveryOrderResource/Pages/ViewDeliveryOrder.php'));
        $this->assertStringContainsString(
            "->extraAttributes(['wire:loading.attr' => 'disabled'])",
            $viewDoContent,
            'Tombol pada ViewDeliveryOrder harus memiliki extraAttributes wire:loading.attr disabled'
        );

        $viewOrContent = file_get_contents(app_path('Filament/Resources/OrderRequestResource/Pages/ViewOrderRequest.php'));
        $this->assertStringContainsString(
            "->extraAttributes(['wire:loading.attr' => 'disabled'])",
            $viewOrContent,
            'Tombol pada ViewOrderRequest harus memiliki extraAttributes wire:loading.attr disabled'
        );

        $viewSoContent = file_get_contents(app_path('Filament/Resources/SaleOrderResource/Pages/ViewSaleOrder.php'));
        $this->assertStringContainsString(
            "->extraAttributes(['wire:loading.attr' => 'disabled'])",
            $viewSoContent,
            'Tombol pada ViewSaleOrder harus memiliki extraAttributes wire:loading.attr disabled'
        );
    }
}
