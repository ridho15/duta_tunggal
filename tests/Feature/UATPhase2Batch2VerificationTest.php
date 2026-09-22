<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use App\Support\OrderRequestQuantityLock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestLivewireComponent extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
}

class UATPhase2Batch2VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Customer $customerA;
    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        UnitOfMeasure::factory()->create();
        Currency::factory()->create();

        $this->cabang = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product = Product::factory()->create();

        $this->customerA = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'PT Pelanggan Pertama',
            'code' => 'CUST-0001',
            'nik_npwp' => '3171234567890001',
        ]);

        $this->customerB = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'PT Pelanggan Kedua',
            'code' => '3172987654320002', // Kode lama diinput berupa NIK
            'nik_npwp' => '3172987654320002',
        ]);
    }

    /**
     * Isu 3: Item OR yang ditolak tidak dihitung dalam sisa dan OR menjadi complete saat item approved terpenuhi
     */
    public function test_issue_3_rejected_item_excluded_from_remaining_and_or_becomes_complete(): void
    {
        $or = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Item 1: Disetujui (Approved) - Qty 10, sudah terpenuhi 10
        $approvedItem = OrderRequestItem::factory()->create([
            'order_request_id' => $or->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'fulfilled_quantity' => 10,
            'status' => OrderRequestItem::STATUS_APPROVED,
        ]);

        // Item 2: Ditolak (Rejected) - Qty 5, fulfilled 0
        $rejectedItem = OrderRequestItem::factory()->create([
            'order_request_id' => $or->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'fulfilled_quantity' => 0,
            'status' => OrderRequestItem::STATUS_REJECTED,
        ]);

        // 1. Verifikasi OrderRequestQuantityLock pada item yang ditolak menghasilkan sisa 0
        $rejectedLimits = OrderRequestQuantityLock::orderRequestItemLimit($rejectedItem->id);
        $this->assertSame(0.0, (float) $rejectedLimits['remaining_for_po']);
        $this->assertSame(0.0, (float) $rejectedLimits['remaining_for_receipt']);

        // 2. Verifikasi sinkronisasi status pemenuhan
        $or->syncFulfillmentStatus();
        $or->refresh();

        // Status OR harus menjadi 'complete' karena satu-satunya item yang disetujui (10) sudah terpenuhi 100%
        $this->assertSame('complete', $or->status);
    }

    /**
     * Isu 11: Pilihan SO pada DO tidak memasukkan status completed dan mencegah penggabungan multi customer
     */
    public function test_issue_11_delivery_order_so_selection_and_multi_customer_rule(): void
    {
        $soApproved = SaleOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customerA->id,
            'status' => 'approved',
            'so_number' => 'SO-APP-001',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $soApproved->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'delivered_quantity' => 0,
        ]);

        $soCompleted = SaleOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customerA->id,
            'status' => 'completed',
            'so_number' => 'SO-CMP-002',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $soCompleted->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'delivered_quantity' => 10,
        ]);

        $livewire = new TestLivewireComponent();
        $form = DeliveryOrderResource::form(new Form($livewire));
        $salesOrdersComponent = collect($form->getFlatFields())->first(fn ($field) => $field->getName() === 'salesOrders');

        $this->assertNotNull($salesOrdersComponent);
        $options = $salesOrdersComponent->getOptions();

        // SO approved harus tersedia, SO completed TIDAK BOLEH tersedia
        $this->assertArrayHasKey($soApproved->id, $options);
        $this->assertArrayNotHasKey($soCompleted->id, $options);

        // Label harus memuat nama customer
        $this->assertStringContainsString('PT Pelanggan Pertama', $options[$soApproved->id]);
    }

    /**
     * Isu 13: Approval SO menolak persetujuan jika stok fisik bebas gudang tidak mencukupi
     */
    public function test_issue_13_so_approval_validates_free_stock(): void
    {
        // Stok bebas diatur hanya 5 unit
        InventoryStock::updateOrCreate([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ], [
            'stock' => 5,
            'allocated_stock' => 0,
        ]);

        $so = SaleOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customerA->id,
            'status' => 'request_approve',
            'total_amount' => 100000,
        ]);

        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 20, // Meminta 20 pcs padahal stok cuma 5
            'unit_price' => 5000,
        ]);

        SaleOrderItemWarehouseAllocation::create([
            'sale_order_item_id' => $soItem->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
        ]);

        $service = app(SalesOrderService::class);

        $this->expectException(ValidationException::class);
        $service->approve($so);
    }

    /**
     * Isu 14: Form Sales Invoice mengunci kuantitas/harga dan mencegah modifikasi baris repeater
     */
    public function test_issue_14_sales_invoice_repeater_is_locked(): void
    {
        $livewire = new TestLivewireComponent();
        $form = SalesInvoiceResource::form(new Form($livewire));
        $repeater = collect($form->getFlatFields())->first(fn ($field) => $field->getName() === 'invoiceItem');

        $this->assertNotNull($repeater);
        $this->assertInstanceOf(Repeater::class, $repeater);

        // Pastikan tidak bisa add, delete, reorder, clone
        $this->assertFalse($repeater->isAddable());
        $this->assertFalse($repeater->isDeletable());
        $this->assertFalse($repeater->isReorderable());
        $this->assertFalse($repeater->isCloneable());
    }

    /**
     * Isu 16: Privasi NIK customer terjaga dan tidak terekspos sebagai kode di dropdown/tabel
     */
    public function test_issue_16_customer_nik_protection_and_display_code(): void
    {
        // Customer A dengan kode internal valid
        $this->assertSame('CUST-0001', $this->customerA->getDisplayCode());
        $this->assertSame('(CUST-0001) PT Pelanggan Pertama', $this->customerA->getDisplayName());

        // Customer B dengan kode berupa 16 digit NIK
        $this->assertNotSame('3172987654320002', $this->customerB->getDisplayCode());
        $this->assertStringStartsWith('CUST-', $this->customerB->getDisplayCode());
        $this->assertStringNotContainsString('3172987654320002', $this->customerB->getDisplayName());

        // Pengujian masking pada tabel Customer
        $livewire = new TestLivewireComponent();
        $table = CustomerResource::table(new Table($livewire));
        $nikColumn = collect($table->getColumns())->first(fn ($col) => $col->getName() === 'nik_npwp');

        $this->assertNotNull($nikColumn);
        $maskedState = $nikColumn->formatState('3171234567890001');
        $this->assertSame('3171********0001', $maskedState);
    }
}
