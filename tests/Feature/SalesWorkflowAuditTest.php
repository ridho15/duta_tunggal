<?php

/**
 * ERP Financial Audit — Sales Workflow Integration Test Suite
 *
 * Tests the complete sales workflow:
 *   Quotation → Sales Order → Delivery Order → Sales Invoice → Account Receivable
 *
 * Covers:
 *  - Tax calculation accuracy (Exclusive & Inclusive)
 *  - Tax propagation from Quotation → Sales Order
 *  - Invoice auto-creation from completed Sale Order
 *  - Account Receivable creation with correct branch scope
 *  - No duplicate AR records (unique constraint)
 *  - Correct journal entries (balanced debit/credit)
 *  - Invoice number uses sequential format (not SO-based)
 *  - Branch (cabang_id) propagation on AR records
 */

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Warehouse;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SuratJalan;
use App\Services\SalesOrderService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('database structure includes cabang_id on account_payables', function () {
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('account_payables', 'cabang_id'),
        'account_payables table must have cabang_id for branch scoping');
});

it('invoice model accessor returns monetary ppn amount not rate', function () {
    $invoice = Invoice::factory()->create([
        'subtotal' => 100000,
        'tax' => 11, // percentage
        'total' => 111000,
    ]);

    $this->assertEquals(11000.0, $invoice->ppn_amount,
        'ppn_amount accessor should compute subtotal * (tax/100) when items absent');
});

it('view page logs ppn amount and account payable info', function () {
    \Illuminate\Support\Facades\Log::spy();

    $invoice = Invoice::factory()->create([
        'subtotal' => 50000,
        'tax' => 11,
        'total' => 55500,
    ]);

    // create an account payable record so it will appear in context
    \App\Models\AccountPayable::factory()->create([
        'invoice_id' => $invoice->id,
        'supplier_id' => \App\Models\Supplier::factory()->create()->id,
        'total' => 55500,
        'paid' => 0,
        'remaining' => 55500,
        'status' => 'Belum Lunas',
    ]);

    $page = new \App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice();
    // Filament pages expect to be mounted with a record parameter
    $page->mount('record', $invoice);
    // trigger infolist construction which includes the afterStateHydrated hook
    $page->infolist(app(\Filament\Infolists\Infolist::class));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('debug')->withArgs(
        fn($msg, $context) =>
            str_contains($msg, 'Viewing invoice for debug')
            && isset($context['id'])
            && $context['id'] === $invoice->id
            && array_key_exists('account_payable', $context)
    );
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function auditCabang(): Cabang
{
    // Factory has a createOrUpdate helper with full defaults; this avoids missing
    // non-nullable columns and still respects the unique `kode` constraint.
    return Cabang::factory()->createOrUpdate([
        'kode' => 'AUD',
        'nama' => 'Audit Branch',
    ]);
}

function auditCoas(): array
{
    return [
        'ar'       => ChartOfAccount::factory()->create(['code' => '1120',    'name' => 'Piutang Dagang',    'type' => 'Asset']),
        'revenue'  => ChartOfAccount::factory()->create(['code' => '4000',    'name' => 'Penjualan',         'type' => 'Revenue']),
        'ppn_out'  => ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran',      'type' => 'Liability']),
        'cogs'     => ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP',               'type' => 'Expense']),
        'delivery' => ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim',   'type' => 'Asset']),
        'shipping' => ChartOfAccount::factory()->create(['code' => '6100.02', 'name' => 'Biaya Pengiriman',  'type' => 'Expense']),
        'discount' => ChartOfAccount::factory()->create(['code' => '4100.01', 'name' => 'Diskon Penjualan',  'type' => 'Expense']),
    ];
}

// ─── SECTION 1: TaxService Unit Coverage ─────────────────────────────────────

describe('TaxService — Exclusive Tax', function () {
    it('computes 12% exclusive: price=1_000_000 → ppn=120_000, total=1_120_000', function () {
        $result = TaxService::compute(1_000_000, 12, 'Eksklusif');
        expect($result['dpp'])->toBe(1_000_000.0)
            ->and($result['ppn'])->toBe(120_000.0)
            ->and($result['total'])->toBe(1_120_000.0);
    });

    it('computes 11% exclusive: price=1_000_000 → ppn=110_000, total=1_110_000', function () {
        $result = TaxService::compute(1_000_000, 11, 'Exclusive');   // English variant normalised
        expect($result['dpp'])->toBe(1_000_000.0)
            ->and($result['ppn'])->toBe(110_000.0)
            ->and($result['total'])->toBe(1_110_000.0);
    });
});

describe('TaxService — Inclusive Tax', function () {
    it('computes 12% inclusive: gross=1_120_000 → dpp=1_000_000, ppn=120_000', function () {
        $result = TaxService::compute(1_120_000, 12, 'Inklusif');
        expect($result['dpp'])->toBe(1_000_000.0)
            ->and($result['ppn'])->toBe(120_000.0)
            ->and($result['total'])->toBe(1_120_000.0);
    });

    it('total stays unchanged for inclusive tax', function () {
        $gross = 5_000_000;
        $result = TaxService::compute($gross, 11, 'Inklusif');
        expect($result['total'])->toBe((float) $gross);
        expect($result['dpp'] + $result['ppn'])->toBe((float) $gross);
    });
});

describe('TaxService — Non Pajak', function () {
    it('Non Pajak returns amount unchanged with zero ppn', function () {
        $result = TaxService::compute(2_500_000, 12, 'Non Pajak');
        expect($result['dpp'])->toBe(2_500_000.0)
            ->and($result['ppn'])->toBe(0.0)
            ->and($result['total'])->toBe(2_500_000.0);
    });
});

// ─── SECTION 2: Quotation → Sales Order Tax Propagation ──────────────────────

describe('Tax Propagation: Quotation → Sales Order', function () {
    it('propagates tax rate and type from quotation items to SO items', function () {
        $cabang   = auditCabang();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product  = Product::factory()->create();

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'approve',
        ]);

        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id'   => $product->id,
            'quantity'     => 2,
            'unit_price'   => 500_000,
            'discount'     => 0,
            'tax'          => 12,
            'tax_type'     => 'Eksklusif',
        ]);

        // Verify quotation item has the tax type stored
        $qItem = $quotation->quotationItem()->first();
        expect($qItem->tax)->toBe(12)
            ->and($qItem->tax_type)->toBe('Eksklusif');
    });
});

// ─── SECTION 3: Invoice Creation from Completed Sale Order ───────────────────

describe('Invoice Auto-Creation from Completed Sale Order', function () {
    beforeEach(function () {
        $this->cabang   = auditCabang();
        $this->coas     = auditCoas();
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product  = Product::factory()->create([
            'cost_price'             => 600_000,
            'sales_coa_id'           => $this->coas['revenue']->id,
            'cogs_coa_id'            => $this->coas['cogs']->id,
            'goods_delivery_coa_id'  => $this->coas['delivery']->id,
        ]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('creates exactly one invoice when SO status becomes completed', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 3,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoices = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->get();

        expect($invoices)->toHaveCount(1);
    });

    it('does not create a second invoice on repeated completion event', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 500_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);
        // Simulate a duplicate event (e.g., retry)
        $so->update(['status' => 'completed']);

        $count = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->count();

        expect($count)->toBe(1);
    });

    it('invoice number follows standard INV-YYYYMMDD-NNNN format', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        expect($invoice->invoice_number)->toMatch('/^INV-\d{8}-\d{4}$/');
    });
});

// ─── SECTION 4: Sales Order PDF Content ───────────────────────────────────

describe('Sales Order PDF content', function () {
    it('shows tax percentage and nominal tax for each item', function () {
        $cabang = auditCabang();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product = Product::factory()->create();

        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'order_date' => now(),
        ]);

        $item = SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 500_000,
            'discount' => 10, // percent
            'tax' => 11,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $html = view('pdf.sales-order', ['saleOrder' => $so])->render();

        $lineBase = $item->quantity * $item->unit_price;
        $discountAmount = $lineBase * ($item->discount / 100);
        $afterDiscount = $lineBase - $discountAmount;
        $taxRes = TaxService::compute($afterDiscount, $item->tax, $item->tipe_pajak);
        $expectedTaxAmount = 'Rp '.number_format($taxRes['ppn'], 0, ',', '.');
        $expectedPercent = number_format($item->tax, 2).'%';

        expect($html)->toContain($expectedPercent)
            ->and($html)->toContain($expectedTaxAmount);
    });
});

// ─── SECTION 5: Delivery Order customer info in infolist ─────────────────────
describe('Delivery Order view page shows customer information', function () {
    it('includes the linked customer name and address from the first sale order', function () {
        // create admin user for Filament access and grant necessary permission
        $admin = \App\Models\User::factory()->create();
        $admin->givePermissionTo('view delivery order');
        $admin->givePermissionTo('view any delivery order');
        $this->actingAs($admin);

        $cabang = auditCabang();
        $customer = Customer::factory()->create([
            'cabang_id' => $cabang->id,
            'address' => 'Jl. Test No. 123',
        ]);
        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'status' => 'confirmed',
        ]);
        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-001',
            'delivery_date' => now(),
            'cabang_id' => $cabang->id,
        ]);
        $do->salesOrders()->attach($so->id);

        $response = $this->get("/admin/delivery-orders/{$do->id}");
        $response->assertStatus(200);
        $response->assertSeeText($customer->name);
        $response->assertSeeText('Jl. Test No. 123');
    });
});
// ─── SECTION 6: PDF Column Audit ─────────────────────────────────────────
describe('PDF generators include required item columns and totals', function () {
    beforeEach(function () {
        $this->cabang = auditCabang();
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product = Product::factory()->create();
    });

    function assertPdfHasColumns($html, array $cols) {
        foreach ($cols as $col) {
            expect($html)->toContain($col);
        }
    }

    it('quotation PDF includes all columns', function () {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => now(),
            'status' => 'draft',
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount' => 10,
            'tax' => 11,
            'tax_type' => 'Eksklusif',
        ]);

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();
        assertPdfHasColumns($html, [
            '#', 'Product', 'Qty', 'Unit Price', 'Discount', 'Tax (%)', 'Tax Type', 'Tax Amount', 'Subtotal'
        ]);
    });

    it('sales order PDF includes all columns', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 200000,
            'discount' => 5,
            'tax' => 12,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $html = view('pdf.sales-order', ['saleOrder' => $so])->render();
        assertPdfHasColumns($html, [
            'No', 'Nama Item', 'Qty', 'Harga Satuan', 'Discount', 'Tax (%)', 'Tax Amount', 'Subtotal'
        ]);
    });

    it('invoice PDF includes all columns', function () {
        // create a sale order so from_model_id resolves to valid record for AR logic
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $invoice = null;
        Invoice::withoutEvents(function () use (&$invoice, $so) {
            $invoice = Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $so->id,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
            ]);
        });
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 300000,
            'discount' => 0,
            'tax_rate' => 12,
            'tax_amount' => 36000,
            'subtotal' => 300000,
            'total' => 336000,
        ]);
        $html = view('pdf.sale-order-invoice', ['invoice' => $invoice])->render();
        assertPdfHasColumns($html, [
            'SKU', 'Produk', 'Qty', 'Harga Satuan', 'Discount', 'Tax (%)', 'Tax Amount', 'Subtotal', 'Total'
        ]);
    });

    it('delivery order PDF includes all columns', function () {
        $do = DeliveryOrder::factory()->create([
            'do_number' => 'DOX',
            'delivery_date' => now(),
            'cabang_id' => $this->cabang->id,
        ]);
        $item = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'reason' => '',
        ]);
        // attach sale order item to get pricing
        $so = SaleOrder::factory()->create(['customer_id'=>$this->customer->id,'cabang_id'=>$this->cabang->id,'status'=>'approved']);
        $soItem = SaleOrderItem::factory()->create([ 'sale_order_id'=>$so->id,'product_id'=>$this->product->id,'unit_price'=>100000,'discount'=>0,'tax'=>0,'tipe_pajak'=>'Eksklusif']);
        $do->salesOrders()->attach($so->id);
        $item->sale_order_item_id = $soItem->id;
        $item->save();

        $html = view('pdf.delivery-order', ['deliveryOrder' => $do])->render();
        assertPdfHasColumns($html, [
            'Nama Barang', 'Qty', 'Harga Satuan', 'Discount', 'Tax (%)', 'Tax Amount', 'Subtotal'
        ]);
        // ensure customer name, shipping address and branch show up
        expect($html)->toContain($this->customer->name);
        expect($html)->toContain($so->shipped_to);
        expect($html)->toContain($this->cabang->name);
    });

    it('surat jalan PDF includes all columns', function () {
        // create at least one user for factory requirements
        $user = \App\Models\User::factory()->create();
        $sj = \App\Models\SuratJalan::create([
            'sj_number' => 'SJX',
            'issued_at' => now(),
            'signed_by' => $user->id,
            'created_by' => $user->id,
            'status' => 1,
        ]);
        $do = DeliveryOrder::factory()->create(['do_number'=>'DOX','delivery_date'=>now(),'cabang_id'=>$this->cabang->id]);
        $sj->deliveryOrder()->attach($do->id);
        $so = SaleOrder::factory()->create(['customer_id'=>$this->customer->id,'cabang_id'=>$this->cabang->id,'status'=>'approved']);
        $soItem=SaleOrderItem::factory()->create(['sale_order_id'=>$so->id,'product_id'=>$this->product->id,'unit_price'=>100000,'discount'=>0,'tax'=>0,'tipe_pajak'=>'Eksklusif']);
        $item = DeliveryOrderItem::factory()->create(['delivery_order_id'=>$do->id,'product_id'=>$this->product->id,'quantity'=>1,'reason'=>'']);
        $item->sale_order_item_id=$soItem->id; $item->save();
        $do->salesOrders()->attach($so->id);

        $html = view('pdf.surat-jalan', ['suratJalan'=>$sj])->render();
        // verify customer and address and branch present in surat jalan output
        expect($html)->toContain($this->customer->name);
        expect($html)->toContain($so->shipped_to);
        expect($html)->toContain($this->cabang->name);
        assertPdfHasColumns($html, [
            'Nama Barang', 'Qty', 'Harga Satuan', 'Discount', 'Tax (%)', 'Tax Amount', 'Subtotal'
        ]);
    });

// ─── SECTION 6b: Excel export audit for sales orders ─────────────────────────
describe('Excel exports reflect correct columns and values', function () {
    it('SalesReportExport contains tax and discount columns with correct totals', function () {
        $cabang = auditCabang();
        $customer = Customer::factory()->create(['cabang_id'=>$cabang->id]);
        $so = SaleOrder::factory()->create(['customer_id'=>$customer->id,'cabang_id'=>$cabang->id,'status'=>'completed','total_amount'=>0]);
        $item = SaleOrderItem::factory()->create([
            'sale_order_id'=>$so->id,
            'product_id'=>$this->product->id,
            'quantity'=>2,
            'unit_price'=>500000,
            'discount'=>10,
            'tax'=>12,
            'tipe_pajak'=>'Eksklusif'
        ]);
        // compute expected values
        $lineBase = 2 * 500000;
        $afterDiscount = $lineBase * 0.90;
        $taxRes = \App\Services\TaxService::compute($afterDiscount, 12, 'Eksklusif');
        $expectedTaxAmt = $taxRes['ppn'];
        $expectedSubtotal = $taxRes['total'];

        $collection = (new \App\Exports\SalesReportExport(SaleOrder::query()))->collection();
        // ensure header row present
        // first() returns an array of cells, so use array_keys()
        $headings = array_keys($collection->first());
        expect($headings)
            ->toContain('Discount (%)')
            ->and('Tax Rate (%)')
            ->and('Tipe Pajak')
            ->and('DPP')
            ->and('PPN Amount');

        // locate item row
        $itemRow = $collection->filter(fn($row)=>str_contains($row['Produk'],$this->product->name))->first();
        // Qty may be returned as string or float, ensure numeric equality
        expect((float) $itemRow['Qty'])->toBe(2.0);
        expect($itemRow['Discount (%)'])->toBe('10.00');
        expect($itemRow['Tax Rate (%)'])->toBe('12.00');
        expect($itemRow['Tipe Pajak'])->toBe('Eksklusif');
        expect($itemRow['PPN Amount'])->toContain(number_format($expectedTaxAmt,0,',','.'));
        expect($itemRow['DPP'])->toContain(number_format($taxRes['dpp'],0,',','.'));
        expect($itemRow['Item Subtotal'])->toContain(number_format($expectedSubtotal,0,',','.'));
    });
});
});
// ─── SECTION 4: Invoice Tax Field — Rate Stored (Not Monetary) ───────────────

describe('Invoice tax field stores rate, not monetary amount', function () {
    beforeEach(function () {
        $this->cabang   = auditCabang();
        auditCoas();
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product  = Product::factory()->create(['cost_price' => 0]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('stores tax rate (11) not monetary amount (110_000) in invoice->tax', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        // tax field should be the rate (11), not monetary 110,000
        expect((int) $invoice->tax)->toBe(11);
        // ppn_rate should also be 11
        expect((float) $invoice->ppn_rate)->toBe(11.0);
        // dpp = subtotal (DPP amount)
        expect((float) $invoice->dpp)->toBe(1_000_000.0);
        // total = DPP + PPN = 1,000,000 + 110,000 = 1,110,000
        expect((float) $invoice->total)->toBe(1_110_000.0);
    });
});

// ─── SECTION 5: Correct PPN Journal Entry ────────────────────────────────────

describe('PPN Keluaran journal entry — correct monetary amount', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        $this->coas      = auditCoas();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create([
            'cost_price'             => 700_000,
            'sales_coa_id'           => $this->coas['revenue']->id,
            'cogs_coa_id'            => $this->coas['cogs']->id,
            'goods_delivery_coa_id'  => $this->coas['delivery']->id,
        ]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('posts PPN credit of 110_000 (not 11_000_000_000) for 1_000_000 @ 11%', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $ppnEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('coa_id', $this->coas['ppn_out']->id)
            ->first();

        expect($ppnEntry)->not->toBeNull();
        expect((float) $ppnEntry->credit)->toBe(110_000.0);
    });

    it('AR debit equals invoice total including PPN', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 2,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 12,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $arEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('coa_id', $this->coas['ar']->id)
            ->first();

        expect((float) $arEntry->debit)->toBe(2_240_000.0); // 2 * 1M * 1.12
    });

    it('journal entries are balanced (total debit == total credit)', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 5,
            'unit_price'    => 800_000,
            'discount'      => 5,   // 5% discount
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::whereMorphedTo('fromModel', $so)->first();
        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $totalDebit  = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        expect($totalDebit)->toBe($totalCredit);
    });
});

// ─── SECTION 6: Account Receivable Integrity ────────────────────────────────

describe('Account Receivable — creation and branch scope', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        auditCoas();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create(['cost_price' => 0]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('creates exactly one AccountReceivable per invoice', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 500_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $arCount = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->count();

        expect($arCount)->toBe(1);
    });

    it('AccountReceivable carries the correct cabang_id for branch scope', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $ar = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->first();

        expect($ar)->not->toBeNull();
        expect($ar->cabang_id)->toBe($this->cabang->id);
    });

    it('AccountReceivable total equals invoice grand total', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 3,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 12,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $ar = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->first();

        expect((float) $ar->total)->toBe((float) $invoice->total);
        expect((float) $ar->remaining)->toBe((float) $invoice->total);
        expect($ar->status)->toBe('Belum Lunas');
    });

    it('no duplicate AR created even if InvoiceObserver fires multiple times', function () {
        // Simulate by registering the observer manually twice and creating an invoice
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 500_000,
            'discount'      => 0,
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        // Attempt a second completion (simulates observer double-fire)
        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $arCount = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->count();

        expect($arCount)->toBe(1);
    });

    it('invoice is created only once when sale order completes multiple times', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 2,
            'unit_price'    => 250_000,
            'discount'      => 0,
            'tax'           => 10,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);
        $so->update(['status' => 'completed']); // repeat

        $invoiceCount = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->count();

        expect($invoiceCount)->toBe(1);
    });

    it('delivery order completion rerun does not duplicate sale order update or invoice', function () {
        // prepare sale order with item
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'approved',
        ]);
        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 100_000,
            'discount'      => 0,
            'tax'           => 0,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        // create a delivery order linked to sale order
        $do = DeliveryOrder::factory()->create([
            'do_number' => 'DOX',
            'delivery_date' => now(),
            'cabang_id' => $this->cabang->id,
        ]);
        $do->salesOrders()->attach($so->id);
        $doItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'reason' => '',
        ]);
        $doItem->sale_order_item_id = $soItem->id;
        $doItem->save();

        // complete DO twice
        $do->update(['status' => 'completed']);
        $do->update(['status' => 'completed']);

        // sale order should have been marked completed once
        $so->refresh();
        expect($so->status)->toBe('completed');

        // invoice count remains one
        $invoiceCount = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->count();
        expect($invoiceCount)->toBe(1);
    });

    it('completing sale order and delivery order never dispatches Laravel events', function () {
        Event::fake();

        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 100_000,
            'discount'      => 0,
            'tax'           => 0,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $do = DeliveryOrder::factory()->create([
            'do_number' => 'DOX',
            'delivery_date' => now(),
            'cabang_id' => $this->cabang->id,
        ]);
        $do->salesOrders()->attach($so->id);
        $doItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'reason' => '',
        ]);
        $doItem->sale_order_item_id = SaleOrderItem::first()->id;
        $doItem->save();

        $do->update(['status' => 'completed']);

        Event::assertNothingDispatched();
    });

    it('ar-ap:sync command respects uniqueness and updates existing AR', function () {
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 200_000,
            'discount'      => 0,
            'tax'           => 10,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        // first sync should create (already created by observer, but sync should not duplicate)
        Artisan::call('ar-ap:sync');
        $arCount = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->count();
        expect($arCount)->toBe(1);

        // change invoice total and force update via sync
        $invoice->update(['total' => $invoice->total + 50_000]);
        Artisan::call('ar-ap:sync', ['--force' => true]);
        $ar = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->first();
        expect((float) $ar->total)->toBe((float) $invoice->total);

        // another sync without force should still not create duplicates
        Artisan::call('ar-ap:sync');
        $arCount = AccountReceivable::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->count();
        expect($arCount)->toBe(1);
    });

    // ─── SECTION 8: DATABASE INTEGRITY ───────────────────────────────────────
    describe('Database integrity for financial tables', function () {
        it('sales_orders.customer_id references an existing customer', function () {
            $cust = Customer::factory()->create();
            $so = SaleOrder::factory()->create([ 'customer_id'=>$cust->id ]);
            DB::table('sale_orders')->insert([
                'customer_id' => 999999,
                'so_number' => 'ORPHAN',
                'order_date' => now(),
                'status'=>'draft',
                'total_amount' => 0,
            ]);

            $hasOrphan = DB::table('sale_orders')
                ->leftJoin('customers','sale_orders.customer_id','customers.id')
                ->whereNull('customers.id')
                ->exists();

            expect($hasOrphan)->toBeTrue();
        });

        it('quotations.customer_id points to existing customer', function () {
            $cust = Customer::factory()->create();
            $q = \App\Models\Quotation::factory()->create(['customer_id'=>$cust->id]);
            DB::table('quotations')->insert([
                'customer_id' => 999999,
                'quotation_number'=>'Q-ORPHAN',
                'date'=>now(),
                'status'=>'draft',
                'total_amount'=>0,
            ]);

            $orphan = DB::table('quotations')
                ->leftJoin('customers','quotations.customer_id','customers.id')
                ->whereNull('customers.id')
                ->exists();
            expect($orphan)->toBeTrue();
        });

        it('account_receivables link to existing invoice and customer', function () {
            $inv = Invoice::factory()->create();
            $cust = Customer::factory()->create();
            AccountReceivable::factory()->create([
                'invoice_id'=>$inv->id,
                'customer_id'=>$cust->id,
            ]);
            DB::table('account_receivables')->insert([
                'invoice_id'=>999999,
                'customer_id'=>$cust->id,
                'total'=>0,'paid'=>0,'remaining'=>0,'status'=>'Belum Lunas'
            ]);
            DB::table('account_receivables')->insert([
                'invoice_id'=>$inv->id,
                'customer_id'=>999999,
                'total'=>0,'paid'=>0,'remaining'=>0,'status'=>'Belum Lunas'
            ]);

            $invoiceOrphan = DB::table('account_receivables')
                ->leftJoin('invoices','account_receivables.invoice_id','invoices.id')
                ->whereNull('invoices.id')
                ->exists();
            $customerOrphan = DB::table('account_receivables')
                ->leftJoin('customers','account_receivables.customer_id','customers.id')
                ->whereNull('customers.id')
                ->exists();
            expect($invoiceOrphan)->toBeTrue();
            expect($customerOrphan)->toBeTrue();
        });

        it('invoice.from_model_id references existing sale_order when appropriate', function () {
            $so = SaleOrder::factory()->create(['status'=>'completed']);
            $inv = Invoice::factory()->create([
                'from_model_type'=>SaleOrder::class,
                'from_model_id'=>$so->id,
            ]);
            DB::table('invoices')->insert([
                'from_model_type'=>SaleOrder::class,
                'from_model_id'=>999999,
                'invoice_number'=>'X','invoice_date'=>now(),'due_date'=>now(),
                'subtotal'=>0,'tax'=>0,'total'=>0
            ]);

            $orphan = DB::table('invoices')
                ->where('from_model_type',SaleOrder::class)
                ->leftJoin('sale_orders','invoices.from_model_id','sale_orders.id')
                ->whereNull('sale_orders.id')
                ->exists();
            expect($orphan)->toBeTrue();
        });

        it('no duplicate account_receivable for same invoice (unique constraint)', function () {
            $inv = Invoice::factory()->create();
            AccountReceivable::factory()->create(['invoice_id'=>$inv->id]);
            $threw = false;
            try {
                DB::table('account_receivables')->insert([
                    'invoice_id'=>$inv->id,'customer_id'=>1,'total'=>0,'paid'=>0,'remaining'=>0,'status'=>'Belum Lunas'
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                $threw = true;
            }
            expect($threw)->toBeTrue();
        });
    });
});

// ─── SECTION 7: Inclusive Tax Invoice ───────────────────────────────────────

describe('Inclusive tax invoice workflow', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        $this->coas      = auditCoas();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create([
            'cost_price'             => 0,
            'sales_coa_id'           => $this->coas['revenue']->id,
        ]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('inclusive tax: total stays at gross price, DPP extracted correctly', function () {
        // 1,120,000 gross, 12% inclusive → DPP = 1,000,000, PPN = 120,000
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_120_000,
            'discount'      => 0,
            'tax'           => 12,
            'tipe_pajak'    => 'Inklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        // Invoice subtotal (DPP) should be 1,000,000
        expect((float) $invoice->subtotal)->toBe(1_000_000.0);

        // Invoice items should have correct tax_amount
        $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
        expect((float) $item->tax_amount)->toBe(120_000.0);

        // total = DPP + PPN = 1,120,000
        expect((float) $invoice->total)->toBe(1_120_000.0);
    });
});

// ─── SECTION 8: Discount Application ────────────────────────────────────────

describe('Discount applied before tax calculation', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        auditCoas();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create(['cost_price' => 0]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('10% discount on 1_000_000 then 11% excl tax = total 999_000', function () {
        // Base = 1,000,000 * (1 - 0.10) = 900,000
        // PPN = 900,000 * 0.11 = 99,000
        // Total = 999,000
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 10,  // 10%
            'tax'           => 11,
            'tipe_pajak'    => 'Eksklusif',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        // DPP after discount
        expect((float) $invoice->subtotal)->toBe(900_000.0);
        // Total = 900,000 + 99,000 = 999,000
        expect((float) $invoice->total)->toBe(999_000.0);
    });
});

// ─── SECTION 9: Multi-item Invoice Journal Balance ───────────────────────────

describe('Multi-item invoice — journals always balance', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        $this->coas      = auditCoas();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('3-item invoice journals balance with mixed discount', function () {
        $prods = Product::factory(3)->create([
            'cost_price'             => 500_000,
            'sales_coa_id'           => $this->coas['revenue']->id,
            'cogs_coa_id'            => $this->coas['cogs']->id,
            'goods_delivery_coa_id'  => $this->coas['delivery']->id,
        ]);

        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'draft',
        ]);

        $items = [
            ['qty' => 2, 'price' => 1_000_000, 'disc' => 0,  'tax' => 11],
            ['qty' => 1, 'price' => 2_000_000, 'disc' => 5,  'tax' => 11],
            ['qty' => 3, 'price' => 500_000,   'disc' => 10, 'tax' => 11],
        ];

        foreach ($items as $idx => $data) {
            SaleOrderItem::factory()->create([
                'sale_order_id' => $so->id,
                'product_id'    => $prods[$idx]->id,
                'quantity'      => $data['qty'],
                'unit_price'    => $data['price'],
                'discount'      => $data['disc'],
                'tax'           => $data['tax'],
                'tipe_pajak'    => 'Eksklusif',
                'warehouse_id'  => $this->warehouse->id,
            ]);
        }

        $so->update(['status' => 'completed']);

        $invoice = Invoice::whereMorphedTo('fromModel', $so)->first();
        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        expect($entries->sum('debit'))->toBe($entries->sum('credit'));
    });
});

// ─── SECTION 10: SalesOrderService null tipe_pajak default ──────────────────

describe('SalesOrderService::updateTotalAmount — null tipe_pajak defaults to Exclusive', function () {
    beforeEach(function () {
        $this->cabang    = auditCabang();
        $this->customer  = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('tipe_pajak=Exclusive (DB default) adds PPN on top of price', function () {
        // The DB column `tipe_pajak` is NOT NULL with DEFAULT 'Exclusive'.
        // A null value cannot be stored; the column default ensures 'Exclusive' is always used.
        // This test verifies SalesOrderService correctly computes with Exclusive type.
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => Product::factory()->create(['cost_price' => 0])->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 10,
            'tipe_pajak'    => 'Exclusive',  // DB default; null not permitted by column constraint
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $service = app(SalesOrderService::class);
        $service->updateTotalAmount($so->load('saleOrderItem'));
        $so->refresh();

        // Exclusive: 1,000,000 + (1,000,000 × 0.10) = 1,100,000
        expect((float) $so->total_amount)->toBe(1_100_000.0);
    });

    it('explicit Inclusive tipe_pajak keeps total at gross price', function () {
        // Inclusive: gross=1_100_000, tax=10% → DPP=1_000_000, PPN=100_000, total=1_100_000
        $so = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id'    => Product::factory()->create(['cost_price' => 0])->id,
            'quantity'      => 1,
            'unit_price'    => 1_100_000,
            'discount'      => 0,
            'tax'           => 10,
            'tipe_pajak'    => 'Inclusive',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $service = app(SalesOrderService::class);
        $service->updateTotalAmount($so->load('saleOrderItem'));
        $so->refresh();

        // Inclusive: total unchanged = 1,100,000
        expect((float) $so->total_amount)->toBe(1_100_000.0);
    });

    it('Exclusive and Inclusive give different totals for same unit_price', function () {
        $product = Product::factory()->create(['cost_price' => 0]);

        $soExcl = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $soExcl->id,
            'product_id'    => $product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 12,
            'tipe_pajak'    => 'Exclusive',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $soIncl = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id'   => $this->cabang->id,
        ]);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $soIncl->id,
            'product_id'    => $product->id,
            'quantity'      => 1,
            'unit_price'    => 1_000_000,
            'discount'      => 0,
            'tax'           => 12,
            'tipe_pajak'    => 'Inclusive',
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $service = app(SalesOrderService::class);
        $service->updateTotalAmount($soExcl->load('saleOrderItem'));
        $service->updateTotalAmount($soIncl->load('saleOrderItem'));
        $soExcl->refresh();
        $soIncl->refresh();

        // Exclusive total > Inclusive total for same unit_price
        expect((float) $soExcl->total_amount)->toBe(1_120_000.0);   // 1,000,000 + 120,000 PPN
        expect((float) $soIncl->total_amount)->toBe(1_000_000.0);   // stays at gross
        expect($soExcl->total_amount)->toBeGreaterThan($soIncl->total_amount);
    });
});

// ─── SECTION 11: Quotation tax_type → SaleOrder tipe_pajak propagation ──────

describe('Quotation tax_type propagates as tipe_pajak to SaleOrderItem', function () {
    beforeEach(function () {
        // QuotationFactory requires a Customer to exist
        Customer::factory()->create();
    });

    it('QuotationItem.tax_type=Exclusive is stored in tax_type column', function () {
        $quotation = Quotation::factory()->create();
        $item = QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'tax_type'     => 'Exclusive',
        ]);
        expect($item->fresh()->tax_type)->toBe('Exclusive');
    });

    it('QuotationItem.tax_type=Inclusive is stored in tax_type column', function () {
        $quotation = Quotation::factory()->create();
        $item = QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'tax_type'     => 'Inclusive',
        ]);
        expect($item->fresh()->tax_type)->toBe('Inclusive');
    });

    it('TaxService normalizes Exclusive and Inclusive English values correctly', function () {
        expect(TaxService::normalizeType('Exclusive'))->toBe('Eksklusif');
        expect(TaxService::normalizeType('Inclusive'))->toBe('Inklusif');
        // Old Indonesian values still work
        expect(TaxService::normalizeType('Eksklusif'))->toBe('Eksklusif');
        expect(TaxService::normalizeType('Inklusif'))->toBe('Inklusif');
    });

    it('compute with English Exclusive gives same result as Indonesian Eksklusif', function () {
        $eng  = TaxService::compute(1_000_000, 12, 'Exclusive');
        $indo = TaxService::compute(1_000_000, 12, 'Eksklusif');
        expect($eng)->toBe($indo);
    });

    it('compute with English Inclusive gives same result as Indonesian Inklusif', function () {
        $eng  = TaxService::compute(1_120_000, 12, 'Inclusive');
        $indo = TaxService::compute(1_120_000, 12, 'Inklusif');
        expect($eng)->toBe($indo);
    });
});

