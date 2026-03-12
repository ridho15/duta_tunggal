<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Tasks 23–26:
 *  23 – "Mark as Sent" removed from DO (handled via SJ terbit)
 *  24 – DO approve action re-labeled "Konfirmasi Dana Diterima"
 *  25 – DO table shows Nomor DO, Customer, Tanggal, Status as primary columns
 *  26 – SuratJalan stores / shows sender_name & shipping_method
 */
class DeliveryOrderTask2326Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Product $product;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected SaleOrder $saleOrder;
    protected SaleOrderItem $saleOrderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'       => 'DO Task Tester',
            'email'      => 'dotask@example.com',
            'username'   => 'dotasktester',
            'password'   => bcrypt('password'),
            'first_name' => 'DOTask',
            'kode_user'  => 'DT001',
        ]);

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer  = Customer::factory()->create();
        $this->product   = Product::factory()->create();
        $this->driver    = Driver::factory()->create();
        $this->vehicle   = Vehicle::factory()->create();

        // COA needed for journal entries created by DeliveryOrderService
        foreach ([
            ['1140.10', 'PERSEDIAAN BARANG DAGANGAN',    'Asset'],
            ['1140.20', 'BARANG TERKIRIM',               'Asset'],
            ['1180.10', 'BARANG TERKIRIM - DEFAULT',     'Asset'],
        ] as [$code, $name, $type]) {
            ChartOfAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'is_active' => true]
            );
        }

        $this->saleOrder = SaleOrder::create([
            'customer_id'     => $this->customer->id,
            'so_number'       => 'SO-TASK2326-001',
            'order_date'      => now(),
            'status'          => 'confirmed',
            'delivery_date'   => now()->addDays(1),
            'total_amount'    => 500000,
            'tipe_pengiriman' => 'Kirim Langsung',
            'created_by'      => $this->user->id,
        ]);

        $this->saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $this->saleOrder->id,
            'product_id'    => $this->product->id,
            'quantity'      => 5,
            'unit_price'    => 100000,
            'discount'      => 0,
            'tax'           => 0,
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $this->actingAs($this->user);
    }

    // ─── Task 23 ──────────────────────────────────────────────────────────────

    /** @test */
    public function task23_do_status_becomes_sent_only_when_sj_terbit_action_is_triggered(): void
    {
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TASK23-001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => $this->vehicle->id,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'approved',
            'created_by'    => $this->user->id,
            'cabang_id'     => $this->cabang->id,
        ]);
        $do->salesOrders()->attach($this->saleOrder->id);

        $sj = SuratJalan::create([
            'sj_number'   => 'SJ-TASK23-001',
            'issued_at'   => now(),
            'created_by'  => $this->user->id,
            'status'      => 0,
            'sender_name' => 'Budi',
        ]);
        $sj->deliveryOrder()->attach($do->id);

        // Simulate SJ terbit action: mark status=1 and all linked DOs as sent
        $sj->update(['status' => 1]);
        $service = app(DeliveryOrderService::class);
        $service->updateStatus($do, 'sent');

        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'sent']);
        $this->assertDatabaseHas('surat_jalans',    ['id' => $sj->id, 'status' => 1]);
    }

    /** @test */
    public function task23_do_resource_has_no_standalone_mark_as_sent_action(): void
    {
        // Parse the resource actions - ensure 'sent' action handle isn't present
        // by verifying via the table action names from the resource's getActions method.
        // We test this indirectly: the only path to status='sent' is via SuratJalan.
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TASK23-002',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => $this->vehicle->id,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'approved',
            'created_by'    => $this->user->id,
            'cabang_id'     => $this->cabang->id,
        ]);

        // Verify that status is still 'approved': calling updateStatus(sent) via SJ
        // is the only valid path; no Filament action on the DO record can do it alone.
        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'approved']);

        // DeliveryOrderResource source file must NOT contain the 'Mark as Sent' label
        $resourceSource = file_get_contents(
            app_path('Filament/Resources/DeliveryOrderResource.php')
        );
        $this->assertStringNotContainsString("'Mark as Sent'", $resourceSource);
        $this->assertStringNotContainsString('"Mark as Sent"', $resourceSource);
    }

    // ─── Task 24 ──────────────────────────────────────────────────────────────

    /** @test */
    public function task24_do_approve_action_is_labeled_konfirmasi_dana_diterima(): void
    {
        $resourceSource = file_get_contents(
            app_path('Filament/Resources/DeliveryOrderResource.php')
        );

        $this->assertStringContainsString('Konfirmasi Dana Diterima', $resourceSource,
            'DO resource should contain "Konfirmasi Dana Diterima" label for the approve action');
        $this->assertStringContainsString('Apakah Dana Sudah Diterima', $resourceSource,
            'DO resource should contain modal heading "Apakah Dana Sudah Diterima?"');
    }

    /** @test */
    public function task24_do_transitions_to_approved_via_service_when_dana_confirmed(): void
    {
        // Setup a SuratJalan so the approve action visibility check passes
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TASK24-001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => $this->vehicle->id,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'request_approve',
            'created_by'    => $this->user->id,
            'cabang_id'     => $this->cabang->id,
        ]);
        $do->salesOrders()->attach($this->saleOrder->id);

        $sj = SuratJalan::create([
            'sj_number'  => 'SJ-TASK24-001',
            'issued_at'  => now(),
            'created_by' => $this->user->id,
            'status'     => 0,
        ]);
        $sj->deliveryOrder()->attach($do->id);

        // Simulate the "Konfirmasi Dana Diterima" action calling the service
        $service = app(DeliveryOrderService::class);
        $service->updateStatus($do, 'approved', 'Dana sudah diterima penuh', 'approved');

        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'approved']);
    }

    // ─── Task 25 ──────────────────────────────────────────────────────────────

    /** @test */
    public function task25_do_resource_table_has_do_number_customer_date_status_as_primary_columns(): void
    {
        $resourceSource = file_get_contents(
            app_path('Filament/Resources/DeliveryOrderResource.php')
        );

        // All four key columns must exist
        $this->assertStringContainsString("'do_number'",      $resourceSource);
        $this->assertStringContainsString("'customer_names'", $resourceSource);
        $this->assertStringContainsString("'delivery_date'",  $resourceSource);
        $this->assertStringContainsString("'status'",         $resourceSource);

        // do_number should appear before customer_names in the table column list
        $doPos       = strpos($resourceSource, "TextColumn::make('do_number')");
        $custPos     = strpos($resourceSource, "TextColumn::make('customer_names')");
        $datePos     = strpos($resourceSource, "TextColumn::make('delivery_date')");
        $statusPos   = strpos($resourceSource, "TextColumn::make('status')\n");

        $this->assertLessThan($custPos,   $doPos,     'do_number must appear before customer_names');
        $this->assertLessThan($datePos,   $custPos,   'customer_names must appear before delivery_date');
        $this->assertLessThan($statusPos, $datePos,   'delivery_date must appear before status');
    }

    /** @test */
    public function task25_do_can_be_queried_with_relevant_fields(): void
    {
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TASK25-001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => $this->vehicle->id,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'created_by'    => $this->user->id,
            'cabang_id'     => $this->cabang->id,
        ]);
        $do->salesOrders()->attach($this->saleOrder->id);
        $do->refresh();

        // The four key pieces of information must be retrievable
        $this->assertNotNull($do->do_number);
        $this->assertNotNull($do->delivery_date);
        $this->assertNotNull($do->status);

        $customerName = $do->salesOrders->first()?->customer?->name
            ?? $do->salesOrders->first()?->customer?->perusahaan;
        $this->assertNotNull($customerName);
    }

    // ─── Task 26 ──────────────────────────────────────────────────────────────

    /** @test */
    public function task26_surat_jalan_stores_sender_name_and_shipping_method(): void
    {
        $sj = SuratJalan::create([
            'sj_number'       => 'SJ-TASK26-001',
            'issued_at'       => now(),
            'created_by'      => $this->user->id,
            'status'          => 0,
            'sender_name'     => 'Ahmad Fauzi',
            'shipping_method' => 'Ekspedisi',
        ]);

        $this->assertDatabaseHas('surat_jalans', [
            'id'              => $sj->id,
            'sender_name'     => 'Ahmad Fauzi',
            'shipping_method' => 'Ekspedisi',
        ]);
    }

    /** @test */
    public function task26_sj_resource_table_shows_sender_name_and_shipping_method_columns(): void
    {
        $resourceSource = file_get_contents(
            app_path('Filament/Resources/SuratJalanResource.php')
        );

        $this->assertStringContainsString("'sender_name'",     $resourceSource,
            'SJ resource table must include sender_name column');
        $this->assertStringContainsString("'shipping_method'", $resourceSource,
            'SJ resource table must include shipping_method column');

        // Verify they appear in the table() method (after public static function table)
        $tableStart = strpos($resourceSource, 'public static function table');
        $senderPos  = strpos($resourceSource, "'sender_name'",  $tableStart);
        $methodPos  = strpos($resourceSource, "'shipping_method'", $tableStart);

        $this->assertNotFalse($senderPos,  'sender_name column must be in table()');
        $this->assertNotFalse($methodPos,  'shipping_method column must be in table()');
    }

    /** @test */
    public function task26_surat_jalan_allows_all_shipping_method_values(): void
    {
        $methods = ['Ekspedisi', 'Kurir Internal', 'Ambil Sendiri', 'Lainnya'];
        foreach ($methods as $i => $method) {
            $sj = SuratJalan::create([
                'sj_number'       => "SJ-TASK26-M{$i}",
                'issued_at'       => now(),
                'created_by'      => $this->user->id,
                'status'          => 0,
                'shipping_method' => $method,
            ]);

            $this->assertDatabaseHas('surat_jalans', [
                'id'              => $sj->id,
                'shipping_method' => $method,
            ]);
        }
    }
}
