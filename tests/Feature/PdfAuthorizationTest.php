<?php

/**
 * Rute PDF diotorisasi PER DOKUMEN (policy `view`), bukan hanya login: pengguna tanpa izin `view <dokumen>` mendapat 403 untuk
 * setiap jenis pada /pdf/{type}/{id} dan /pdf/customer-return/{id}; pengguna berizin mendapat PDF; dokumen tak ada tetap 404.
 */

use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    \App\Models\Currency::factory()->create(['code' => 'IDR']);
    $this->cabang = \App\Models\Cabang::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->supplier = Supplier::factory()->create();

    Pdf::shouldReceive('loadView')->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('stream')->andReturn(response('', 200)->header('Content-Type', 'application/pdf'));
});

/** @return array<string, array{0: string, 1: string, 2: \Closure}> [jenis => [rute, izin, pembuat dokumen]] */
function pdfAuthCases(): array
{
    $so = fn () => SaleOrder::factory()->create(['customer_id' => test()->customer->id, 'cabang_id' => test()->cabang->id]);
    $po = fn () => PurchaseOrder::factory()->create(['supplier_id' => test()->supplier->id]);

    return [
        'order-request' => ['order-request', 'view order request', fn () => OrderRequest::factory()->create()],
        'purchase-order' => ['purchase-order', 'view purchase order', $po],
        'purchase-invoice' => ['purchase-invoice', 'view invoice', fn () => Invoice::factory()->create(['from_model_type' => PurchaseOrder::class, 'from_model_id' => $po()->id, 'cabang_id' => test()->cabang->id])],
        'quotation' => ['quotation', 'view quotation', fn () => Quotation::factory()->create(['customer_id' => test()->customer->id])],
        'sale-order' => ['sale-order', 'view sales order', $so],
        'sales-invoice' => ['sales-invoice', 'view invoice', fn () => Invoice::factory()->create(['from_model_type' => SaleOrder::class, 'from_model_id' => $so()->id, 'cabang_id' => test()->cabang->id])],
        'delivery-order' => ['delivery-order', 'view delivery order', fn () => DeliveryOrder::factory()->create(['cabang_id' => test()->cabang->id])],
        'delivery-schedule' => ['delivery-schedule', 'view delivery schedule', fn () => DeliverySchedule::factory()->create()],
        'surat-jalan' => ['surat-jalan', 'view surat jalan', fn () => SuratJalan::factory()->create()],
    ];
}

function pdfAuthUser(array $permissions = []): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    return $user;
}

it('pdf-stream: pengguna login TANPA izin view mendapat 403 untuk setiap jenis dokumen; dengan izin mendapat PDF', function () {
    foreach (pdfAuthCases() as $type => [$route, $permission, $make]) {
        $record = $make();
        $url = route('pdf-stream', ['type' => $route, 'id' => $record->id]);

        test()->actingAs(pdfAuthUser())->get($url)->assertForbidden();
        $otherPermissions = array_diff(['view order request', 'view purchase order', 'view quotation', 'view sales order', 'view delivery order', 'view invoice', 'view delivery schedule', 'view surat jalan', 'view customer return'], [$permission]);
        test()->actingAs(pdfAuthUser($otherPermissions))->get($url)->assertForbidden();   // punya izin dokumen LAIN, bukan yang ini

        $ok = test()->actingAs(pdfAuthUser([$permission]))->get($url);
        expect($ok->status())->toBe(200, "{$type}: pengguna berizin '{$permission}' harus mendapat PDF")->and($ok->headers->get('content-type'))->toContain('application/pdf');
    }
});

it('customer-return: 403 tanpa izin view customer return; PDF dengan izin', function () {
    $invoice = Invoice::factory()->create(['from_model_type' => SaleOrder::class, 'from_model_id' => SaleOrder::factory()->create(['customer_id' => $this->customer->id, 'cabang_id' => $this->cabang->id])->id, 'cabang_id' => $this->cabang->id]);
    $return = CustomerReturn::create([
        'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $invoice->id, 'customer_id' => $this->customer->id, 'cabang_id' => $this->cabang->id,
        'return_date' => now()->toDateString(), 'reason' => 'Unit cacat produksi', 'status' => CustomerReturn::STATUS_PENDING,
    ]);
    $url = route('pdf-customer-return', ['id' => $return->id]);

    $this->actingAs(pdfAuthUser(['view invoice', 'view sales order']))->get($url)->assertForbidden();
    $this->actingAs(pdfAuthUser(['view customer return']))->get($url)->assertOk();
});

it('delivery-order: policy Sales — hanya DO dari SO buatannya sendiri; peran lain berizin bebas', function () {
    $owner = pdfAuthUser(['view delivery order']);
    $owner->assignRole(Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']));
    $so = SaleOrder::factory()->create(['customer_id' => $this->customer->id, 'cabang_id' => $this->cabang->id, 'created_by' => $owner->id]);
    $mine = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id]);
    $mine->salesOrders()->attach($so->id);
    $others = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id]);

    $this->actingAs($owner)->get(route('pdf-stream', ['type' => 'delivery-order', 'id' => $mine->id]))->assertOk();
    $this->actingAs($owner)->get(route('pdf-stream', ['type' => 'delivery-order', 'id' => $others->id]))->assertForbidden();
});

it('tanpa login dialihkan ke login; dokumen tak ada tetap 404 untuk pengguna berizin', function () {
    $this->get(route('pdf-stream', ['type' => 'quotation', 'id' => 1]))->assertRedirect('/login');
    $this->get(route('pdf-customer-return', ['id' => 1]))->assertRedirect('/login');

    $this->actingAs(pdfAuthUser(['view quotation', 'view customer return']));
    $this->get(route('pdf-stream', ['type' => 'quotation', 'id' => 99999]))->assertNotFound();
    $this->get(route('pdf-customer-return', ['id' => 99999]))->assertNotFound();
});
