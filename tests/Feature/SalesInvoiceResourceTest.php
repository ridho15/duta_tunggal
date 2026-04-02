<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\ListSalesInvoices;
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
use Illuminate\Support\Facades\Config;
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
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);

        foreach (['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order']);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->assertSet('data.ar_coa_id', $arCoa->id)
            ->assertSet('data.revenue_coa_id', $revCoa->id)
            ->assertSet('data.ppn_keluaran_coa_id', $ppnCoa->id);
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

    /** @test */
    public function legacy_sales_invoice_tax_values_are_normalized_for_display(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 100000,
            'dpp' => 100000,
            'tax' => 11,
            'ppn_rate' => 0,
            'tipe_pajak' => 'Eklusif',
            'total' => 111000,
        ]);

        $this->assertSame('Eksklusif', $invoice->tax_type_display);
        $this->assertSame(11.0, $invoice->effective_ppn_rate);
        $this->assertSame(11000.0, $invoice->ppn_amount);
    }

    /** @test */
    public function sales_invoice_edit_save_normalizes_formatted_money_values_before_persisting(): void
    {
        $cabang = Cabang::factory()->create();

        $invoice = Invoice::withoutEvents(function () use ($cabang) {
            return Invoice::create([
                'invoice_number'  => 'INV-EDIT-001',
                'from_model_type' => 'App\\Models\\Unknown',
                'from_model_id'   => 1,
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'subtotal'        => 2_000_000,
                'dpp'             => 2_000_000,
                'total'           => 2_220_000,
                'ppn_rate'        => 11,
                'tax'             => 11,
                'status'          => 'draft',
                'cabang_id'       => $cabang->id,
            ]);
        });

        $invoice->fill([
            'total'    => '2.220.000',
            'dpp'      => '2.000.000',
            'subtotal' => '2.000.000',
            'tax'      => '11',
            'ppn_rate' => '11',
        ])->save();

        $invoice->refresh();

        $this->assertSame(2220000.0, (float) $invoice->total);
        $this->assertSame(2000000.0, (float) $invoice->dpp);
        $this->assertSame(2000000.0, (float) $invoice->subtotal);
        $this->assertSame(11.0, (float) $invoice->ppn_rate);
        $this->assertSame(11.0, (float) $invoice->tax);
    }

    /** @test */
    public function creating_invoice_via_resource_uses_configured_non_legacy_coa_values(): void
    {
        Config::set('coa.accounts_receivable', '1120.10');
        Config::set('coa.sales_revenue', '4111');
        Config::set('coa.sales_output_vat', '2120.99');

        $arCoa = ChartOfAccount::factory()->create(['code' => '1120.10', 'type' => 'Asset']);
        $revCoa = ChartOfAccount::factory()->create(['code' => '4111', 'type' => 'Revenue']);
        $ppnCoa = ChartOfAccount::factory()->create(['code' => '2120.99', 'type' => 'Liability']);
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create(['cabang_id' => $cabang->id]);

        foreach (['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice', 'create invoice', 'view any customer', 'view any product', 'view any warehouse', 'view any sale order']);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->assertSet('data.ar_coa_id', $arCoa->id)
            ->assertSet('data.revenue_coa_id', $revCoa->id)
            ->assertSet('data.ppn_keluaran_coa_id', $ppnCoa->id);
    }

    /** @test */
    public function sales_invoice_list_defaults_to_newest_first(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $olderInvoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'invoice_number' => 'INV-OLD-001',
                'invoice_date' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        });

        $newerInvoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'invoice_number' => 'INV-NEW-001',
                'invoice_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $user = User::factory()->create();
        foreach (['view any invoice', 'view invoice'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view any invoice', 'view invoice']);

        $component = Livewire::actingAs($user)->test(ListSalesInvoices::class);
        $records = $component->instance()->getTableRecords();

        $this->assertSame([$newerInvoice->id, $olderInvoice->id], $records->pluck('id')->all());
    }
}
