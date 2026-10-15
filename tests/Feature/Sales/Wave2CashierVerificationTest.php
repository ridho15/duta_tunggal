<?php

namespace Tests\Feature\Sales;

use App\Models\ApprovalOverride;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\SaleOrderCashierVerification;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gelombang 2 (DO), B11: Verifikasi Kasir COD dikaitkan dengan saldo titip customer (wajib cukup, otomatis dipotong, dikembalikan saat batal).
 */
class Wave2CashierVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private Customer $customer;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create();
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id, 'tipe_pembayaran' => 'COD (Bayar Lunas)']);
        foreach (['Kasir', 'Owner'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->cashier = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->cashier->assignRole('Kasir');
        Auth::login($this->cashier);
    }

    private function deposit(float $amount): Deposit
    {
        $coa = ChartOfAccount::create(['code' => '2130.' . random_int(10, 99), 'name' => 'Titipan Pelanggan', 'type' => 'Liability', 'is_active' => true]);

        return Deposit::create([
            'from_model_type' => Customer::class, 'from_model_id' => $this->customer->id, 'coa_id' => $coa->id, 'created_by' => $this->cashier->id,
            'amount' => $amount, 'used_amount' => 0, 'remaining_amount' => $amount, 'status' => 'active',
        ]);
    }

    private function saleOrder(float $total = 500000, float $shipping = 0, string $status = 'approved'): SaleOrder
    {
        return SaleOrder::create([
            'so_number' => 'SO-W2-' . uniqid(), 'customer_id' => $this->customer->id, 'cabang_id' => $this->cabang->id, 'order_date' => now(),
            'status' => $status, 'tipe_pengiriman' => 'Kirim Langsung', 'created_by' => $this->cashier->id,
            'total_amount' => $total, 'shipping_cost' => $shipping,
        ]);
    }

    public function test_verification_deducts_grand_total_from_deposit_and_logs_it(): void
    {
        $deposit = $this->deposit(1000000);
        $so = $this->saleOrder(500000, 50000);

        $result = app(SaleOrderCashierVerification::class)->verify($so, 'Titipan diterima');

        $this->assertEquals(550000.0, $result['used']);
        $this->assertFalse($result['overridden']);
        $this->assertEquals(450000.0, (float) $deposit->fresh()->remaining_amount);
        $this->assertEquals(550000.0, (float) $deposit->fresh()->used_amount);
        $this->assertNotNull($so->fresh()->cashier_approved_at);
        $this->assertDatabaseHas('deposit_logs', ['deposit_id' => $deposit->id, 'reference_id' => $so->id, 'amount' => 550000, 'type' => 'use']);
    }

    public function test_verification_is_rejected_when_deposit_is_short_and_nothing_changes(): void
    {
        $deposit = $this->deposit(300000);
        $so = $this->saleOrder(500000);

        try {
            app(SaleOrderCashierVerification::class)->verify($so);
            $this->fail('seharusnya ditolak');
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->implode(' ');
            $this->assertStringContainsString('kurang Rp 200.000', $message);
            $this->assertStringContainsString('Saldo Titip', $message);
        }

        $this->assertNull($so->fresh()->cashier_approved_at);
        $this->assertEquals(300000.0, (float) $deposit->fresh()->remaining_amount);
        $this->assertDatabaseMissing('deposit_logs', ['reference_id' => $so->id, 'type' => 'use']);
    }

    public function test_verification_without_any_deposit_is_rejected(): void
    {
        $so = $this->saleOrder(500000);

        $this->expectException(ValidationException::class);
        app(SaleOrderCashierVerification::class)->verify($so);
    }

    public function test_cashier_cannot_override_a_short_deposit_but_owner_can_with_reason(): void
    {
        $deposit = $this->deposit(300000);
        $so = $this->saleOrder(500000);
        $service = app(SaleOrderCashierVerification::class);

        // Kasir: alasan pengecualian diabaikan (bukan peran penguasa)
        try {
            $service->verify($so, null, 'Mohon dispensasi pembayaran saat kirim');
            $this->fail('kasir tidak boleh mengecualikan');
        } catch (ValidationException) {
            $this->assertNull($so->fresh()->cashier_approved_at);
        }

        $owner = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $owner->assignRole('Owner');

        // Owner tanpa alasan cukup panjang: ditolak
        try {
            $service->verify($so, null, 'singkat', $owner);
            $this->fail('alasan terlalu pendek');
        } catch (ValidationException) {
            $this->assertNull($so->fresh()->cashier_approved_at);
        }

        // Owner dengan alasan >= 10 karakter: lolos, saldo yang ada dipakai habis, tercatat sebagai pengecualian
        $result = $service->verify($so, null, 'Customer langganan, sisa dibayar saat pengiriman', $owner);

        $this->assertTrue($result['overridden']);
        $this->assertEquals(300000.0, $result['used']);
        $this->assertEquals(200000.0, $result['shortfall']);
        $this->assertEquals(0.0, (float) $deposit->fresh()->remaining_amount);
        $this->assertNotNull($so->fresh()->cashier_approved_at);
        $this->assertSame(1, ApprovalOverride::where('document_id', $so->id)->where('user_id', $owner->id)->count());
    }

    public function test_verification_cannot_run_twice_or_on_non_cod_orders(): void
    {
        $this->deposit(2000000);
        $so = $this->saleOrder(500000);
        $service = app(SaleOrderCashierVerification::class);
        $service->verify($so);

        try {
            $service->verify($so->fresh());
            $this->fail('verifikasi ganda');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah diverifikasi', collect($e->errors())->flatten()->implode(' '));
        }

        $this->customer->update(['tipe_pembayaran' => 'Kredit']);
        $this->expectException(ValidationException::class);
        $service->verify($this->saleOrder(100000));
    }

    public function test_two_verifications_cannot_spend_the_same_deposit(): void
    {
        $deposit = $this->deposit(600000);
        $first = $this->saleOrder(500000);
        $second = $this->saleOrder(500000);
        $service = app(SaleOrderCashierVerification::class);

        $service->verify($first);

        try {
            $service->verify($second);
            $this->fail('saldo tersisa 100.000 tidak cukup untuk SO kedua');
        } catch (ValidationException) {
        }

        $this->assertEquals(100000.0, (float) $deposit->fresh()->remaining_amount);
        $this->assertNull($second->fresh()->cashier_approved_at);
    }

    public function test_cancelling_the_sale_order_returns_the_deposit_exactly_once(): void
    {
        $deposit = $this->deposit(1000000);
        $so = $this->saleOrder(500000, 50000);
        app(SaleOrderCashierVerification::class)->verify($so);
        $this->assertEquals(450000.0, (float) $deposit->fresh()->remaining_amount);

        app(SalesOrderService::class)->cancel($so->fresh());

        $this->assertSame('canceled', $so->fresh()->status);
        $this->assertEquals(1000000.0, (float) $deposit->fresh()->remaining_amount);
        $this->assertDatabaseHas('deposit_logs', ['deposit_id' => $deposit->id, 'reference_id' => $so->id, 'type' => 'return', 'amount' => 550000]);

        // Pengembalian idempoten
        $this->assertEquals(0.0, app(SaleOrderCashierVerification::class)->refund($so->fresh()));
        $this->assertEquals(1000000.0, (float) $deposit->fresh()->remaining_amount);
    }
}
