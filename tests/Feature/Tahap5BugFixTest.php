<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DepositAdjustmentResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockOpnameResource;
use App\Filament\Resources\SupplierResource;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\SaleOrder;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Tests\TestCase;

class Tahap5BugFixTest extends TestCase
{
    protected function createSupplier(array $attributes = []): Supplier
    {
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CBG-T5', 'nama' => 'Cabang T5']);
        return Supplier::create(array_merge([
            'cabang_id' => $cabang->id,
            'code' => 'SUPP-' . uniqid(),
            'perusahaan' => 'PT Supplier Test ' . uniqid(),
            'address' => 'Jl. Supplier No. 1',
            'phone' => '081234567890',
            'handphone' => '081234567890',
            'fax' => '021-123456',
            'email' => 'supplier' . uniqid() . '@test.com',
            'npwp' => '123456789012345',
        ], $attributes));
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CBG-T5', 'nama' => 'Cabang T5']);
        return Customer::create(array_merge([
            'cabang_id' => $cabang->id,
            'name' => 'Customer Test ' . uniqid(),
            'code' => 'CUST-' . uniqid(),
            'address' => 'Jl. Customer No. 1',
            'telephone' => '021-123456',
            'phone' => '08123456789',
            'email' => 'customer' . uniqid() . '@test.com',
            'perusahaan' => 'PT Customer Test ' . uniqid(),
            'tipe' => 'PKP',
            'fax' => '021-123456',
            'nik_npwp' => '1234567890123456',
        ], $attributes));
    }

    protected function createProduct(array $attributes = []): Product
    {
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CBG-T5', 'nama' => 'Cabang T5']);
        $category = \App\Models\ProductCategory::firstOrCreate(
            ['kode' => 'ALKES-T5'],
            ['name' => 'Kategori T5']
        );
        $uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'PCS'],
            ['name' => 'PCS', 'abbreviation' => 'PCS']
        );

        return Product::create(array_merge([
            'cabang_id' => $cabang->id,
            'name' => 'Produk T5 ' . uniqid(),
            'sku' => 'SKU-T5-' . uniqid(),
            'uom_id' => $uom->id,
            'product_category_id' => $category->id,
            'sell_price' => 25000,
            'cost_price' => 15000,
            'harga_batas' => 5,
            'kode_merk' => 'MERK-T5',
        ], $attributes));
    }

    public function test_qc_return_deduplication_prevents_duplicate_returns()
    {
        $supplier = $this->createSupplier();
        $cabang = Cabang::find($supplier->cabang_id);
        $user = User::first() ?? User::factory()->create();
        $currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp']
        );

        $warehouse = Warehouse::first() ?? Warehouse::create([
            'cabang_id' => $cabang->id,
            'name' => 'Gudang Utama T5',
            'location' => 'Jakarta',
            'kode' => 'GUD-T5',
        ]);
        $product = $this->createProduct(['cabang_id' => $cabang->id]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-T5-' . uniqid(),
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'order_date' => now(),
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'REC-T5-' . uniqid(),
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'receipt_date' => now(),
            'received_by' => $user->id,
            'currency_id' => $currency->id,
            'status' => 'completed',
        ]);

        // Pre-create PurchaseReturn representing the QC reject return
        $existingReturn = PurchaseReturn::create([
            'nota_retur' => 'NR-T5-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'return_date' => now(),
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        // When PurchaseReceiptItemObserver triggers, it must detect existing return and NOT create a second return
        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty_received' => 10,
            'qty_accepted' => 8,
            'qty_rejected' => 2,
            'reason_rejected' => '2 rusak saat QC',
        ]);

        $returnsCount = PurchaseReturn::where('purchase_receipt_id', $receipt->id)->count();
        $this->assertEquals(1, $returnsCount, 'Should not create duplicate return when a return for the receipt already exists');
    }

    public function test_stock_opname_has_combined_relation_manager_tabs_configured()
    {
        $viewPage = new StockOpnameResource\Pages\ViewStockOpname();
        $editPage = new StockOpnameResource\Pages\EditStockOpname();

        $this->assertTrue(
            $viewPage->hasCombinedRelationManagerTabsWithContent(),
            'ViewStockOpname must show relation manager combined with content so items table is never hidden'
        );
        $this->assertTrue(
            $editPage->hasCombinedRelationManagerTabsWithContent(),
            'EditStockOpname must show relation manager combined with content so items table is never hidden'
        );
    }

    public function test_cash_bank_accounts_scope_is_used_for_deposit_adjustments()
    {
        $cashBankCount = ChartOfAccount::cashBank()->count();
        $allCount = ChartOfAccount::count();
        
        $this->assertLessThanOrEqual($allCount, $cashBankCount);
        $cashAccounts = ChartOfAccount::cashBank()->get();
        foreach ($cashAccounts as $account) {
            $code = (string) $account->code;
            $this->assertTrue(
                str_starts_with($code, '11') || str_starts_with($code, '1-1'),
                "Account {$code} ({$account->name}) should belong to Cash & Bank (11...)"
            );
        }
    }

    public function test_supplier_bank_details_rendered_in_po_pdf()
    {
        $supplier = $this->createSupplier([
            'perusahaan' => 'PT Bank Supplier Mandiri',
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8880012345',
            'nama_rekening' => 'PT Bank Supplier Mandiri',
        ]);
        $warehouse = Warehouse::first() ?? Warehouse::create([
            'cabang_id' => $supplier->cabang_id,
            'name' => 'Gudang PO T5',
            'location' => 'Jakarta',
            'kode' => 'GUD-PO-T5',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-BANK-' . uniqid(),
            'supplier_id' => $supplier->id,
            'cabang_id' => $supplier->cabang_id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'order_date' => now(),
        ]);

        $html = view('pdf.purchase-order', [
            'purchaseOrder' => $po,
            'isImport' => false,
            'topLabel' => 'Cash',
            'headerCurrencyId' => null,
            'formatMoney' => fn ($val) => 'Rp ' . number_format($val, 0, ',', '.'),
        ])->render();

        $this->assertStringContainsString('Rekening Supplier', $html);
        $this->assertStringContainsString('BCA', $html);
        $this->assertStringContainsString('8880012345', $html);
        $this->assertStringContainsString('PT Bank Supplier Mandiri', $html);
    }

    public function test_invoice_polymorphic_search_query_does_not_crash()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = $this->createCustomer([
            'perusahaan' => 'PT Customer Polymorph Test',
        ]);
        $so = SaleOrder::create([
            'so_number' => 'SO-INV-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $customer->cabang_id,
            'order_date' => now(),
            'status' => 'completed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'tempo_pembayaran' => 30,
            'shipped_to' => 'Alamat Pengiriman',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'cabang_id' => $customer->cabang_id,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'total' => 100000,
        ]);

        $search = 'PT Customer Polymorph';
        $results = Invoice::query()
            ->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhereHasMorph('fromModel', [SaleOrder::class], function ($soQuery) use ($search) {
                        $soQuery->whereHas('customer', function ($cQuery) use ($search) {
                            $cQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('perusahaan', 'like', "%{$search}%");
                        });
                    })
                    ->orWhereHasMorph('fromModel', [PurchaseOrder::class], function ($poQuery) use ($search) {
                        $poQuery->whereHas('supplier', function ($sQuery) use ($search) {
                            $sQuery->where('perusahaan', 'like', "%{$search}%");
                        });
                    });
            })
            ->get();

        $this->assertTrue($results->contains($invoice));
    }
}
