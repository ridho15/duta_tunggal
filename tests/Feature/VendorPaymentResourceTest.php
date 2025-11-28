<?php

namespace Tests\Feature;

use App\Filament\Resources\VendorPaymentResource;
use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function grantVendorPaymentPermissions(User $user, array $permissions): void
{
    registerAllPermissions();

    $user->givePermissionTo($permissions);
}

class VendorPaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $currency;
    protected $supplier;
    protected $warehouse;
    protected $product;
    protected $chartOfAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $permissions = [
            'view any vendor payment',
            'view vendor payment',
            'create vendor payment',
            'update vendor payment',
            'delete vendor payment',
            'view any supplier',
            'view any invoice',
            'view any account payable',
            'view account payable',
            'create account payable',
            'update account payable',
            'delete account payable',
            'view any chart of account',
            'view any deposit',
        ];
        grantVendorPaymentPermissions($this->user, $permissions);
        $this->actingAs($this->user);

        // Create basic data
        $this->currency = Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
        ]);

        $this->supplier = Supplier::factory()->create([
            'tempo_hutang' => 30,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'status' => 1,
        ]);

        \App\Models\UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'uom_id' => \App\Models\UnitOfMeasure::first()->id,
        ]);

        // Create COA for cash/bank
        $this->chartOfAccount = ChartOfAccount::factory()->create([
            'code' => '1101',
            'name' => 'Kas Kecil',
            'type' => 'Asset',
            'is_current' => true,
        ]);

        // Create COA for accounts payable
        ChartOfAccount::factory()->create([
            'code' => '2101',
            'name' => 'Hutang Supplier',
            'type' => 'Liability',
            'is_current' => true,
        ]);
    }

    public function test_vendor_payment_resource_can_render_list_page()
    {
        Livewire::test(VendorPaymentResource\Pages\ListVendorPayments::class)
            ->assertSuccessful();
    }

    public function test_vendor_payment_resource_can_render_create_page()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertSuccessful();
    }

    public function test_vendor_payment_form_has_required_fields()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormExists()
            ->assertFormFieldExists('supplier_id')
            ->assertFormFieldExists('payment_date')
            ->assertFormFieldExists('ntpn')
            ->assertFormFieldExists('total_payment')
            ->assertFormFieldExists('coa_id')
            ->assertFormFieldExists('payment_method')
            ->assertFormFieldExists('notes')
            ->assertFormFieldExists('selected_invoices')
            ->assertFormFieldExists('invoice_receipts');
    }

    public function test_supplier_selection_is_required()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => null,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['supplier_id' => 'required']);
    }

    public function test_payment_date_is_required()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => null,
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['payment_date' => 'required']);
    }

    public function test_ntpn_is_required()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => null,
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['ntpn' => 'required']);
    }

    public function test_coa_selection_is_required()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => null,
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['coa_id' => 'required']);
    }

    public function test_payment_method_is_required()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['payment_method' => 'required']);
    }

    public function test_payment_method_options_available()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormFieldExists('payment_method');
    }

    public function test_supplier_selection_filters_available_invoices()
    {
        // Create invoice for supplier
        $invoice = $this->createTestInvoice();

        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
            ])
            ->assertFormSet([
                'selected_invoices' => [],
                'total_payment' => 0,
            ]);
    }

    public function test_form_can_be_filled_with_basic_data()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
                'notes' => 'Test payment notes',
            ])
            ->assertFormSet([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
                'notes' => 'Test payment notes',
            ]);
    }

    public function test_cash_payment_method_selection()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
            ])
            ->assertFormSet([
                'payment_method' => 'Cash',
            ]);
    }

    public function test_bank_transfer_payment_method_selection()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Bank Transfer',
            ])
            ->assertFormSet([
                'payment_method' => 'Bank Transfer',
            ]);
    }

    public function test_credit_payment_method_selection()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Credit',
            ])
            ->assertFormSet([
                'payment_method' => 'Credit',
            ]);
    }

    public function test_deposit_payment_method_selection()
    {
        // Create deposit for supplier
        Deposit::factory()->create([
            'from_model_type' => Supplier::class,
            'from_model_id' => $this->supplier->id,
            'amount' => 100000,
            'used_amount' => 0,
            'remaining_amount' => 100000,
            'coa_id' => $this->chartOfAccount->id,
            'status' => 'active',
            'created_by' => $this->user->id
        ]);

        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Deposit',
            ])
            ->assertFormSet([
                'payment_method' => 'Deposit',
            ]);
    }

    public function test_total_payment_field_is_not_readonly()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormFieldExists('total_payment')
            ->assertFormFieldIsEnabled('total_payment');
    }

    public function test_ntpn_field_has_max_length_validation()
    {
        $longNtpn = str_repeat('A', 256); // Exceeds max length of 255

        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => $longNtpn,
                'coa_id' => $this->chartOfAccount->id,
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['ntpn']);
    }

    public function test_coa_options_are_available()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormFieldExists('coa_id');
    }

    public function test_supplier_options_are_available()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormFieldExists('supplier_id');
    }

    public function test_form_has_hidden_fields_for_backward_compatibility()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormFieldExists('invoice_id')
            ->assertFormFieldExists('status')
            ->assertFormFieldExists('payment_adjustment')
            ->assertFormFieldExists('diskon')
            ->assertFormFieldExists('selected_invoices')
            ->assertFormFieldExists('invoice_receipts');
    }

    public function test_vendor_payment_resource_can_render_edit_page()
    {
        $vendorPayment = VendorPayment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'ntpn' => 'NTPN123456',
            'total_payment' => 50000,
            'coa_id' => $this->chartOfAccount->id,
            'payment_method' => 'Cash',
        ]);

        Livewire::test(VendorPaymentResource\Pages\EditVendorPayment::class, [
            'record' => $vendorPayment->getRouteKey(),
        ])
            ->assertSuccessful();
    }

    public function test_vendor_payment_resource_can_render_view_page()
    {
        $vendorPayment = VendorPayment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'ntpn' => 'NTPN123456',
            'total_payment' => 50000,
            'coa_id' => $this->chartOfAccount->id,
            'payment_method' => 'Cash',
        ]);

        Livewire::test(VendorPaymentResource\Pages\ViewVendorPayment::class, [
            'record' => $vendorPayment->getRouteKey(),
        ])
            ->assertSuccessful();
    }

    public function test_form_validation_messages_are_customized()
    {
        // Test supplier validation message
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['supplier_id' => 'Supplier belum dipilih']);

        // Test COA validation message
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'payment_date' => now()->format('Y-m-d'),
                'ntpn' => 'NTPN123456',
                'coa_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['coa_id' => 'COA belum dipilih']);
    }

    public function test_payment_date_defaults_to_today()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormSet([
                'payment_date' => now()->format('Y-m-d'),
            ]);
    }

    public function test_status_field_defaults_to_draft()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormSet([
                'status' => 'Draft',
            ]);
    }

    public function test_payment_adjustment_defaults_to_zero()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormSet([
                'payment_adjustment' => 0,
            ]);
    }

    public function test_diskon_defaults_to_zero()
    {
        Livewire::test(VendorPaymentResource\Pages\CreateVendorPayment::class)
            ->assertFormSet([
                'diskon' => 0,
            ]);
    }

    /**
     * Helper method to create a test invoice with all required relationships
     */
    private function createTestInvoice()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed'
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'tax' => 1000,
            'discount' => 0
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-TEST-' . rand(1000, 9999),
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'supplier_name' => $this->supplier->name,
            'subtotal' => 110000,
            'total' => 110000,
            'status' => 'draft'
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 11000,
            'total' => 110000
        ]);

        // Create account payable
        AccountPayable::factory()->create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 110000,
            'paid' => 0,
            'remaining' => 110000,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id
        ]);

        return $invoice;
    }
}