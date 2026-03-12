<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Forms\Form;
use Livewire\Livewire;
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

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'from_model_type'   => SaleOrder::class,
                'from_model_id'     => $so->id,
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
            ->call('create');

        $invoice = Invoice::latest()->first();

        $this->assertNotNull($invoice);
        $this->assertEquals($arCoa->id, $invoice->ar_coa_id);
        $this->assertEquals($revCoa->id, $invoice->revenue_coa_id);
        $this->assertEquals($ppnCoa->id, $invoice->ppn_keluaran_coa_id);

        // invoice item should inherit product sales_coa_id
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'coa_id'     => $revCoa->id,
        ]);
    }

    /** @test */
    public function the_form_schema_contains_hidden_coa_fields(): void
    {
        $form = SalesInvoiceResource::form(Form::make());

        $names = collect($form->getComponents())->map->getName()->all();

        $this->assertContains('ar_coa_id', $names);
        $this->assertContains('revenue_coa_id', $names);
        $this->assertContains('ppn_keluaran_coa_id', $names);

        // ensure they are Hidden components, not Select
        $form->getComponents(); // component tree already built above
        $hiddenTypes = collect($form->getComponents())
            ->filter(fn($c) => in_array($c->getName(), ['ar_coa_id', 'revenue_coa_id', 'ppn_keluaran_coa_id']))
            ->map(fn($c) => get_class($c))
            ->unique()
            ->all();

        $this->assertTrue(in_array(\Filament\Forms\Components\Hidden::class, $hiddenTypes));
    }
}
