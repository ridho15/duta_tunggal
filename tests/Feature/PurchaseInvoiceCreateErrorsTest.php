<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Penolakan dari service invoice (nomor invoice supplier ganda, receipt sudah ditagih, dan lain-lain) harus
 * terlihat oleh pengguna: error menempel di field (kunci "data.*") dan sebuah notifikasi ikut dikirim.
 */
class PurchaseInvoiceCreateErrorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Cabang $cabang;
    private PurchaseOrder $purchaseOrder;
    private PurchaseReceipt $receipt;

    /** @var array<string, int> */
    private array $coaIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => sprintf('%s %s', $action, $resource), 'guard_name' => 'web']);
            }
        }

        $this->user = User::factory()->create(['manage_type' => 'all']);
        $this->user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice']);
        $this->actingAs($this->user);

        $this->cabang = Cabang::factory()->create();
        UnitOfMeasure::factory()->create();

        // Form invoice mewajibkan COA hutang; di aplikasi nyata field ini terisi default dari kode COA.
        $this->coaIds = [];
        foreach ([
            'accounts_payable_coa_id' => [config('coa.accounts_payable', '2110'), 'Hutang Dagang', 'Liability'],
            'ppn_masukan_coa_id' => ['1170.06', 'PPN Masukan', 'Asset'],
            'inventory_coa_id' => [config('coa.inventory', '1140.10'), 'Persediaan Barang', 'Asset'],
            'expense_coa_id' => ['6100.02', 'Beban Pembelian', 'Expense'],
        ] as $field => [$code, $name, $type]) {
            $this->coaIds[$field] = ChartOfAccount::factory()->create(['code' => $code, 'name' => $name, 'type' => $type, 'is_current' => true])->id;
        }
        $currency = Currency::factory()->create(['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        $this->supplier = Supplier::factory()->create(['perusahaan' => 'CV Uji Invoice', 'cabang_id' => $this->cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);
        $product = Product::factory()->forCabang($this->cabang)->create(['supplier_id' => $this->supplier->id]);

        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);
        $this->purchaseOrder->purchaseOrderCurrency()->create(['currency_id' => $currency->id, 'nominal' => 1]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'product_id' => $product->id,
            'currency_id' => $currency->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'tax' => 0,
        ]);

        $this->receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);
        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $this->receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
        ]);
    }

    private function existingInvoiceWithSupplierNumber(string $number): Invoice
    {
        $otherPo = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        return Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $otherPo->id,
            'supplier_id' => $this->supplier->id,
            'supplier_invoice_number' => $number,
            'status' => 'draft',
        ]);
    }

    public function test_duplicate_supplier_invoice_number_is_reported_on_the_field_and_in_a_notification(): void
    {
        $this->existingInvoiceWithSupplierNumber('SUP-DUP-001');

        $component = Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm($this->coaIds + [
                'selected_supplier' => $this->supplier->id,
                'selected_purchase_orders' => [$this->purchaseOrder->id],
                'selected_purchase_receipts' => [$this->receipt->id],
                'invoice_number' => 'PINV-UJI-0001',
                'supplier_invoice_number' => 'SUP-DUP-001',
                'tax_invoice_number' => '010.000-26.00000001',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
            ])
            ->call('create');
        $component->assertHasFormErrors(['supplier_invoice_number'])
            ->assertNotified('Invoice pembelian belum dapat disimpan');

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'PINV-UJI-0001']);
    }

    public function test_service_errors_get_the_data_prefix_so_livewire_can_attach_them_to_fields(): void
    {
        $this->existingInvoiceWithSupplierNumber('SUP-DUP-002');

        $page = new class extends CreatePurchaseInvoice {
            public function runMutate(array $data): array
            {
                $method = new \ReflectionMethod($this, 'mutateFormDataBeforeCreate');
                $method->setAccessible(true);

                return $method->invoke($this, $data);
            }
        };

        try {
            $page->runMutate([
                'selected_supplier' => $this->supplier->id,
                'selected_purchase_orders' => [$this->purchaseOrder->id],
                'selected_purchase_receipts' => [$this->receipt->id],
                'cabang_id' => $this->cabang->id,
                'supplier_invoice_number' => 'SUP-DUP-002',
                'invoiceItem' => [],
            ]);

            $this->fail('Nomor invoice supplier ganda seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(['data.supplier_invoice_number'], array_keys($exception->errors()));
            $this->assertStringContainsString('sudah pernah digunakan', $exception->errors()['data.supplier_invoice_number'][0]);
        }
    }

    public function test_cancelled_invoice_frees_its_receipt_and_supplier_and_tax_numbers(): void
    {
        $existing = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'purchase_receipts' => [$this->receipt->id],
            'supplier_invoice_number' => 'SUP-FREE-001',
            'tax_invoice_number' => '010.000-26.00000777',
            'status' => Invoice::STATUS_SENT,
        ]));

        $data = [
            'selected_supplier' => $this->supplier->id,
            'selected_purchase_orders' => [$this->purchaseOrder->id],
            'selected_purchase_receipts' => [$this->receipt->id],
            'purchase_order_ids' => [$this->purchaseOrder->id],
            'cabang_id' => $this->cabang->id,
            'supplier_invoice_number' => 'SUP-FREE-001',
            'tax_invoice_number' => '010.000-26.00000777',
            'invoiceItem' => [],
        ];

        $service = app(\App\Services\PurchaseInvoiceAccountingService::class);

        try {
            $service->validateReceiptBackedCreateData($data);
            $this->fail('Receipt yang sudah ditagih seharusnya ditolak selama invoicenya aktif.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selected_purchase_receipts', $exception->errors());
        }

        Invoice::withoutEvents(fn () => $existing->update(['status' => Invoice::STATUS_CANCELLED]));

        // Setelah dibatalkan: receipt, nomor invoice supplier, dan nomor faktur pajak boleh dipakai invoice pengganti.
        $validated = $service->validateReceiptBackedCreateData($data);
        $this->assertSame('SUP-FREE-001', $validated['supplier_invoice_number']);
    }
}
