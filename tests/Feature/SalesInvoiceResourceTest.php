<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Forms\Form;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalesInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_invoice_via_resource_uses_default_coa_values(): void
    {
        // seed baseline COA records with expected codes
        $arCoa      = ChartOfAccount::factory()->create(['code' => '1120']);
        $revCoa     = ChartOfAccount::factory()->create(['code' => '4000']);
        $ppnCoa     = ChartOfAccount::factory()->create(['code' => '2120.06']);

        $cabang   = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        foreach (['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order']);

        // prepare a sale order to link from (manual create avoids factory issues)
        $so = SaleOrder::create([
            'so_number'             => 'SO-INV-001',
            'customer_id'           => $customer->id,
            'status'                => 'completed',
            'tipe_pengiriman'       => 'Kirim Langsung',
            'order_date'            => now()->toDateString(),
            'delivery_date'         => now()->toDateString(),
            'cabang_id'             => $cabang->id,
            'warehouse_id'          => $warehouse->id,
            'warehouse_confirmed_at'=> now(),
            'created_by'            => $user->id,
        ]);

        // minimal invoice item data
        $product = Product::factory()->create([
            'sales_coa_id' => $revCoa->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'delivered_quantity' => 0,
            'unit_price' => 1000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'None',
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'completed',
            'created_by' => $user->id,
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reason' => 'Testing invoice flow',
        ]);

        $deliveryOrder->salesOrders()->attach($so->id);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->set('data.selected_customer', $customer->id)
            ->set('data.selected_sale_order', $so->id)
            ->set('data.selected_delivery_orders', [$deliveryOrder->id])
            ->set('data.from_model_type', SaleOrder::class)
            ->set('data.from_model_id', $so->id)
            ->set('data.delivery_orders', [$deliveryOrder->id])
            ->set('data.cabang_id', $cabang->id)
            ->set('data.invoice_number', 'INV-TEST-001')
            ->set('data.invoice_date', now()->toDateString())
            ->set('data.due_date', now()->addDays(30)->toDateString())
            ->set('data.subtotal', 1000)
            ->set('data.tax', 0)
            ->set('data.ppn_rate', 0)
            ->set('data.total', 1000)
            ->set('data.invoiceItem', [
                [
                    'product_id' => $product->id,
                    'quantity'   => 1,
                    'price'      => 1000,
                    'total'      => 1000,
                ],
            ])
            ->call('create');

        $invoice = Invoice::latest()->first();

        $this->assertNotNull($invoice);
        $this->assertEquals($arCoa->id, $invoice->ar_coa_id);
        $this->assertEquals($revCoa->id, $invoice->revenue_coa_id);
        $this->assertEquals($ppnCoa->id, $invoice->ppn_keluaran_coa_id);

        // invoice item should be created for the invoice
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);
    }

    /** @test */
    public function the_form_schema_contains_hidden_coa_fields(): void
    {
        $user = User::factory()->create();
        foreach (['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order']);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->assertSuccessful()
            ->assertFormExists()
            ->assertFormFieldExists('ar_coa_id')
            ->assertFormFieldExists('revenue_coa_id')
            ->assertFormFieldExists('ppn_keluaran_coa_id');
    }

    /** @test */
    public function ppn_rate_auto_fills_from_tax_setting_when_tipe_pajak_changes(): void
    {
        $cabang   = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $product = Product::factory()->create();

        foreach (['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order']);

        TaxSetting::factory()->ppn()->create([
            'effective_date' => now()->subDay()->toDateString(),
            'status' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'from_model_type'   => SaleOrder::class,
                'from_model_id'     => null,
                'customer_name'     => $customer->name,
                'customer_phone'    => $customer->phone,
                'cabang_id'         => $cabang->id,
                'subtotal'          => 1000,
                'tax'               => 0,
                'ppn_rate'          => 0,
                'total'             => 1000,
                'invoiceItem'       => [
                    [
                        'product_id' => $product->id,
                        'quantity'   => 1,
                        'price'      => 1000,
                        'total'      => 1000,
                    ],
                ],
            ])
            ->set('data.tipe_pajak', 'Inklusif')
            ->assertSet('data.ppn_rate', 11)
            ->set('data.ppn_rate', 7)
            ->assertSet('data.ppn_rate', 7)
            ->set('data.tipe_pajak', 'Eklusif')
            ->assertSet('data.ppn_rate', 11)
            ->set('data.tipe_pajak', 'None')
            ->assertSet('data.ppn_rate', 0);
    }
}
