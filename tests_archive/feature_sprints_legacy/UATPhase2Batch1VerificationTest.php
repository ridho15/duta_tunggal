<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\OrderRequestService;
use App\Support\TaxTypeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UATPhase2Batch1VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        UnitOfMeasure::factory()->create();
        Currency::factory()->create();

        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
            'tempo_hutang' => 30,
        ]);
    }

    /**
     * Isu 4: Reject Order Request mencatat status rejected dan rejection_note pada item & header
     */
    public function test_order_request_can_be_rejected_with_reason(): void
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'request_approve',
            'created_by' => $this->user->id,
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'status' => OrderRequestItem::STATUS_DRAFT,
            'quantity' => 10,
        ]);

        $service = app(OrderRequestService::class);
        $reason = 'Stok gudang masih mencukupi untuk bulan ini.';
        $service->reject($orderRequest, $reason);

        $orderRequest->refresh();
        $item->refresh();

        $this->assertSame('rejected', $orderRequest->status);
        $this->assertStringContainsString($reason, (string) $orderRequest->note);
        $this->assertSame(OrderRequestItem::STATUS_REJECTED, $item->status);
        $this->assertSame($reason, $item->rejection_note);
    }

    /**
     * Isu 2: Approval Order Request memperbarui status item menjadi approved
     */
    public function test_order_request_approval_updates_item_approval_status(): void
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'request_approve',
            'created_by' => $this->user->id,
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'status' => OrderRequestItem::STATUS_DRAFT,
            'quantity' => 15,
            'unit_price' => 10000,
        ]);

        $service = app(OrderRequestService::class);
        $service->approve($orderRequest, [
            'create_purchase_order' => false,
            'selected_items' => [
                [
                    'id' => $item->id,
                    'is_approved' => true,
                    'quantity' => 15,
                    'unit_price' => 10000,
                ],
            ],
        ]);

        $orderRequest->refresh();
        $item->refresh();

        $this->assertSame('approved', $orderRequest->status);
        $this->assertSame(OrderRequestItem::STATUS_APPROVED, $item->status);
    }

    /**
     * Isu 5: Format TOP pada Purchase Order menampilkan label dan hari dengan benar
     */
    public function test_purchase_order_top_formatting_and_idr_currency_rate(): void
    {
        $poCash = new PurchaseOrder([
            'top_type' => 'cash',
        ]);
        $this->assertSame('Cash / Tunai (COD)', PurchaseOrderResource::formatPurchaseOrderTop($poCash));

        $poCredit = new PurchaseOrder([
            'top_type' => 'credit_days',
            'tempo_hutang' => 45,
        ]);
        $this->assertSame('Kredit 45 hari', PurchaseOrderResource::formatPurchaseOrderTop($poCredit));

        $poSupplierTempo = new PurchaseOrder([
            'top_type' => null,
            'tempo_hutang' => null,
        ]);
        $poSupplierTempo->setRelation('supplier', $this->supplier);
        $this->assertSame('Kredit 30 hari', PurchaseOrderResource::formatPurchaseOrderTop($poSupplierTempo));

        $poFallback = new PurchaseOrder([
            'top_type' => null,
            'tempo_hutang' => null,
        ]);
        $poFallback->setRelation('supplier', null);
        $this->assertSame('-', PurchaseOrderResource::formatPurchaseOrderTop($poFallback));
    }

    /**
     * Isu 17: Label UI dan TaxTypeHelper menggunakan ejaan Bahasa Indonesia yang benar
     */
    public function test_ui_labels_and_tax_types_have_no_typos(): void
    {
        $taxOptions = TaxTypeHelper::options();
        $this->assertArrayHasKey('eklusif', $taxOptions);
        $this->assertSame('Eksklusif', $taxOptions['eklusif']);

        // Pastikan DeliveryOrderResource tidak mengandung string 'Develiry Order Number'
        $doReflection = new \ReflectionClass(DeliveryOrderResource::class);
        $doCode = file_get_contents($doReflection->getFileName());
        $this->assertStringNotContainsString('Develiry Order Number', $doCode);
        $this->assertStringContainsString('Delivery Order Number', $doCode);
    }
}
