<?php

namespace Tests\Feature;

use App\Models\ApprovalOverride;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SuratJalanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalAuditStage1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Currency::firstOrCreate(
            ['id' => 1],
            ['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1.0, 'is_default' => 1]
        );

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Finance Manager', 'guard_name' => 'web']);
    }

    #[Test]
    public function it_allows_saving_so_as_request_approve_when_credit_limit_is_exceeded(): void
    {
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create(['cabang_id' => $cabang->id]);
        $user->assignRole('Sales');

        $customer = Customer::factory()->create([
            'tipe_pembayaran' => 'Kredit',
            'kredit_limit' => 5000000,
            'cabang_id' => $cabang->id,
        ]);

        $product = Product::factory()->create([
            'sell_price' => 10000000,
            'cost_price' => 8000000,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('penjualan.so.store'), [
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'order_date' => now()->toDateString(),
            'tipe_pengiriman' => 'Ambil Sendiri',
            'currency_id' => 1,
            'action' => 'request_approve',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000000,
                ],
            ],
        ]);

        // Must succeed with redirect to show and warning flash
        $response->assertRedirect();
        $so = SaleOrder::latest('id')->first();
        $this->assertNotNull($so);
        $this->assertEquals('request_approve', $so->status);
        $this->assertStringContainsString('PERINGATAN PLAFON KREDIT', $so->notes);
    }

    #[Test]
    public function it_enforces_super_admin_or_finance_override_for_over_credit_so_approval(): void
    {
        $cabang = Cabang::factory()->create();
        $superAdmin = User::factory()->create(['cabang_id' => $cabang->id]);
        $superAdmin->assignRole('Super Admin');

        $sales = User::factory()->create(['cabang_id' => $cabang->id]);
        $sales->assignRole('Sales');

        $customer = Customer::factory()->create([
            'tipe_pembayaran' => 'Kredit',
            'kredit_limit' => 5000000,
            'cabang_id' => $cabang->id,
        ]);

        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'total_amount' => 10000000,
            'status' => 'request_approve',
        ]);

        // 1. Regular sales staff attempting approval is blocked
        $this->actingAs($sales);
        $resDenied = $this->post(route('penjualan.so.approve', $so));
        $resDenied->assertRedirect();
        $this->assertEquals('request_approve', $so->fresh()->status);

        // 2. Super Admin without valid override reason (empty or < 10 chars) is blocked
        $this->actingAs($superAdmin);
        $resNoReason = $this->post(route('penjualan.so.approve', $so), [
            'credit_override_reason' => 'acc',
        ]);
        $resNoReason->assertSessionHas('error');
        $this->assertEquals('request_approve', $so->fresh()->status);

        // 3. Super Admin with valid override reason (>= 10 chars) successfully approves
        $resApproved = $this->post(route('penjualan.so.approve', $so), [
            'credit_override_reason' => 'Disetujui direksi untuk proyek percontohan',
        ]);
        $resApproved->assertSessionHas('success');
        $this->assertEquals('approved', $so->fresh()->status);

        // Verify ApprovalOverride record was created
        $override = ApprovalOverride::where('document_type', 'sale_order')
            ->where('document_id', $so->id)
            ->first();
        $this->assertNotNull($override);
        $this->assertEquals('Disetujui direksi untuk proyek percontohan', $override->reason);
    }

    #[Test]
    public function it_protects_pos_terminal_and_shift_routes_with_rbac(): void
    {
        $cabang = Cabang::factory()->create();
        $auditor = User::factory()->create(['cabang_id' => $cabang->id]);
        $auditor->assignRole('Auditor');

        $kasir = User::factory()->create(['cabang_id' => $cabang->id]);
        $kasir->assignRole('Kasir');

        // Auditor is forbidden (403)
        $this->actingAs($auditor);
        $resAuditorPos = $this->get(route('kasir.pos.index'));
        $resAuditorPos->assertStatus(403);

        $resAuditorShift = $this->get(route('kasir.shift.index'));
        $resAuditorShift->assertStatus(403);

        // Kasir is allowed (200)
        $this->actingAs($kasir);
        $resKasirPos = $this->get(route('kasir.pos.index'));
        $resKasirPos->assertStatus(200);

        $resKasirShift = $this->get(route('kasir.shift.index'));
        $resKasirShift->assertStatus(200);
    }

    #[Test]
    public function it_allows_super_admin_to_force_close_a_stuck_open_shift(): void
    {
        $cabang = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        $cashierBudi = User::factory()->create(['name' => 'Budi Kasir', 'cabang_id' => $cabang->id]);
        $cashierBudi->assignRole('Kasir');

        $superAdmin = User::factory()->create(['name' => 'Pak Joko Super Admin', 'cabang_id' => $cabang->id]);
        $superAdmin->assignRole('Super Admin');

        // Budi opens a shift with starting cash 200,000
        $shift = PosShift::create([
            'shift_number' => 'SHF-TEST-001',
            'user_id' => $cashierBudi->id,
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
            'opened_at' => Carbon::now()->subHours(8),
            'starting_cash' => 200000,
            'status' => 'open',
        ]);

        $this->assertEquals('open', $shift->fresh()->status);

        // Super Admin force closes the shift with actual physical cash 250,000
        $this->actingAs($superAdmin);

        $response = $this->post(route('kasir.shift.forceClose', $shift), [
            'cash_actual' => 250000,
            'notes' => 'Kasir sakit mendadak, uang laci diverifikasi supervisor',
        ]);

        $response->assertRedirect(route('kasir.shift.summary', $shift));
        $closedShift = $shift->fresh();
        $this->assertEquals('closed', $closedShift->status);
        $this->assertEquals(250000, (float) $closedShift->cash_actual);
        $this->assertStringContainsString('INTERVENSI ADMIN', $closedShift->notes);
    }

    #[Test]
    public function it_allows_cancelling_issued_surat_jalan_and_reverts_do_status(): void
    {
        $cabang = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $superAdmin = User::factory()->create(['cabang_id' => $cabang->id]);
        $superAdmin->assignRole('Super Admin');

        $do = DeliveryOrder::factory()->create([
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'received', // Marked received upon SJ issuance
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-TEST-001',
            'cabang_id' => $cabang->id,
            'status' => SuratJalan::STATUS_ISSUED,
            'issued_at' => now(),
            'created_by' => $superAdmin->id,
        ]);
        $suratJalan->deliveryOrder()->attach($do->id);

        $this->actingAs($superAdmin);

        $service = app(SuratJalanService::class);
        $cancelled = $service->cancel($suratJalan, 'Armada mogok di area pabrik sebelum berangkat', $superAdmin->id);

        $this->assertEquals(SuratJalan::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals('confirmed', $do->fresh()->status);
    }
}
