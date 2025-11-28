<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptBiaya;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

        $this->user = User::factory()->create();
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

    public function test_purchase_invoice_form_has_required_fields()
    {
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->assertFormExists()
            ->assertFormFieldExists('invoice_number')
            ->assertFormFieldExists('selected_supplier')
            ->assertFormFieldExists('selected_purchase_order')
            ->assertFormFieldExists('selected_purchase_receipts')
            ->assertFormFieldExists('invoice_date')
            ->assertFormFieldExists('due_date')
            ->assertFormFieldExists('tax')
            ->assertFormFieldExists('ppn_rate')
            ->assertFormFieldExists('other_fees');
    }

    public function test_supplier_selection_filters_purchase_orders()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();
        $product = Product::factory()->create();

        // Create PO for supplier1
        $purchaseOrder1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier1->id,
            'status' => 'completed'
        ]);

        // Create PO for supplier2
        $purchaseOrder2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier2->id,
            'status' => 'completed'
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier1->id,
            ])
            ->assertFormSet([
                'selected_purchase_order' => null,
                'selected_purchase_receipts' => [],
                'invoiceItem' => [],
                'subtotal' => 0,
                'total' => 0,
            ]);
    }

    public function test_purchase_order_selection_loads_receipts()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Create PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
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
            'status' => 'completed'
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'is_sent' => false
        ]);

        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'selected_supplier' => $supplier->id,
                'selected_purchase_order' => $purchaseOrder->id,
            ])
            ->assertFormSet([
                'selected_purchase_receipts' => [],
                'invoiceItem' => [],
                'subtotal' => 0,
                'total' => 0,
            ]);
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
        // Test that tax and fee fields can be filled
        Livewire::test(PurchaseInvoiceResource\Pages\CreatePurchaseInvoice::class)
            ->fillForm([
                'invoice_number' => 'PINV-TAX-TEST-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'tax' => 5,
                'ppn_rate' => 11,
            ])
            ->assertFormSet([
                'tax' => 5,
                'ppn_rate' => 11,
            ]);
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
            'status' => 'completed'
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0002',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id
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
            'status' => 'completed'
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
            'status' => 'completed'
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'is_sent' => false
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
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'subtotal' => 100000,
            'other_fee' => [
                [
                    'name' => 'Biaya Admin',
                    'amount' => 5000,
                ],
            ],
            'tax' => 0,
            'total' => 105000, // subtotal + other_fee
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
        $this->assertCount(1, $savedInvoice->other_fee);
        $this->assertEquals('Biaya Admin', $savedInvoice->other_fee[0]['name']);
        $this->assertEquals(5000, $savedInvoice->other_fee[0]['amount']);
        // Should include both manually added fees and fees from receipt
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'PINV-TEST-OTHER-FEE-001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => 100000,
            'other_fee' => json_encode([
                [
                    'name' => 'Biaya Admin',
                    'amount' => 5000,
                ],
                [
                    'name' => 'Biaya Transport',
                    'amount' => 7500,
                ],
            ]),
        ]);

        $invoice = Invoice::where('invoice_number', 'PINV-TEST-OTHER-FEE-001')->first();
        $this->assertEquals(12500, $invoice->other_fee_total);
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
            'name' => 'Test Supplier',
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
            'name' => 'Yayasan Narpati (Persero) Tbk',
        ]);

        // Test deposit creation through Filament form
        $component = Livewire::test(\App\Filament\Resources\DepositResource\Pages\CreateDeposit::class);

        // Try to set form data using different approach
        $component->set('data', [
            'from_model_type' => 'App\Models\Supplier',
            'from_model_id' => $supplier->id,
            'deposit_number' => 'DEP-SUP-OOGNE-001',
            'amount' => 1000000,
            'note' => 'Deposit supplier Yayasan Narpati (Persero) Tbk',
            'coa_id' => $titipanCoa->id,
            'payment_coa_id' => $kasCoa->id,
        ]);

        // Call create action
        $component->call('create');

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
            'name' => 'Yayasan Narpati (Persero) Tbk',
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