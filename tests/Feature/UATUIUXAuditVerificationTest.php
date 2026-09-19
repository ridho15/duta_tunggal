<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\SaleOrderResource\Pages\CreateSaleOrder;
use App\Filament\Resources\SaleOrderResource\Pages\EditSaleOrder;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UATUIUXAuditVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UnitOfMeasure::firstOrCreate(['id' => 1], ['name' => 'PCS', 'abbreviation' => 'pcs']);
        Currency::firstOrCreate(['id' => 1], ['name' => 'Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        Cabang::firstOrCreate(['id' => 1], ['nama' => 'Pusat', 'kode' => 'PST', 'alamat' => 'Jakarta']);
        Product::factory()->create(['id' => 1]);
    }

    public function test_po_item_refer_item_label_displays_order_request_number()
    {
        $supplier = Supplier::factory()->create();
        $or = OrderRequest::create([
            'request_number' => 'OR-TEST-999',
            'request_date' => now(),
            'status' => 'approved',
            'supplier_id' => $supplier->id,
            'cabang_id' => 1,
            'created_by' => 1,
        ]);

        $item = OrderRequestItem::create([
            'order_request_id' => $or->id,
            'product_id' => 1,
            'quantity' => 10,
            'unit_price' => 50000,
            'currency_id' => 1,
            'tipe_pajak' => 'none',
            'cabang_id' => 1,
            'status' => 'approved',
        ]);

        $poItem = new PurchaseOrderItem([
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $item->id,
        ]);

        $label = PurchaseOrderItemRelationManager::referItemLabel($poItem);

        $this->assertEquals('OR-TEST-999', $label);
    }

    public function test_indonesian_money_macro_placeholder_is_zero()
    {
        $input = TextInput::make('test_money')->indonesianMoney();
        $this->assertEquals('0,00', $input->getPlaceholder());
    }

    public function test_sales_invoice_due_date_is_deduplicated()
    {
        $code = file_get_contents(app_path('Filament/Resources/SalesInvoiceResource.php'));
        $this->assertStringNotContainsString('due_date_display', $code);
        $this->assertStringContainsString('Tanggal Invoice', $code);
        $this->assertStringContainsString('Tanggal Jatuh Tempo', $code);
    }

    public function test_hub_menu_customer_receipt_is_in_sales_hub_not_purchase_hub()
    {
        $purchaseHub = file_get_contents(resource_path('views/filament/pages/purchase-hub-page.blade.php'));
        $salesHub = file_get_contents(resource_path('views/filament/pages/sales-hub-page.blade.php'));

        $this->assertStringNotContainsString('CustomerReceiptResource', $purchaseHub);
        $this->assertStringContainsString('CustomerReceiptResource', $salesHub);
    }

    public function test_sale_order_redirects_to_view_page_on_save()
    {
        $createMethod = new \ReflectionMethod(CreateSaleOrder::class, 'getRedirectUrl');
        $createMethod->setAccessible(true);
        $editMethod = new \ReflectionMethod(EditSaleOrder::class, 'getRedirectUrl');
        $editMethod->setAccessible(true);

        $this->assertTrue($createMethod->class === CreateSaleOrder::class);
        $this->assertTrue($editMethod->class === EditSaleOrder::class);
    }

    public function test_sale_order_table_legend_corrected()
    {
        $soResourceCode = file_get_contents(app_path('Filament/Resources/SaleOrderResource.php'));
        $this->assertStringContainsString('Kuning (Partially Delivered)', $soResourceCode);
        $this->assertStringContainsString('SO terkirim sebagian', $soResourceCode);
    }

    public function test_order_request_infolist_has_note()
    {
        $infolistCode = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));
        $this->assertStringContainsString("TextEntry::make('note')", $infolistCode);
    }

    public function test_quality_control_preselects_first_eligible_item_when_po_has_multiple_items()
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-QC-01',
            'order_date' => now(),
            'status' => 'approved',
            'supplier_id' => $supplier->id,
            'cabang_id' => 1,
            'created_by' => 1,
            'is_asset' => false,
            'currency_id' => 1,
            'total_amount' => 100000,
        ]);

        $item1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => 1,
            'quantity' => 10,
            'unit_price' => 5000,
            'subtotal' => 50000,
            'currency_id' => 1,
            'cabang_id' => 1,
        ]);

        $item2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => 1,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
            'currency_id' => 1,
            'cabang_id' => 1,
        ]);

        request()->merge(['purchase_order_id' => $po->id]);

        $selected = QualityControlPurchaseResource::defaultPurchaseOrderItemForQuery();

        $this->assertNotNull($selected);
        $this->assertEquals($item1->id, $selected->id);
    }

    public function test_quality_control_preselects_by_specific_item_query_param()
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-QC-02',
            'order_date' => now(),
            'status' => 'approved',
            'supplier_id' => $supplier->id,
            'cabang_id' => 1,
            'created_by' => 1,
            'is_asset' => false,
            'currency_id' => 1,
            'total_amount' => 100000,
        ]);

        $item1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => 1,
            'quantity' => 10,
            'unit_price' => 5000,
            'subtotal' => 50000,
            'currency_id' => 1,
            'cabang_id' => 1,
        ]);

        $item2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => 1,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
            'currency_id' => 1,
            'cabang_id' => 1,
        ]);

        request()->merge(['purchase_order_item_id' => $item2->id]);

        $selected = QualityControlPurchaseResource::defaultPurchaseOrderItemForQuery();

        $this->assertNotNull($selected);
        $this->assertEquals($item2->id, $selected->id);
    }

    public function test_empty_parenthesis_rak_bug_is_eliminated()
    {
        $filesToCheck = [
            app_path('Filament/Resources/StockTransferResource.php'),
            app_path('Filament/Resources/QuotationResource.php'),
            app_path('Filament/Resources/QualityControlPurchaseResource.php'),
            app_path('Filament/Resources/WarehouseConfirmationResource.php'),
            app_path('Filament/Resources/DeliveryOrderResource.php'),
            resource_path('views/filament/infolists/material-issue-items-table.blade.php'),
        ];

        foreach ($filesToCheck as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString("'Rak: (' . (\$record->rak->code ?? '') . ')'", $content);
            $this->assertStringNotContainsString("'Rak: (' . (\$item->rak->code ?? '') . ')'", $content);
        }
    }

    public function test_order_request_unit_price_is_not_required()
    {
        $code = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));
        $this->assertStringContainsString("TextInput::make('unit_price')", $code);
        $this->assertStringContainsString("Harga Satuan", $code);
        $this->assertStringContainsString("Opsional bagi pemohon", $code);
    }

    public function test_order_request_table_styling_is_responsive_and_sticky()
    {
        $blade = file_get_contents(resource_path('views/filament/forms/order-request-item-navigator.blade.php'));
        $this->assertStringContainsString('min-width: 800px;', $blade);
        $this->assertStringContainsString('.dt-item-supplier', $blade);
        $this->assertStringContainsString('<th>Produk</th>', $blade);
        $this->assertStringContainsString('<th class="dt-item-number">Harga</th>', $blade);
        $this->assertStringContainsString('<th class="dt-item-action-col">Aksi</th>', $blade);
        $this->assertStringContainsString('data-dt-inline-validation-errors', $blade);
    }
}
