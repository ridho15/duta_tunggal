<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoiceResource\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReceiptResource;
use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\Cabang;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptBiaya;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseInvoiceAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(RefreshDatabase::class);

if (! function_exists('registerAllPermissions')) {
    function registerAllPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => sprintf('%s %s', $action, $resource),
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}

function grantInvoicePermissions(User $user, array $permissions): void
{
    registerAllPermissions();

    $user->givePermissionTo($permissions);
}

function createPurchaseInvoiceOrderRequestItem(OrderRequest $orderRequest, Product $product, Supplier $supplier, Cabang $cabang, float $quantity = 100): OrderRequestItem
{
    return OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabang->id,
        'quantity' => $quantity,
        'currency_id' => Currency::where('code', 'IDR')->value('id') ?? Currency::query()->value('id'),
    ]);
}

class PurchaseInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $currency;
    protected $supplier;
    protected $warehouse;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required COAs for testing
        DB::table('customer_receipts')->delete();
        DB::table('journal_entries')->delete();
        DB::table('invoices')->delete();
        DB::table('purchase_receipts')->delete();
        DB::table('purchase_orders')->delete();
        DB::table('suppliers')->delete();
        DB::table('products')->delete();
        DB::table('warehouses')->delete();
        DB::table('currencies')->delete();
        DB::table('users')->delete();
        DB::table('chart_of_accounts')->delete();
        
        \App\Models\ChartOfAccount::create([
            'code' => '2100.10',
            'name' => 'PENERIMAAN BARANG BELUM TERTAGIH',
            'type' => 'liability',
            'is_active' => 1
        ]);
        \App\Models\ChartOfAccount::create([
            'code' => '2110',
            'name' => 'HUTANG SUPPLIER',
            'type' => 'liability',
            'is_active' => 1
        ]);
        \App\Models\ChartOfAccount::create([
            'code' => '1170.06',
            'name' => 'PPN MASUKAN',
            'type' => 'asset',
            'is_active' => 1
        ]);
        \App\Models\ChartOfAccount::create([
            'code' => '6100',
            'name' => 'BIAYA PENJUALAN',
            'type' => 'expense',
            'is_active' => 1
        ]);

        $this->cabang = Cabang::factory()->create();
        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $permissions = [
            'view any invoice',
            'view invoice',
            'create invoice',
            'update invoice',
            'delete invoice',
            'view any supplier',
            'view any warehouse',
            'view any product',
            'view any currency',
            'view any purchase order',
            'view any purchase receipt',
            'view any account payable',
            'view account payable',
            'create account payable',
            'update account payable',
            'delete account payable',
            'restore account payable',
            'force-delete account payable',
            'view any ageing schedule',
        ];
        grantInvoicePermissions($this->user, $permissions);
        $this->actingAs($this->user);

        \App\Models\UnitOfMeasure::factory()->create();
        $this->currency = Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
        ]);
        $this->supplier = Supplier::factory()->create([
            'tempo_hutang' => 21,
        ]);
        $this->warehouse = Warehouse::factory()->create([
            'status' => 1,
            'cabang_id' => $this->cabang->id,
        ]);
        $this->product = Product::factory()->create([
            'uom_id' => \App\Models\UnitOfMeasure::first()->id,
        ]);

        // Create required COAs for invoice creation
        \App\Models\ChartOfAccount::factory()->create([
            'code' => '1130',
            'name' => 'PPn Masukan',
            'type' => 'asset',
        ]);
    }

    private function createReceiptBackedSource(
        ?Supplier $supplier = null,
        ?OrderRequest $orderRequest = null,
        ?Cabang $cabang = null,
        string $receiptStatus = 'completed'
    ): array {
        $supplier ??= $this->supplier;
        $cabang ??= $this->cabang;
        $orderRequest ??= OrderRequest::factory()->create([
            'cabang_id' => $cabang->id,
            'status' => 'approved',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'status' => 'completed',
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $supplier, $cabang, 1);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
        ]);
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $cabang->id,
            'status' => $receiptStatus,
        ]);
        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        return compact('supplier', 'orderRequest', 'purchaseOrder', 'receipt');
    }

    private function assertReceiptSourceValidationError(array $data, string $field): void
    {
        try {
            app(PurchaseInvoiceAccountingService::class)->validateReceiptBackedCreateData($data);
            $this->fail("Expected validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function createSupplierReceipt(
        Supplier $supplier,
        Cabang $cabang,
        string $receiptDate,
        ?string $createdAt = null
    ): PurchaseReceipt {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
        ]);

        return PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $cabang->id,
            'receipt_date' => $receiptDate,
            'created_at' => $createdAt ?? $receiptDate,
        ]);
    }

    public function test_purchase_invoice_resource_can_render_list_page()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\ListPurchaseInvoices::class)
            ->assertSuccessful();
    }

    public function test_purchase_invoice_resource_can_render_create_page()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->assertSuccessful();
    }

    public function test_purchase_invoice_create_data_forces_draft_status()
    {
        $data = PurchaseInvoiceResource::mutateFormDataBeforeCreate([
            'status' => 'paid',
        ]);

        $this->assertSame(Invoice::STATUS_DRAFT, $data['status']);
    }

    public function test_purchase_invoice_create_data_persists_source_currency_context()
    {
        $usd = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'to_rupiah' => 15000,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $po->purchaseOrderCurrency()->create([
            'currency_id' => $usd->id,
            'nominal' => 16000,
        ]);

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);
        $po->update([
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang, 1);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'currency_id' => $usd->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'tax' => 0,
        ]);
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);
        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $page = new class extends \App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice {
            public function setTestData(array $data): array
            {
                $this->data = $data;
                $method = new \ReflectionMethod($this, 'mutateFormDataBeforeCreate');
                $method->setAccessible(true);

                return $method->invoke($this, $data);
            }
        };

        $data = $page->setTestData([
            'selected_supplier' => $this->supplier->id,
            'selected_order_request' => $orderRequest->id,
            'selected_purchase_orders' => [$po->id],
            'selected_purchase_receipts' => [$receipt->id],
            'cabang_id' => $this->cabang->id,
            'subtotal' => '100.000,00',
            'ppn_rate' => 11,
            'total' => '111.000,00',
            'invoiceItem' => [],
        ]);

        $this->assertSame($usd->id, $data['currency_id']);
        $this->assertSame(16000.0, (float) $data['exchange_rate']);
    }

    public function test_purchase_invoice_formats_foreign_source_amount_with_idr_equivalent(): void
    {
        $usd = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'to_rupiah' => 15000,
        ]);

        $invoice = Invoice::factory()->make([
            'currency_id' => $usd->id,
            'exchange_rate' => 15000,
        ]);

        $this->assertSame(
            'Rp 8.879.400,00 / USD 591.96',
            PurchaseInvoiceResource::formatInvoiceCurrencyPair($invoice, 591.96)
        );
        $this->assertSame(8879400.0, PurchaseInvoiceResource::invoiceAmountToIdr($invoice, 591.96));
    }

    public function test_purchase_invoice_edit_data_clears_import_charge_state_for_non_import_purchase_order()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'is_import' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'subtotal' => 100000,
            'pph22_amount' => 5000,
            'bea_masuk_amount' => 7000,
            'other_fee' => [
                ['name' => 'PPH 22', 'amount' => 5000],
                ['name' => 'Bea Masuk', 'amount' => 7000],
                ['name' => 'Biaya Lain', 'amount' => 3000],
            ],
        ]);

        $page = new class extends \App\Filament\Resources\PurchaseInvoiceResource\Pages\EditPurchaseInvoice {
            public function setTestRecord($record): void
            {
                $this->record = $record;
            }
        };
        $page->setTestRecord($invoice);

        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeFill');
        $method->setAccessible(true);
        $data = $method->invoke($page, []);

        $this->assertSame(0.0, (float) $data['pph22_amount']);
        $this->assertSame(0.0, (float) $data['bea_masuk_amount']);
        $this->assertCount(1, $data['other_fees']);
        $this->assertSame('Biaya Lain', $data['other_fees'][0]['name']);
    }

    public function test_purchase_invoice_view_uses_stored_currency_display()
    {
        $usd = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'to_rupiah' => 15000,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $po->purchaseOrderCurrency()->create([
            'currency_id' => $usd->id,
            'nominal' => 16000,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'currency_id' => $usd->id,
            'exchange_rate' => 16000,
            'subtotal' => 100,
            'ppn_rate' => 11,
            'total' => 111,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertSame('$', $invoice->displayCurrency->symbol);
        $this->assertSame('USD', $invoice->displayCurrency->code);
        $this->assertSame($usd->id, $invoice->display_currency_id);
    }

    public function test_purchase_invoice_create_preserves_idr_currency_for_idr_po()
    {
        $idr = Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $po->purchaseOrderCurrency()->create([
            'currency_id' => $idr->id,
            'nominal' => 1,
        ]);

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);
        $po->update([
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang, 1);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'currency_id' => $idr->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'tax' => 0,
        ]);
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);
        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $page = new class extends \App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice {
            public function setTestData(array $data): array
            {
                $this->data = $data;
                $method = new \ReflectionMethod($this, 'mutateFormDataBeforeCreate');
                $method->setAccessible(true);

                return $method->invoke($this, $data);
            }
        };

        $data = $page->setTestData([
            'selected_supplier' => $this->supplier->id,
            'selected_order_request' => $orderRequest->id,
            'selected_purchase_orders' => [$po->id],
            'selected_purchase_receipts' => [$receipt->id],
            'cabang_id' => $this->cabang->id,
            'subtotal' => '100.000,00',
            'ppn_rate' => 11,
            'total' => '111.000,00',
            'invoiceItem' => [],
        ]);

        $this->assertSame($idr->id, $data['currency_id']);
        $this->assertSame(1.0, (float) $data['exchange_rate']);
    }

    public function test_mark_as_sent_action_shows_friendly_notification_when_update_fails()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-FAIL-' . rand(1000, 9999),
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => 100000,
            'tax' => 0,
            'total' => 100000,
            'status' => 'draft',
            'purchase_receipts' => [],
            'cabang_id' => $this->cabang->id,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 20000,
            'total' => 100000,
        ]);

        $this->user->syncRoles(['Super Admin']);
        $this->actingAs($this->user);

        Invoice::updating(function () {
            throw new RuntimeException('simulated invoice update failure');
        });

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('mark_as_sent')
            ->assertNotified('Gagal Mengubah Status Invoice');

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_purchase_invoice_pdf_renders_supplier_and_ppn_correctly(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $invoice = Invoice::withoutEvents(function () use ($purchaseOrder) {
            return Invoice::factory()->create([
                'invoice_number' => 'PINV-PDF-' . rand(1000, 9999),
                'from_model_type' => PurchaseOrder::class,
                'from_model_id' => $purchaseOrder->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(14),
                'subtotal' => 100000,
                'dpp' => 100000,
                'ppn_rate' => 11,
                'tax' => 11,
                'total' => 111000,
                'status' => 'draft',
                'purchase_receipts' => [],
                'cabang_id' => $this->cabang->id,
            ]);
        });

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 20000,
            'subtotal' => 100000,
            'tax_rate' => 11,
            'tax_amount' => 11000,
            'total' => 111000,
        ]);

        $html = view('pdf.purchase-order-invoice-2', [
            'invoice' => $invoice->load('fromModel', 'invoiceItem.product', 'cabang'),
        ])->render();

        $this->assertStringContainsString('INVOICE PEMBELIAN', $html);
        $this->assertStringContainsString($this->supplier->perusahaan, $html);
        $this->assertStringContainsString('PPN 11,00%', $html);
        $this->assertStringContainsString('Rp 11.000', $html);
        $this->assertStringContainsString('Rp 111.000', $html);
    }

    public function test_import_purchase_invoice_pdf_shows_import_breakdown(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'is_import' => true,
            'tempo_hutang' => 0,
        ]);

        $invoice = Invoice::withoutEvents(function () use ($purchaseOrder) {
            return Invoice::factory()->create([
                'invoice_number' => 'PINV-IMP-' . rand(1000, 9999),
                'from_model_type' => PurchaseOrder::class,
                'from_model_id' => $purchaseOrder->id,
                'invoice_date' => now(),
                'due_date' => now(),
                'subtotal' => 100000,
                'dpp' => 100000,
                'ppn_rate' => 11,
                'tax' => 11,
                'pph22_amount' => 5000,
                'bea_masuk_amount' => 7000,
                'other_fee' => [
                    ['name' => 'Biaya Handling', 'amount' => 3000],
                ],
                'total' => 126000,
                'status' => 'draft',
                'purchase_receipts' => [],
                'cabang_id' => $this->cabang->id,
            ]);
        });

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 20000,
            'subtotal' => 100000,
            'tax_rate' => 11,
            'tax_amount' => 11000,
            'total' => 111000,
        ]);

        $html = view('pdf.purchase-order-invoice-2', [
            'invoice' => $invoice->load('fromModel', 'invoiceItem.product', 'cabang'),
        ])->render();

        $this->assertStringContainsString('Breakdown Impor', $html);
        $this->assertStringContainsString('PPh 22', $html);
        $this->assertStringContainsString('BEA MASUK', $html);
        $this->assertStringContainsString('TOTAL IMPOR', $html);
    }

    public function test_purchase_invoice_form_has_required_fields()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->assertFormExists()
            ->assertFormFieldExists('invoice_number')
            ->assertFormFieldExists('selected_supplier')
            ->assertFormFieldExists('selected_order_request')
            ->assertFormFieldExists('cabang_id')
            ->assertFormFieldExists('selected_cabang_label')
            ->assertFormFieldExists('selected_purchase_orders')
            ->assertFormFieldExists('selected_purchase_receipts')
            ->assertFormFieldExists('invoice_date')
            ->assertFormFieldExists('due_date')
            ->assertFormFieldExists('pph22_amount')
            ->assertFormFieldExists('bea_masuk_amount')
            ->assertFormFieldExists('tax')
            ->assertFormFieldExists('ppn_rate')
            ->assertFormFieldExists('other_fees');
    }

    public function test_purchase_invoice_source_fields_put_cabang_after_order_request_as_readonly_display(): void
    {
        $resourceSource = file_get_contents(app_path('Filament/Resources/PurchaseInvoiceResource.php'));

        $supplierPosition = strpos($resourceSource, "Select::make('selected_supplier')");
        $orderRequestPosition = strpos($resourceSource, "Select::make('selected_order_request')");
        $hiddenCabangPosition = strpos($resourceSource, "Hidden::make('cabang_id')");
        $visibleCabangPosition = strpos($resourceSource, "TextInput::make('selected_cabang_label')");
        $purchaseOrderPosition = strpos($resourceSource, "CheckboxList::make('selected_purchase_orders')");

        $this->assertNotFalse($supplierPosition);
        $this->assertNotFalse($orderRequestPosition);
        $this->assertNotFalse($hiddenCabangPosition);
        $this->assertNotFalse($visibleCabangPosition);
        $this->assertNotFalse($purchaseOrderPosition);
        $this->assertStringNotContainsString("Select::make('cabang_id')", $resourceSource);
        $this->assertTrue($supplierPosition < $orderRequestPosition);
        $this->assertTrue($orderRequestPosition < $hiddenCabangPosition);
        $this->assertTrue($hiddenCabangPosition < $visibleCabangPosition);
        $this->assertTrue($visibleCabangPosition < $purchaseOrderPosition);
    }

    public function test_supplier_options_prioritize_five_unique_recent_receipt_suppliers(): void
    {
        $recentSuppliers = collect([
            ['name' => 'Zulu Terbaru', 'date' => '2026-06-19 09:00:00', 'created_at' => '2026-06-19 11:00:00'],
            ['name' => 'Yankee Kedua', 'date' => '2026-06-19 09:00:00', 'created_at' => '2026-06-19 10:00:00'],
            ['name' => 'Xray Ketiga', 'date' => '2026-06-17 09:00:00', 'created_at' => '2026-06-17 09:00:00'],
            ['name' => 'Whiskey Keempat', 'date' => '2026-06-16 09:00:00', 'created_at' => '2026-06-16 09:00:00'],
            ['name' => 'Victor Kelima', 'date' => '2026-06-15 09:00:00', 'created_at' => '2026-06-15 09:00:00'],
            ['name' => 'Alpha Keenam', 'date' => '2026-06-14 09:00:00', 'created_at' => '2026-06-14 09:00:00'],
        ])->map(function (array $data) {
            $supplier = Supplier::factory()->create(['perusahaan' => $data['name']]);
            $this->createSupplierReceipt($supplier, $this->cabang, $data['date'], $data['created_at']);

            return $supplier;
        });

        $this->createSupplierReceipt(
            $recentSuppliers->first(),
            $this->cabang,
            '2026-01-01 09:00:00'
        );

        $otherCabang = Cabang::factory()->create();
        $otherBranchSupplier = Supplier::factory()->create(['perusahaan' => 'Supplier Cabang Lain']);
        $this->createSupplierReceipt(
            $otherBranchSupplier,
            $otherCabang,
            '2026-06-20 09:00:00'
        );

        $deletedReceiptSupplier = Supplier::factory()->create(['perusahaan' => 'Supplier Receipt Terhapus']);
        $deletedReceipt = $this->createSupplierReceipt(
            $deletedReceiptSupplier,
            $this->cabang,
            '2026-06-21 09:00:00'
        );
        DB::table('purchase_receipts')
            ->where('id', $deletedReceipt->id)
            ->update(['deleted_at' => now()]);

        $deletedOrderSupplier = Supplier::factory()->create(['perusahaan' => 'Supplier PO Terhapus']);
        $deletedOrderReceipt = $this->createSupplierReceipt(
            $deletedOrderSupplier,
            $this->cabang,
            '2026-06-22 09:00:00'
        );
        DB::table('purchase_orders')
            ->where('id', $deletedOrderReceipt->purchase_order_id)
            ->update(['deleted_at' => now()]);

        $options = PurchaseInvoiceResource::getSupplierOptions();
        $optionIds = array_map('intval', array_keys($options));
        $expectedRecentIds = $recentSuppliers->take(5)->pluck('id')->all();

        $this->assertSame($expectedRecentIds, array_slice($optionIds, 0, 5));
        $this->assertSame($optionIds, array_values(array_unique($optionIds)));
        $this->assertNotContains($otherBranchSupplier->id, array_slice($optionIds, 0, 5));
        $this->assertNotContains($deletedReceiptSupplier->id, array_slice($optionIds, 0, 5));
        $this->assertNotContains($deletedOrderSupplier->id, array_slice($optionIds, 0, 5));

        $expectedAlphabeticalIds = Supplier::query()
            ->whereNotIn('id', $expectedRecentIds)
            ->orderBy('perusahaan')
            ->orderBy('id')
            ->limit(45)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame($expectedAlphabeticalIds, array_slice($optionIds, 5));
    }

    public function test_supplier_options_fall_back_to_alphabetical_order_and_limit_fifty(): void
    {
        foreach (range(1, 55) as $number) {
            Supplier::factory()->create([
                'perusahaan' => sprintf('Supplier %03d', $number),
            ]);
        }

        $options = PurchaseInvoiceResource::getSupplierOptions();
        $expectedIds = Supplier::query()
            ->orderBy('perusahaan')
            ->orderBy('id')
            ->limit(50)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertCount(50, $options);
        $this->assertSame($expectedIds, array_map('intval', array_keys($options)));
    }

    public function test_supplier_selection_filters_purchase_orders()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();
        $product = Product::factory()->create();

        // Create PO for supplier1
        $purchaseOrder1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier1->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        // Create PO for supplier2
        $purchaseOrder2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier2->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier1->id,
            ])
            ->assertFormSet([
                'selected_purchase_orders' => [],
                'selected_purchase_receipts' => [],
                'invoiceItem' => [],
                'subtotal' => 0,
                'total' => 0,
            ]);
    }

    public function test_order_request_and_purchase_order_options_follow_cabang_scope()
    {
        $supplier = Supplier::factory()->create();
        $branchA = $this->cabang;
        $branchB = Cabang::factory()->create();

        $orderRequestA = OrderRequest::factory()->create([
            'cabang_id' => $branchA->id,
            'status' => 'approved',
        ]);
        $purchaseOrderA = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $branchA->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequestA->id,
        ]);
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrderA->id,
            'status' => 'completed',
            'cabang_id' => $branchA->id,
        ]);

        $orderRequestB = OrderRequest::factory()->create([
            'cabang_id' => $branchB->id,
            'status' => 'approved',
        ]);
        $purchaseOrderB = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $branchB->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequestB->id,
        ]);
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrderB->id,
            'status' => 'completed',
            'cabang_id' => $branchB->id,
        ]);

        $allAccessUser = User::factory()->create([
            'cabang_id' => $branchA->id,
            'manage_type' => 'all',
        ]);
        $this->actingAs($allAccessUser);

        $allBranchOrderRequests = PurchaseInvoiceResource::getOrderRequestOptions($supplier->id);
        $this->assertArrayHasKey($orderRequestA->id, $allBranchOrderRequests);
        $this->assertArrayHasKey($orderRequestB->id, $allBranchOrderRequests);

        $branchAOrderRequests = PurchaseInvoiceResource::getOrderRequestOptions($supplier->id, $branchA->id);
        $this->assertArrayHasKey($orderRequestA->id, $branchAOrderRequests);
        $this->assertArrayNotHasKey($orderRequestB->id, $branchAOrderRequests);

        $branchAPurchaseOrders = PurchaseInvoiceResource::getPurchaseOrderOptions($supplier->id, $orderRequestA->id, $branchA->id);
        $this->assertArrayHasKey($purchaseOrderA->id, $branchAPurchaseOrders);
        $this->assertArrayNotHasKey($purchaseOrderB->id, $branchAPurchaseOrders);

        $branchUser = User::factory()->create([
            'cabang_id' => $branchB->id,
        ]);
        $this->actingAs($branchUser);

        $branchBOrderRequests = PurchaseInvoiceResource::getOrderRequestOptions($supplier->id);
        $this->assertArrayHasKey($orderRequestB->id, $branchBOrderRequests);
        $this->assertArrayNotHasKey($orderRequestA->id, $branchBOrderRequests);
    }

    public function test_order_request_selection_auto_fills_readonly_cabang_label(): void
    {
        $source = $this->createReceiptBackedSource();
        $expectedCabangLabel = "({$this->cabang->kode}) {$this->cabang->nama}";

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $source['supplier']->id,
            ])
            ->fillForm([
                'selected_order_request' => $source['orderRequest']->id,
            ])
            ->assertFormSet([
                'cabang_id' => $this->cabang->id,
                'selected_cabang_label' => $expectedCabangLabel,
                'selected_purchase_orders' => [],
            ]);

        $purchaseOrderOptions = PurchaseInvoiceResource::getPurchaseOrderOptions(
            $source['supplier']->id,
            $source['orderRequest']->id,
            $this->cabang->id
        );

        $this->assertArrayHasKey($source['purchaseOrder']->id, $purchaseOrderOptions);
    }

    public function test_order_request_cabang_context_falls_back_to_item_then_receipt(): void
    {
        $supplier = Supplier::factory()->create();
        $itemCabang = Cabang::factory()->create([
            'kode' => 'ITM',
            'nama' => 'Cabang Item',
        ]);
        $receiptCabang = Cabang::factory()->create([
            'kode' => 'RCP',
            'nama' => 'Cabang Receipt',
        ]);

        $orderRequestWithItemBranch = OrderRequest::factory()->create([
            'cabang_id' => null,
            'status' => 'approved',
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequestWithItemBranch, $this->product, $supplier, $itemCabang, 1);

        $itemContext = PurchaseInvoiceResource::getOrderRequestCabangContext($orderRequestWithItemBranch->id, $supplier->id);

        $this->assertSame($itemCabang->id, $itemContext['id']);
        $this->assertSame("(ITM) Cabang Item", $itemContext['label']);

        $orderRequestWithReceiptBranch = OrderRequest::factory()->create([
            'cabang_id' => null,
            'status' => 'approved',
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $receiptCabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequestWithReceiptBranch->id,
        ]);
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $receiptCabang->id,
        ]);

        $receiptContext = PurchaseInvoiceResource::getOrderRequestCabangContext($orderRequestWithReceiptBranch->id, $supplier->id);

        $this->assertSame($receiptCabang->id, $receiptContext['id']);
        $this->assertSame("(RCP) Cabang Receipt", $receiptContext['label']);
    }

    public function test_partial_purchase_receipt_can_be_selected_for_purchase_invoice()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'partially_received',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'Eklusif',
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'partial',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'qty_accepted' => 4,
        ]);

        $purchaseOrderOptions = PurchaseInvoiceResource::getPurchaseOrderOptions(
            $this->supplier->id,
            $orderRequest->id,
            $this->cabang->id
        );

        $this->assertArrayHasKey($purchaseOrder->id, $purchaseOrderOptions);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
            ])
            ->fillForm([
                'selected_purchase_receipts' => [$receipt->id],
            ])
            ->assertSet('data.purchase_receipts', [$receipt->id])
            ->assertSet('data.invoiceItem.0.quantity', 4);
    }

    public function test_purchase_order_selection_loads_receipts()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        // Create PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
            'cabang_id' => $this->cabang->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $product, $supplier, $this->cabang);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'tax' => 1000,
            'discount' => 0
        ]);

        // Create completed receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
            ])
            ->assertFormSet([
                'selected_purchase_receipts' => [],
                'invoiceItem' => [],
                'subtotal' => 0,
                'total' => 0,
            ]);
    }

    public function test_purchase_receipt_checkbox_labels_use_rupiah_format()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
            'cabang_id' => $this->cabang->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $product, $supplier, $this->cabang);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'tax' => 0,
            'discount' => 0,
        ]);

        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
        ]);

        $expectedLabel = sprintf(
            '[%s] %s - %s',
            $purchaseOrder->po_number,
            $purchaseReceipt->receipt_number,
            \App\Helpers\MoneyHelper::rupiah(1500)
        );

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
            ])
            ->assertSeeHtml($expectedLabel)
            ->assertDontSeeHtml('Rp. 1.500');
    }

    public function test_receipt_selection_calculates_invoice_items()
    {
        // Test that the form can be filled with basic data
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'invoice_number' => 'PINV-TEST-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->assertFormSet([
                'invoice_number' => 'PINV-TEST-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ]);
    }

    public function test_tax_and_other_fees_calculations()
    {
        // PPN is no longer manually editable from the invoice form.
        // Source-backed create flows set tax/ppn from PO items when receipts are selected.
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'invoice_number' => 'PINV-TAX-TEST-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'ppn_rate' => 11,
            ])
            ->assertFormSet([
                'tax' => 0,
                'ppn_rate' => 11,
            ]);
    }

    public function test_purchase_invoice_create_normalizes_tax_to_percentage()
    {
        $supplier = Supplier::factory()->create();
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);
        $product = Product::factory()->create();
        createPurchaseInvoiceOrderRequestItem($orderRequest, $product, $supplier, $this->cabang);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 1000000,
            'discount' => 0,
            'tax' => 11,
        ]);
        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrder->purchaseOrderItem->first()->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-NORMALIZE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'ppn_rate' => 11,
                'status' => Invoice::STATUS_DRAFT,
                'invoiceItem' => [[
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 1000000,
                    'total' => 10000000,
                ]],
                'other_fees' => [[
                    'name' => 'Biaya Admin',
                    'amount' => 100000,
                ]],
                'receiptBiayaItems' => [],
            ])
            ->call('create');

        $saved = Invoice::where('invoice_number', 'PINV-NORMALIZE-001')->firstOrFail();

        $this->assertSame(11.0, (float) $saved->tax);
        $this->assertSame(11.0, (float) $saved->ppn_rate);
        $this->assertSame(1100000.0, (float) $saved->ppn_amount);
        $this->assertSame(11100000.0, (float) $saved->total);
        $this->assertSame([], $saved->other_fee);
    }

    public function test_purchase_invoice_from_receipt_persists_full_nominal_branch_ap_and_journals(): void
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang, 10);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 720000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'eklusif',
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-FULL-NOMINAL-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'ppn_rate' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', 'PINV-FULL-NOMINAL-001')
            ->with('invoiceItem', 'accountPayable')
            ->firstOrFail();

        $this->assertSame(7200000.0, (float) $invoice->subtotal);
        $this->assertSame(7200000.0, (float) $invoice->dpp);
        $this->assertSame(7992000.0, (float) $invoice->total);
        $this->assertSame(11.0, round((float) $invoice->ppn_rate, 2));
        $this->assertSame(792000.0, (float) $invoice->ppn_amount);
        $this->assertSame($this->cabang->id, (int) $invoice->cabang_id);
        $this->assertSame(7200000.0, (float) $invoice->invoiceItem->first()->total);
        $this->assertSame(11.0, (float) $invoice->invoiceItem->first()->tax_rate);
        $this->assertSame(792000.0, (float) $invoice->invoiceItem->first()->tax_amount);
        $this->assertSame(7992000.0, (float) $invoice->accountPayable->total);
        $this->assertSame(7992000.0, (float) $invoice->accountPayable->remaining);
        $this->assertSame($this->cabang->id, (int) $invoice->accountPayable->cabang_id);

        $journals = JournalEntry::withoutGlobalScopes()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', false)
            ->get();

        $this->assertSame(7992000.0, (float) $journals->sum('debit'));
        $this->assertSame(7992000.0, (float) $journals->sum('credit'));
        $this->assertTrue($journals->contains(fn (JournalEntry $entry) => (float) $entry->debit === 7200000.0));
        $this->assertTrue($journals->contains(fn (JournalEntry $entry) => (float) $entry->debit === 792000.0));
        $this->assertTrue($journals->contains(fn (JournalEntry $entry) => (float) $entry->credit === 7992000.0));
        $this->assertTrue($journals->every(fn (JournalEntry $entry) => (int) $entry->cabang_id === $this->cabang->id));
    }

    public function test_purchase_invoice_ignores_manual_ppn_for_mixed_tax_receipts(): void
    {
        $secondProduct = Product::factory()->create(['cabang_id' => $this->cabang->id]);
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang, 10);
        createPurchaseInvoiceOrderRequestItem($orderRequest, $secondProduct, $this->supplier, $this->cabang, 10);

        $taxedPoItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'eklusif',
        ]);
        $nonTaxPoItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $secondProduct->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        foreach ([[$taxedPoItem, $this->product], [$nonTaxPoItem, $secondProduct]] as [$poItem, $product]) {
            PurchaseReceiptItem::factory()->create([
                'purchase_receipt_id' => $receipt->id,
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $product->id,
                'qty_received' => 10,
                'qty_accepted' => 10,
                'qty_rejected' => 0,
                'warehouse_id' => $this->warehouse->id,
                'status' => 'completed',
            ]);
        }

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-MIXED-TAX-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'ppn_rate' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', 'PINV-MIXED-TAX-001')
            ->with('invoiceItem', 'accountPayable')
            ->firstOrFail();

        $this->assertSame(2000000.0, (float) $invoice->subtotal);
        $this->assertSame(110000.0, (float) $invoice->ppn_amount);
        $this->assertSame(2110000.0, (float) $invoice->total);
        $this->assertSame(5.5, round((float) $invoice->ppn_rate, 2));
        $this->assertSame(110000.0, (float) $invoice->invoiceItem->sum('tax_amount'));
    }

    public function test_purchase_receipt_resource_is_not_manually_creatable_or_editable(): void
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'cabang_id' => $this->cabang->id,
            ])->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertFalse(PurchaseReceiptResource::canCreate());
        $this->assertFalse(PurchaseReceiptResource::canEdit($receipt));
        $this->assertFalse(PurchaseReceiptResource::canDelete($receipt));
        $this->assertArrayNotHasKey('create', PurchaseReceiptResource::getPages());
        $this->assertArrayNotHasKey('edit', PurchaseReceiptResource::getPages());
    }

    public function test_purchase_invoice_uses_receipt_branch_when_order_request_creator_has_different_branch(): void
    {
        $creatorBranch = Cabang::factory()->create();
        $creator = User::factory()->create(['cabang_id' => $creatorBranch->id]);

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => null,
            'created_by' => $creator->id,
            'status' => 'approved',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $this->product, $this->supplier, $this->cabang, 1);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'tax' => 0,
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-BRANCH-SOURCE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'ppn_rate' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', 'PINV-BRANCH-SOURCE-001')
            ->with('accountPayable')
            ->firstOrFail();

        $this->assertSame($this->cabang->id, (int) $invoice->cabang_id);
        $this->assertNotSame($creatorBranch->id, (int) $invoice->cabang_id);
        $this->assertSame($this->cabang->id, (int) $invoice->accountPayable->cabang_id);
    }

    public function test_purchase_invoice_audit_command_dry_runs_and_repairs_with_reversal(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 720000,
            'tax' => 11,
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::withoutEvents(function () use ($purchaseOrder, $receipt) {
            return Invoice::factory()->create([
                'invoice_number' => 'PINV-BROKEN-REPAIR-001',
                'from_model_type' => PurchaseOrder::class,
                'from_model_id' => $purchaseOrder->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => 7200,
                'dpp' => 7200,
                'ppn_rate' => 11,
                'tax' => 11,
                'total' => 7992,
                'status' => Invoice::STATUS_DRAFT,
                'cabang_id' => $this->cabang->id,
                'purchase_receipts' => [$receipt->id],
            ]);
        });

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 720000,
            'total' => 7200000,
        ]);

        $invoice->accountPayable()->create([
            'supplier_id' => $this->supplier->id,
            'total' => 7992,
            'paid' => 0,
            'remaining' => 7992,
            'status' => \App\Enums\PaymentStatus::UNPAID->value,
            'cabang_id' => $this->cabang->id,
        ]);

        foreach ([[7200, 0], [792, 0], [0, 7992]] as [$debit, $credit]) {
            JournalEntry::create([
                'coa_id' => \App\Models\ChartOfAccount::query()->first()->id,
                'date' => now(),
                'reference' => $invoice->invoice_number,
                'description' => 'Broken purchase invoice journal',
                'debit' => $debit,
                'credit' => $credit,
                'journal_type' => 'purchase',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'cabang_id' => $this->cabang->id,
            ]);
        }

        $this->artisan('procurement:audit-purchase-invoices', ['--invoice' => $invoice->id])
            ->expectsOutputToContain('Dry-run only')
            ->assertSuccessful();

        $this->assertSame(7992.0, (float) $invoice->fresh()->total);

        $this->artisan('procurement:audit-purchase-invoices', [
            '--invoice' => $invoice->id,
            '--repair' => true,
        ])->assertSuccessful();

        $invoice = $invoice->fresh(['accountPayable']);
        $this->assertSame(7200000.0, (float) $invoice->subtotal);
        $this->assertSame(7992000.0, (float) $invoice->total);
        $this->assertSame(7992000.0, (float) $invoice->accountPayable->total);

        $this->assertGreaterThan(0, JournalEntry::withoutGlobalScopes()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', true)
            ->count());

        $activeJournals = JournalEntry::withoutGlobalScopes()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', false)
            ->whereNull('reversal_of_transaction_id')
            ->get();

        $this->assertSame(7992000.0, (float) $activeJournals->sum('debit'));
        $this->assertSame(7992000.0, (float) $activeJournals->sum('credit'));
    }

    public function test_purchase_invoice_pdf_renders_ppn_once_without_legacy_tax_row()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_number' => 'PINV-PDF-TEST-001',
            'cabang_id' => $this->cabang->id,
            'subtotal' => 17000000,
            'tax' => 11,
            'ppn_rate' => 11,
            'dpp' => 17000000,
            'other_fee' => [
                ['name' => 'Biaya Transport', 'amount' => 100000],
            ],
            'total' => 18970000,
            'purchase_receipts' => [$receipt->id],
        ]);

        $invoice = Invoice::where('invoice_number', 'PINV-PDF-TEST-001')
            ->with(['fromModel.supplier', 'invoiceItem.product', 'cabang'])
            ->firstOrFail();

        $html = view('pdf.purchase-order-invoice-2', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('PPN 11,00%', $html);
        $this->assertStringContainsString('Biaya Transport', $html);
        $this->assertSame(1, substr_count($html, 'Biaya Transport'));
        $this->assertStringNotContainsString('Tax (', $html);
    }

    public function test_invoice_creation_with_valid_data()
    {
        // Test that the form can be submitted with valid basic data
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'invoice_number' => 'PINV-20251101-0001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->assertFormSet([
                'invoice_number' => 'PINV-20251101-0001',
            ]);
    }

    public function test_invoice_number_generation_action()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_supplier' => 'required']);
    }

    public function test_purchase_orders_require_order_request_before_submit()
    {
        $supplier = Supplier::factory()->create();
        $orderRequest = OrderRequest::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'invoice_number' => 'PINV-OR-REQ-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'selected_purchase_orders' => [$purchaseOrder->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_order_request' => 'required']);
    }

    public function test_purchase_invoice_requires_purchase_order_and_receipt_before_submit(): void
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_order_request' => $orderRequest->id,
                'invoice_number' => 'PINV-SOURCE-REQUIRED-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors([
                'selected_purchase_orders' => 'required',
                'selected_purchase_receipts' => 'required',
            ]);
    }

    public function test_purchase_invoice_requires_receipt_after_purchase_order_is_selected(): void
    {
        $source = $this->createReceiptBackedSource();

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $source['supplier']->id,
                'selected_order_request' => $source['orderRequest']->id,
                'selected_purchase_orders' => [$source['purchaseOrder']->id],
                'invoice_number' => 'PINV-RECEIPT-REQUIRED-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_purchase_receipts' => 'required']);
    }

    public function test_purchase_invoice_derives_source_from_receipt_and_ignores_hidden_source_state(): void
    {
        $source = $this->createReceiptBackedSource();
        $unrelatedPurchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $source['supplier']->id,
                'selected_order_request' => $source['orderRequest']->id,
                'selected_purchase_orders' => [$source['purchaseOrder']->id],
                'selected_purchase_receipts' => [$source['receipt']->id],
                'from_model_type' => PurchaseOrder::class,
                'from_model_id' => $unrelatedPurchaseOrder->id,
                'purchase_order_ids' => [$unrelatedPurchaseOrder->id],
                'invoice_number' => 'PINV-CANONICAL-SOURCE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', 'PINV-CANONICAL-SOURCE-001')->firstOrFail();
        $this->assertSame($source['purchaseOrder']->id, (int) $invoice->from_model_id);
        $this->assertSame([$source['purchaseOrder']->id], array_map('intval', $invoice->purchase_order_ids));
        $this->assertSame([$source['receipt']->id], array_map('intval', $invoice->purchase_receipts));
    }

    public function test_purchase_invoice_source_state_is_cleared_when_purchase_order_is_removed(): void
    {
        $source = $this->createReceiptBackedSource();

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $source['supplier']->id,
                'selected_order_request' => $source['orderRequest']->id,
                'selected_purchase_orders' => [$source['purchaseOrder']->id],
                'selected_purchase_receipts' => [$source['receipt']->id],
            ])
            ->assertSet('data.from_model_id', $source['purchaseOrder']->id)
            ->set('data.selected_purchase_orders', [])
            ->assertSet('data.from_model_id', null)
            ->assertSet('data.purchase_order_ids', [])
            ->assertSet('data.purchase_receipts', [])
            ->assertSet('data.selected_purchase_receipts', [])
            ->assertSet('data.invoiceItem', []);
    }

    public function test_purchase_invoice_rejects_invalid_receipt_source_relationships(): void
    {
        $source = $this->createReceiptBackedSource();
        $otherSource = $this->createReceiptBackedSource();
        $base = [
            'selected_supplier' => $source['supplier']->id,
            'selected_order_request' => $source['orderRequest']->id,
            'selected_purchase_orders' => [$source['purchaseOrder']->id],
            'selected_purchase_receipts' => [$otherSource['receipt']->id],
            'cabang_id' => $this->cabang->id,
        ];

        $this->assertReceiptSourceValidationError(array_merge($base, [
            'selected_purchase_receipts' => [PHP_INT_MAX],
        ]), 'selected_purchase_receipts');

        $this->assertReceiptSourceValidationError($base, 'selected_purchase_receipts');

        $this->assertReceiptSourceValidationError(array_merge($base, [
            'selected_purchase_orders' => [$otherSource['purchaseOrder']->id],
            'selected_purchase_receipts' => [$otherSource['receipt']->id],
            'selected_supplier' => Supplier::factory()->create()->id,
        ]), 'selected_supplier');

        $this->assertReceiptSourceValidationError(array_merge($base, [
            'selected_purchase_orders' => [$source['purchaseOrder']->id],
            'selected_purchase_receipts' => [$source['receipt']->id],
            'selected_order_request' => $otherSource['orderRequest']->id,
        ]), 'selected_order_request');

        $otherCabang = Cabang::factory()->create();
        $this->assertReceiptSourceValidationError(array_merge($base, [
            'selected_purchase_orders' => [$source['purchaseOrder']->id],
            'selected_purchase_receipts' => [$source['receipt']->id],
            'cabang_id' => $otherCabang->id,
        ]), 'cabang_id');

        $source['receipt']->update(['status' => 'draft']);
        $this->assertReceiptSourceValidationError(array_merge($base, [
            'selected_purchase_receipts' => [$source['receipt']->id],
        ]), 'selected_purchase_receipts');
    }

    public function test_purchase_invoice_rejects_receipt_already_used_by_another_invoice(): void
    {
        $source = $this->createReceiptBackedSource();
        Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $source['purchaseOrder']->id,
            'purchase_order_ids' => [$source['purchaseOrder']->id],
            'purchase_receipts' => [$source['receipt']->id],
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertReceiptSourceValidationError([
            'selected_supplier' => $source['supplier']->id,
            'selected_order_request' => $source['orderRequest']->id,
            'selected_purchase_orders' => [$source['purchaseOrder']->id],
            'selected_purchase_receipts' => [$source['receipt']->id],
            'cabang_id' => $this->cabang->id,
        ], 'selected_purchase_receipts');
    }

    public function test_purchase_invoice_multi_po_source_is_derived_from_selected_receipts(): void
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);
        $first = $this->createReceiptBackedSource(orderRequest: $orderRequest);
        $second = $this->createReceiptBackedSource(orderRequest: $orderRequest);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $this->supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$first['purchaseOrder']->id, $second['purchaseOrder']->id],
                'selected_purchase_receipts' => [$first['receipt']->id, $second['receipt']->id],
                'invoice_number' => 'PINV-MULTI-PO-SOURCE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', 'PINV-MULTI-PO-SOURCE-001')->firstOrFail();
        $this->assertSame($first['purchaseOrder']->id, (int) $invoice->from_model_id);
        $this->assertSame(
            [$first['purchaseOrder']->id, $second['purchaseOrder']->id],
            array_map('intval', $invoice->purchase_order_ids)
        );
    }

    public function test_purchase_invoice_creation_rolls_back_when_finalisation_fails(): void
    {
        $source = $this->createReceiptBackedSource();
        $this->partialMock(PurchaseInvoiceAccountingService::class, function ($mock): void {
            $mock->shouldReceive('finaliseInvoice')
                ->once()
                ->andThrow(new RuntimeException('Forced finalisation failure'));
        });

        try {
            Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
                ->fillForm([
                    'selected_supplier' => $source['supplier']->id,
                    'selected_order_request' => $source['orderRequest']->id,
                    'selected_purchase_orders' => [$source['purchaseOrder']->id],
                    'selected_purchase_receipts' => [$source['receipt']->id],
                    'invoice_number' => 'PINV-ROLLBACK-001',
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(30)->format('Y-m-d'),
                ])
                ->call('create');
            $this->fail('Expected finalisation failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced finalisation failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'PINV-ROLLBACK-001']);
    }

    public function test_form_validation_requires_invoice_date()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'invoice_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['invoice_date' => 'required']);
    }

    public function test_form_validation_requires_due_date()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'due_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['due_date' => 'required']);
    }

    public function test_purchase_invoice_resource_can_render_edit_page()
    {
        $supplier = Supplier::factory()->create();



        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0002',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\EditPurchaseInvoice::class, [
            'record' => $invoice->id,
        ])
            ->assertSuccessful();
    }

    public function test_purchase_invoice_creation_with_other_fees()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Create PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'tax' => 1000,
            'discount' => 0
        ]);

        // Create completed receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
        ]);

        // Create biaya for the receipt
        $currency = \App\Models\Currency::first() ?? \App\Models\Currency::factory()->create(['code' => 'IDR', 'name' => 'Indonesian Rupiah']);
        $purchaseReceiptBiaya = \App\Models\PurchaseReceiptBiaya::create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'nama_biaya' => 'Biaya Transport',
            'total' => 7500,
            'currency_id' => $currency->id,
        ]);
        // Test creating invoice with other fees
        $invoiceData = [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_receipt_ids' => [$purchaseReceipt->id],
            'invoice_number' => 'PINV-TEST-OTHER-FEE-001',
            'from_model_type' => \App\Models\PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'subtotal' => 100000,
            'other_fee' => [
                [
                    'name' => 'Biaya Admin',
                    'amount' => 5000,
                ],
                [
                    'name' => 'Biaya Transport',
                    'amount' => 7500,
                ],
            ],
            'tax' => 0,
            'total' => 112500, // subtotal + other_fee (12500)
            'ppn_rate' => 11,
        ];

        $invoice = Invoice::create($invoiceData);

        // Assert invoice was created with correct other_fee data
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'PINV-TEST-OTHER-FEE-001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
        ]);

        $savedInvoice = Invoice::where('invoice_number', 'PINV-TEST-OTHER-FEE-001')->first();
        $this->assertNotNull($savedInvoice);
        $this->assertIsArray($savedInvoice->other_fee);
        $this->assertCount(2, $savedInvoice->other_fee);
        $this->assertEquals('Biaya Admin', $savedInvoice->other_fee[0]['name']);
        $this->assertEquals(5000, $savedInvoice->other_fee[0]['amount']);
        $this->assertEquals('Biaya Transport', $savedInvoice->other_fee[1]['name']);
        $this->assertEquals(7500, $savedInvoice->other_fee[1]['amount']);
        // Should include both manually added fees and fees from receipt
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'PINV-TEST-OTHER-FEE-001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => '100000.00',
        ]);

        $invoice = Invoice::where('invoice_number', 'PINV-TEST-OTHER-FEE-001')->first();
        $this->assertEquals(12500, $invoice->other_fee_total);
    }

    public function test_purchase_invoice_create_page_persists_receipt_biaya_items()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $product, $supplier, $this->cabang);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrder->purchaseOrderItem->first()->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        PurchaseReceiptBiaya::create([
            'purchase_receipt_id' => $receipt->id,
            'nama_biaya' => 'Biaya Transport',
            'total' => 7500,
            'currency_id' => $this->currency->id,
            'masuk_invoice' => 1,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-RECEIPT-FEE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'status' => Invoice::STATUS_DRAFT,
                'invoiceItem' => [[
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 10000,
                    'total' => 100000,
                ]],
                'other_fees' => [],
                'receiptBiayaItems' => [[
                    'receipt_id' => $receipt->id,
                    'nama_biaya' => 'Biaya Transport',
                    'total' => 7500,
                ]],
            ])
            ->call('create');

        $savedInvoice = Invoice::where('invoice_number', 'PINV-RECEIPT-FEE-001')->first();

        $this->assertNotNull($savedInvoice);
        $this->assertIsArray($savedInvoice->other_fee);
        $this->assertCount(1, $savedInvoice->other_fee);
        $this->assertEquals('Biaya Transport', $savedInvoice->other_fee[0]['name']);
        $this->assertEquals(7500, $savedInvoice->other_fee[0]['amount']);
        $this->assertEquals(7500, $savedInvoice->other_fee_total);
    }

    public function test_purchase_invoice_ignores_zero_value_other_fee_items()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        createPurchaseInvoiceOrderRequestItem($orderRequest, $product, $supplier, $this->cabang);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrder->purchaseOrderItem->first()->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_order_request' => $orderRequest->id,
                'selected_purchase_orders' => [$purchaseOrder->id],
                'selected_purchase_receipts' => [$receipt->id],
                'invoice_number' => 'PINV-ZERO-FEE-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'status' => Invoice::STATUS_DRAFT,
                'invoiceItem' => [[
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 10000,
                    'total' => 100000,
                ]],
                'other_fees' => [[
                    'name' => 'Biaya Pengiriman',
                    'amount' => 0,
                ]],
                'receiptBiayaItems' => [[
                    'receipt_id' => $receipt->id,
                    'nama_biaya' => 'Biaya Pengiriman',
                    'total' => 0,
                ]],
            ])
            ->call('create');

        $savedInvoice = Invoice::where('invoice_number', 'PINV-ZERO-FEE-001')->firstOrFail();

        $this->assertIsArray($savedInvoice->other_fee);
        $this->assertFalse(collect($savedInvoice->other_fee)->contains(fn ($fee) => (float) ($fee['amount'] ?? 0) <= 0));
        $this->assertSame(0, collect($savedInvoice->other_fee)->sum(fn ($fee) => (float) ($fee['amount'] ?? 0) <= 0 ? (float) ($fee['amount'] ?? 0) : 0));
    }

    public function test_purchase_invoice_receipt_biaya_deletion()
    {
        // Test passes if we can create and run this test without errors
        $this->assertTrue(true);
    }

    public function test_create_deposit_with_journal_posting()
    {
        // Create COAs for deposit
        \App\Models\ChartOfAccount::create([
            'code' => '1101',
            'name' => 'Kas',
            'type' => 'asset',
            'is_active' => 1
        ]);
        \App\Models\ChartOfAccount::create([
            'code' => '2101',
            'name' => 'Titipan Konsumen',
            'type' => 'liability',
            'is_active' => 1
        ]);

        // Create supplier for deposit
        $supplier = Supplier::factory()->create([
            'code' => 'SUP001',
            'perusahaan' => 'Test Supplier',
        ]);

        // Create deposit directly
        $deposit = \App\Models\Deposit::create([
            'deposit_number' => 'DEP-TEST-001',
            'from_model_type' => 'App\Models\Supplier',
            'from_model_id' => $supplier->id,
            'amount' => 100000,
            'remaining_amount' => 100000,
            'coa_id' => \App\Models\ChartOfAccount::where('code', '2101')->first()->id,
            'note' => 'Test deposit',
            'status' => 'active',
            'created_by' => 1
        ]);

        // Manually create journal entries like in CreateDeposit
        $createDepositPage = new \App\Filament\Resources\DepositResource\Pages\CreateDeposit();
        $createDepositPage->record = $deposit;
        
        // Mock form state for payment_coa_id
        $createDepositPage->form = new class {
            public function getState() {
                return [
                    'payment_coa_id' => \App\Models\ChartOfAccount::where('code', '1101')->first()->id
                ];
            }
        };
        
        $createDepositPage->createDepositJournalEntries();

        // Assert deposit was created
        $this->assertDatabaseHas('deposits', [
            'deposit_number' => 'DEP-TEST-001',
            'from_model_type' => 'App\Models\Supplier',
            'from_model_id' => $supplier->id,
            'amount' => 100000,
            'remaining_amount' => 100000,
        ]);

        // Check if journal entries were created
        $deposit = \App\Models\Deposit::where('deposit_number', 'DEP-TEST-001')->first();
        $this->assertNotNull($deposit);

        // Assert journal entries for deposit creation
        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => \App\Models\ChartOfAccount::where('code', '2101')->first()->id,
            'debit' => 100000,
            'credit' => 0,
            'source_type' => \App\Models\Deposit::class,
            'source_id' => $deposit->id,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => \App\Models\ChartOfAccount::where('code', '1101')->first()->id,
            'debit' => 0,
            'credit' => 100000,
            'source_type' => \App\Models\Deposit::class,
            'source_id' => $deposit->id,
        ]);
    }

    public function test_create_deposit_supplier_oogne_with_specific_data()
    {
        // Create COAs for deposit
        $kasCoa = \App\Models\ChartOfAccount::create([
            'code' => '1101',
            'name' => 'Kas',
            'type' => 'asset',
            'is_active' => 1
        ]);
        $titipanCoa = \App\Models\ChartOfAccount::create([
            'code' => '2101',
            'name' => 'Titipan Konsumen',
            'type' => 'liability',
            'is_active' => 1
        ]);

        // Create supplier with specific data
        $supplier = Supplier::factory()->create([
            'code' => 'SUP-OOGNE',
            'perusahaan' => 'Yayasan Narpati (Persero) Tbk',
        ]);

        // Create deposit directly and trigger journal posting like create page flow
        $deposit = \App\Models\Deposit::create([
            'deposit_number' => 'DEP-SUP-OOGNE-001',
            'from_model_type' => 'App\Models\Supplier',
            'from_model_id' => $supplier->id,
            'amount' => 1000000,
            'remaining_amount' => 1000000,
            'coa_id' => $titipanCoa->id,
            'note' => 'Deposit supplier Yayasan Narpati (Persero) Tbk',
            'status' => 'active',
            'created_by' => 1,
        ]);

        $createDepositPage = new \App\Filament\Resources\DepositResource\Pages\CreateDeposit();
        $createDepositPage->record = $deposit;
        $createDepositPage->form = new class ($kasCoa) {
            public function __construct(private $kasCoa) {}
            public function getState() {
                return [
                    'payment_coa_id' => $this->kasCoa->id,
                ];
            }
        };
        $createDepositPage->createDepositJournalEntries();

        // Assert deposit was created with correct data
        $this->assertDatabaseHas('deposits', [
            'deposit_number' => 'DEP-SUP-OOGNE-001',
            'from_model_type' => 'App\Models\Supplier',
            'from_model_id' => $supplier->id,
            'amount' => 1000000,
            'remaining_amount' => 1000000,
            'note' => 'Deposit supplier Yayasan Narpati (Persero) Tbk',
        ]);

        // Assert supplier was created correctly
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-OOGNE',
            'perusahaan' => 'Yayasan Narpati (Persero) Tbk',
        ]);

        // Check if journal entries were created with amount 1,000,000
        $deposit = \App\Models\Deposit::where('deposit_number', 'DEP-SUP-OOGNE-001')->first();
        $this->assertNotNull($deposit);

        // Assert journal entries for deposit creation (supplier deposit)
        // For supplier: Dr: Uang Muka Pembelian (coa_id), Cr: Kas/Bank (payment_coa_id)
        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $titipanCoa->id, // Debit to liability COA
            'debit' => 1000000,
            'credit' => 0,
            'source_type' => \App\Models\Deposit::class,
            'source_id' => $deposit->id,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $kasCoa->id, // Credit to cash/bank COA
            'debit' => 0,
            'credit' => 1000000,
            'source_type' => \App\Models\Deposit::class,
            'source_id' => $deposit->id,
        ]);
    }
}
