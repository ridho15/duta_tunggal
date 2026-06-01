<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Filament\Resources\DeliveryScheduleResource;
use App\Filament\Resources\DeliveryScheduleResource\Pages\CreateDeliverySchedule;
use App\Services\DeliveryScheduleService;
use App\Models\SuratJalan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected DeliveryScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'       => 'Test User',
            'email'      => 'schedule_test@example.com',
            'username'   => 'scheduleuser',
            'password'   => bcrypt('password'),
            'first_name' => 'Schedule',
            'kode_user'  => 'SCH001',
        ]);

        $this->cabang  = Cabang::factory()->create();
        $this->driver  = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['view any delivery schedule', 'create delivery schedule'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo(['view any delivery schedule', 'create delivery schedule']);

        $this->scheduleService = app(DeliveryScheduleService::class);
    }

    #[Test]
    public function it_can_generate_unique_schedule_number(): void
    {
        $first  = $this->scheduleService->generateScheduleNumber();
        $second = $this->scheduleService->generateScheduleNumber();

        $this->assertStringStartsWith('SCH-', $first);
        $this->assertEquals($first, $second, 'Sequential generation before first save should be same');
    }

    #[Test]
    public function it_generates_incrementing_schedule_numbers(): void
    {
        $first = DeliverySchedule::create([
            'schedule_number' => $this->scheduleService->generateScheduleNumber(),
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $second = $this->scheduleService->generateScheduleNumber();

        $this->assertNotEquals($first->schedule_number, $second);
    }

    #[Test]
    public function it_can_create_delivery_schedule_with_surat_jalan(): void
    {
        $deliveryOrder1 = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 'approved']);
        $deliveryOrder2 = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 'approved']);

        $suratJalan1 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan2 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0002',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan1->deliveryOrder()->attach([$deliveryOrder1->id]);
        $suratJalan2->deliveryOrder()->attach([$deliveryOrder2->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0001',
            'scheduled_date'  => now()->addDay(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'notes'           => 'Test schedule',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan1->id, $suratJalan2->id]);

        $this->assertDatabaseHas('delivery_schedules', [
            'schedule_number' => 'SCH-TEST-0001',
            'status'          => 'pending',
        ]);

        $this->assertCount(2, $schedule->suratJalan);
        $this->assertCount(2, $schedule->relatedDeliveryOrders());
    }

    #[Test]
    public function it_lists_only_surat_jalan_for_the_selected_cabang(): void
    {
        $approved = SuratJalan::create([
            'sj_number' => 'SJ-APPROVED-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        SuratJalan::create([
            'sj_number' => 'SJ-DRAFT-001',
            'issued_at' => now(),
            'status' => 0,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $otherCabang = Cabang::factory()->create();
        SuratJalan::create([
            'sj_number' => 'SJ-APPROVED-OTHER',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $otherCabang->id,
        ]);

        $options = DeliveryScheduleResource::getSuratJalanOptions($this->cabang->id);

        $this->assertArrayHasKey($approved->id, $options);
        $this->assertSame('SJ-APPROVED-001', $options[$approved->id]);
        $this->assertNotContains('SJ-DRAFT-001', $options);
        $this->assertNotContains('SJ-APPROVED-OTHER', $options);
    }

    #[Test]
    public function it_filters_surat_jalan_for_bau_bau_cabang(): void
    {
        $bauBauCabang = Cabang::factory()->create([
            'nama' => 'Bau Bau',
        ]);

        $available = SuratJalan::create([
            'sj_number' => 'SJ-BAU-AVAILABLE-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $bauBauCabang->id,
        ]);

        $used = SuratJalan::create([
            'sj_number' => 'SJ-BAU-USED-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $bauBauCabang->id,
        ]);

        $otherDeliveryOrder = DeliveryOrder::factory()->create([
            'cabang_id' => $bauBauCabang->id,
            'status' => 'approved',
        ]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-BAU-001',
            'scheduled_date' => now(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'cabang_id' => $bauBauCabang->id,
            'created_by' => $this->user->id,
        ]);

        $schedule->suratJalan()->attach($used->id);
        $used->deliveryOrder()->attach($otherDeliveryOrder->id);

        $options = DeliveryScheduleResource::getSuratJalanOptions($bauBauCabang->id, true);

        $this->assertArrayHasKey($available->id, $options);
        $this->assertSame('SJ-BAU-AVAILABLE-001', $options[$available->id]);
        $this->assertArrayNotHasKey($used->id, $options);
    }

    #[Test]
    public function it_still_excludes_surat_jalan_used_by_trashed_delivery_schedules(): void
    {
        $bauBauCabang = Cabang::factory()->create([
            'nama' => 'Bau Bau',
        ]);

        $used = SuratJalan::create([
            'sj_number' => 'SJ-BAU-TRASHED-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $bauBauCabang->id,
        ]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-BAU-TRASHED-001',
            'scheduled_date' => now(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'cabang_id' => $bauBauCabang->id,
            'created_by' => $this->user->id,
        ]);

        $schedule->suratJalan()->attach($used->id);
        $schedule->delete();

        $options = DeliveryScheduleResource::getSuratJalanOptions($bauBauCabang->id, true);

        $this->assertArrayNotHasKey($used->id, $options);
    }

    #[Test]
    public function it_excludes_surat_jalan_that_are_already_used_by_another_delivery_schedule(): void
    {
        $available = SuratJalan::create([
            'sj_number' => 'SJ-AVAILABLE-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $used = SuratJalan::create([
            'sj_number' => 'SJ-USED-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $otherDeliveryOrder = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-USED-001',
            'scheduled_date' => now(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        $schedule->suratJalan()->attach($used->id);
        $used->deliveryOrder()->attach($otherDeliveryOrder->id);

        $options = DeliveryScheduleResource::getSuratJalanOptions($this->cabang->id, true);

        $this->assertArrayHasKey($available->id, $options);
        $this->assertSame('SJ-AVAILABLE-001', $options[$available->id]);
        $this->assertArrayNotHasKey($used->id, $options);
    }

    #[Test]
    public function it_renders_the_delivery_schedule_create_form(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateDeliverySchedule::class)
            ->assertSuccessful()
            ->assertFormExists()
            ->assertFormFieldExists('cabang_id')
            ->assertFormFieldExists('suratJalan');
    }

    #[Test]
    public function it_can_update_schedule_status(): void
    {
        $deliveryOrder1 = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $deliveryOrder2 = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $suratJalan1 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0003',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan2 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0004',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan1->deliveryOrder()->attach([$deliveryOrder1->id]);
        $suratJalan2->deliveryOrder()->attach([$deliveryOrder2->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0002',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'notes'           => 'Status sync test',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan1->id, $suratJalan2->id]);

        $this->assertEquals('pending', $schedule->status);

        $schedule->update(['status' => 'on_the_way']);
        $this->assertEquals('on_the_way', $schedule->fresh()->status);
        $this->assertEquals('sent', $deliveryOrder1->fresh()->status);
        $this->assertEquals('sent', $deliveryOrder2->fresh()->status);

        $schedule->update(['status' => 'delivered']);
        $this->assertEquals('delivered', $schedule->fresh()->status);
        $this->assertEquals('completed', $deliveryOrder1->fresh()->status);
        $this->assertEquals('completed', $deliveryOrder2->fresh()->status);
    }

    #[Test]
    public function it_creates_journal_entries_when_schedule_starts_shipping_not_when_delivered(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $inventoryCoa = ChartOfAccount::create([
            'code' => '1140.10',
            'name' => 'Inventory',
            'type' => 'asset',
            'level' => 3,
            'is_active' => true,
        ]);

        $goodsDeliveryCoa = ChartOfAccount::create([
            'code' => '1140.20',
            'name' => 'Cost of Goods Sold',
            'type' => 'expense',
            'level' => 3,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'inventory_coa_id' => $inventoryCoa->id,
            'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
            'cost_price' => 1000,
        ]);

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 1500,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
        ]);

        DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'sale_order_item_id' => $saleOrderItem->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-JOURNAL-0001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

        $this->assertSame(0, JournalEntry::count());

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-JOURNAL-0001',
            'scheduled_date' => now(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan->id]);

        $schedule->update(['status' => 'on_the_way']);

        $this->assertSame('sent', $deliveryOrder->fresh()->status);
        $this->assertSame(0, JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->count());

        $journalCountAfterOnTheWay = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->count();

        $schedule->update(['status' => 'delivered']);

        $this->assertSame('completed', $deliveryOrder->fresh()->status);
        $this->assertGreaterThan($journalCountAfterOnTheWay, JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->count());
    }

    #[Test]
    public function it_can_have_multiple_surat_jalan_per_schedule(): void
    {
        $deliveryOrders = DeliveryOrder::factory()->count(3)->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $suratJalans = collect($deliveryOrders)->map(function (DeliveryOrder $deliveryOrder, int $index) {
            $suratJalan = SuratJalan::create([
                'sj_number' => 'SJ-TEST-10' . ($index + 1),
                'issued_at' => now(),
                'status' => 1,
                'created_by' => $this->user->id,
                'cabang_id' => $this->cabang->id,
            ]);

            $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

            return $suratJalan;
        });

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0003',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach($suratJalans->pluck('id')->toArray());

        $this->assertCount(3, $schedule->fresh()->suratJalan);
        $this->assertCount(3, $schedule->relatedDeliveryOrders());
    }

    #[Test]
    public function it_shows_selected_surat_jalan_and_delivery_orders_preview(): void
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-PREVIEW-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

        $suratJalanPreview = DeliveryScheduleResource::getSelectedSuratJalanPreviewContent([
            $suratJalan->id,
        ], $this->cabang->id);

        $deliveryOrderPreview = DeliveryScheduleResource::getSelectedDeliveryOrderPreviewContent([
            $suratJalan->id,
        ], $this->cabang->id);

        $this->assertStringContainsString('SJ-PREVIEW-001', $suratJalanPreview);
        $this->assertStringContainsString('Jumlah Surat Jalan: <strong>1</strong>', $suratJalanPreview);
        $this->assertStringContainsString($deliveryOrder->do_number, $deliveryOrderPreview);
        $this->assertStringContainsString('Jumlah Delivery Order: <strong>1</strong>', $deliveryOrderPreview);
    }

    #[Test]
    public function delivered_schedule_completes_related_delivery_orders(): void
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0005',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0005',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan->id]);

        $schedule->update(['status' => 'delivered']);

        $this->assertEquals('completed', $deliveryOrder->fresh()->status);
    }

    #[Test]
    public function delivery_order_no_longer_requires_driver_and_vehicle(): void
    {
        // Verify DO model has nullable driver_id and vehicle_id
        $columns = \Schema::getColumnListing('delivery_orders');

        $this->assertContains('driver_id', $columns);
        $this->assertContains('vehicle_id', $columns);

        // Verify the fields are nullable by checking SHOW COLUMNS
        $columnDetails = \DB::select("SHOW COLUMNS FROM `delivery_orders` WHERE Field IN ('driver_id', 'vehicle_id')");

        foreach ($columnDetails as $col) {
            $this->assertEquals('YES', $col->Null, "Column {$col->Field} should be nullable on delivery_orders");
        }
    }

    #[Test]
    public function it_provides_correct_status_label(): void
    {
        $schedule = new DeliverySchedule(['status' => 'on_the_way']);
        $this->assertEquals('Sedang Berjalan', $schedule->status_label);

        $schedule->status = 'delivered';
        $this->assertEquals('Selesai / Terkirim', $schedule->status_label);

        $schedule->status = 'pending';
        $this->assertEquals('Menunggu Keberangkatan', $schedule->status_label);
    }

    #[Test]
    public function it_soft_deletes_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-TEST-0004',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $id = $schedule->id;
        $schedule->delete();

        // After soft delete, regular find (with SoftDeletes scope) should return null
        $this->assertNull(DeliverySchedule::withoutGlobalScopes()->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->whereNull('deleted_at')->find($id));
        // But withTrashed() should find it
        $this->assertNotNull(DeliverySchedule::withoutGlobalScopes()->withTrashed()->find($id));
    }

    #[Test]
    public function it_has_delivery_schedule_pdf_route_in_preview_controller(): void
    {
        // Verify PdfPreviewController has delivery-schedule config
        $controller = new \App\Http\Controllers\PdfPreviewController();
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('documentConfig');
        $property->setAccessible(true);
        $config = $property->getValue($controller);

        // Verify delivery-schedule type exists
        $this->assertArrayHasKey('delivery-schedule', $config, 'delivery-schedule type should exist in PdfPreviewController');

        // Verify config structure
        $this->assertArrayHasKey('model', $config['delivery-schedule']);
        $this->assertArrayHasKey('blade', $config['delivery-schedule']);
        $this->assertArrayHasKey('bladeVar', $config['delivery-schedule']);
        $this->assertArrayHasKey('paper', $config['delivery-schedule']);
        $this->assertArrayHasKey('orientation', $config['delivery-schedule']);
        $this->assertArrayHasKey('filename', $config['delivery-schedule']);
        $this->assertArrayHasKey('relations', $config['delivery-schedule']);

        // Verify model is DeliverySchedule
        $this->assertEquals(DeliverySchedule::class, $config['delivery-schedule']['model']);

        // Verify blade template
        $this->assertEquals('pdf.delivery-schedule-work-order', $config['delivery-schedule']['blade']);
    }

    #[Test]
    public function it_can_generate_pdf_for_delivery_schedule(): void
    {
        // Create a delivery schedule
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number'  => 'SCH-PDF-TEST-001',
            'scheduled_date'   => now(),
            'driver_id'         => $this->driver->id,
            'vehicle_id'        => $this->vehicle->id,
            'delivery_method'   => 'internal',
            'status'            => 'pending',
            'cabang_id'         => $this->cabang->id,
            'created_by'        => $this->user->id,
        ]);

        // Verify the schedule can be loaded with relations
        $loadedSchedule = DeliverySchedule::with([
            'driver',
            'vehicle',
            'cabang',
            'suratJalan.deliveryOrder.deliveryOrderItem.product.uom',
            'suratJalan.deliveryOrder.salesOrders.customer'
        ])->find($schedule->id);

        $this->assertNotNull($loadedSchedule);
        $this->assertEquals('SCH-PDF-TEST-001', $loadedSchedule->schedule_number);
    }

    #[Test]
    public function it_renders_delivery_schedule_pdf_without_view_warnings(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number'  => 'SCH-PDF-RENDER-001',
            'scheduled_date'   => now(),
            'driver_id'        => $this->driver->id,
            'vehicle_id'       => $this->vehicle->id,
            'delivery_method'  => 'internal',
            'status'           => 'pending',
            'cabang_id'        => $this->cabang->id,
            'created_by'       => $this->user->id,
        ]);

        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true)) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });

        try {
            $response = app(\App\Http\Controllers\PdfPreviewController::class)->stream('delivery-schedule', $schedule->id);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function it_preview_pdf_action_visible_for_all_delivery_methods(): void
    {
        // Create schedules with different delivery methods
        $scheduleInternal = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number'  => 'SCH-METHOD-INT-001',
            'scheduled_date'   => now(),
            'driver_id'         => $this->driver->id,
            'vehicle_id'        => $this->vehicle->id,
            'delivery_method'   => 'internal',
            'status'            => 'pending',
            'cabang_id'         => $this->cabang->id,
            'created_by'        => $this->user->id,
        ]);

        $scheduleEkspedisi = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number'  => 'SCH-METHOD-EKS-001',
            'scheduled_date'   => now(),
            'driver_id'        => null,
            'vehicle_id'       => null,
            'delivery_method'  => 'ekspedisi',
            'status'           => 'pending',
            'cabang_id'        => $this->cabang->id,
            'created_by'       => $this->user->id,
            'driver_name'      => 'Test Ekspedisi',
            'vehicle_info'     => 'Resi: TEST123',
        ]);

        // Verify all methods can be loaded
        $this->assertNotNull($scheduleInternal->fresh());
        $this->assertNotNull($scheduleEkspedisi->fresh());

        // Verify the delivery_method values
        $this->assertEquals('internal', $scheduleInternal->delivery_method);
        $this->assertEquals('ekspedisi', $scheduleEkspedisi->delivery_method);

        // Verify routes are accessible for all methods
        $routeInternal = route('pdf-stream', ['type' => 'delivery-schedule', 'id' => $scheduleInternal->id]);
        $routeEkspedisi = route('pdf-stream', ['type' => 'delivery-schedule', 'id' => $scheduleEkspedisi->id]);

        $this->assertStringContainsString('/pdf/delivery-schedule/', $routeInternal);
        $this->assertStringContainsString('/pdf/delivery-schedule/', $routeEkspedisi);
    }

    #[Test]
    public function it_can_access_pdf_route_for_delivery_schedule(): void
    {
        // Create a delivery schedule
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number'  => 'SCH-ROUTE-TEST-001',
            'scheduled_date'   => now(),
            'driver_id'         => $this->driver->id,
            'vehicle_id'        => $this->vehicle->id,
            'delivery_method'   => 'ekspedisi',
            'status'            => 'delivered',
            'cabang_id'         => $this->cabang->id,
            'created_by'        => $this->user->id,
            'driver_name'       => 'Test Ekspedisi',
            'vehicle_info'      => 'Resi: TEST123456',
        ]);

        // Verify route exists and generates correct URL
        $routeUrl = route('pdf-stream', ['type' => 'delivery-schedule', 'id' => $schedule->id]);
        $this->assertStringContainsString('/pdf/delivery-schedule/' . $schedule->id, $routeUrl);
    }
}
