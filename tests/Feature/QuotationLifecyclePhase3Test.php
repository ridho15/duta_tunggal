<?php

/**
 * Fase 3 audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md), Isu 4:
 *  - quotation terkunci setelah menunggu persetujuan / disetujui / kedaluwarsa (policy, halaman, tabel, API)
 *  - kedaluwarsa (status "expired" + job harian + guard tanggal), created_by selalu tercatat
 *  - revisi lewat versi baru (-R1, -R2, ...), versi lama digantikan saat revisi disetujui
 *  - T1: API tidak lagi dapat melewati alur persetujuan
 */

use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Pages\EditQuotation;
use App\Filament\Resources\QuotationResource\Pages\ListQuotations;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const P3_ALL_PERMISSIONS = [
    'view any quotation', 'view quotation', 'create quotation', 'update quotation', 'delete quotation',
    'request-approve quotation', 'approve quotation',
    'create sales order', 'view any sales order', 'view sales order', 'update sales order',
];

function phase3Context(string $status = 'draft', array $quotation = [], ?array $permissions = null): array
{
    $cabang = Cabang::factory()->create(['kode' => 'P3-' . strtoupper(substr(uniqid(), -6)), 'nama' => 'Cabang Fase 3', 'status' => 1]);

    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions ?? P3_ALL_PERMISSIONS);
    Auth::login($user);

    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

    $customer = Customer::factory()->create([
        'cabang_id' => $cabang->id,
        'tempo_kredit' => 30,
        'address' => 'Jl. Master Customer No. 1, Jakarta',
        'tipe_pembayaran' => 'Bebas',
    ]);

    $product = Product::factory()->create(['name' => 'Alat Medis Fase 3', 'sku' => 'AMD3-' . strtoupper(substr(uniqid(), -7)), 'sell_price' => 8687]);

    $model = Quotation::create(array_merge([
        'quotation_number' => 'QO-P3-' . strtoupper(substr(uniqid(), -8)),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'currency_id' => $idr->id,
        'exchange_rate' => 1,
        'tempo_pembayaran' => 30,
        'notes' => 'Catatan Fase 3',
        'status' => $status,
        'created_by' => $user->id,
        'total_amount' => 183208.83,
    ], $quotation));

    QuotationItem::create([
        'quotation_id' => $model->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_price' => 8687,
        'discount' => 5,
        'tax' => 11,
        'tax_type' => 'eksklusif',
    ]);

    return compact('cabang', 'user', 'idr', 'customer', 'product') + ['quotation' => $model];
}

function phase3ApiPayload(array $ctx, array $header = []): array
{
    return [
        'header' => array_merge([
            'quotation_number' => 'QO-P3-API-' . strtoupper(substr(uniqid(), -8)),
            'customer_id' => $ctx['customer']->id,
            'cabang_id' => $ctx['cabang']->id,
            'date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'currency_id' => $ctx['idr']->id,
            'tempo_pembayaran' => 30,
            'notes' => 'Via API',
        ], $header),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'discount' => 0,
            'tax_type' => 'Eksklusif',
            'tax' => 11,
        ]],
    ];
}

// ───────────────────────────── Kunci status: policy ─────────────────────────────

it('Isu 4: policy — hanya Draft dan Ditolak yang boleh diubah dan dihapus', function (string $status, bool $allowed) {
    $ctx = phase3Context($status);

    expect($ctx['user']->can('update', $ctx['quotation']))->toBe($allowed)
        ->and($ctx['user']->can('delete', $ctx['quotation']))->toBe($allowed)
        ->and($ctx['quotation']->isEditable())->toBe($allowed);
})->with([
    'draft' => ['draft', true],
    'reject' => ['reject', true],
    'request_approve' => ['request_approve', false],
    'approve' => ['approve', false],
    'expired' => ['expired', false],
]);

it('Isu 4: policy — izin peran tetap dibutuhkan walau status masih Draft', function () {
    $ctx = phase3Context('draft', permissions: ['view any quotation', 'view quotation']);

    expect($ctx['user']->can('update', $ctx['quotation']))->toBeFalse()
        ->and($ctx['user']->can('delete', $ctx['quotation']))->toBeFalse();
});

it('Isu 4: policy revise — hanya Approved/Expired yang belum digantikan, dan butuh izin create quotation', function () {
    $ctx = phase3Context('approve');
    expect($ctx['user']->can('revise', $ctx['quotation']))->toBeTrue();

    $ctx['quotation']->update(['status' => 'expired']);
    expect($ctx['user']->can('revise', $ctx['quotation']->fresh()))->toBeTrue();

    $ctx['quotation']->update(['status' => 'draft']);
    expect($ctx['user']->can('revise', $ctx['quotation']->fresh()))->toBeFalse();

    $ctx['quotation']->update(['status' => 'approve', 'superseded_at' => now()]);
    expect($ctx['user']->can('revise', $ctx['quotation']->fresh()))->toBeFalse();

    $noCreate = phase3Context('approve', permissions: ['view any quotation', 'view quotation']);
    expect($noCreate['user']->can('revise', $noCreate['quotation']))->toBeFalse();
});

// ───────────────────────────── Kunci status: halaman & tabel ─────────────────────────────

it('Isu 4: halaman Edit quotation terkunci diarahkan ke halaman Lihat, Draft tetap dapat dibuka', function () {
    $locked = phase3Context('approve');

    Livewire::actingAs($locked['user'])
        ->test(EditQuotation::class, ['record' => $locked['quotation']->getKey()])
        ->assertRedirect(QuotationResource::getUrl('view', ['record' => $locked['quotation']]));

    $draft = phase3Context('draft');
    Livewire::actingAs($draft['user'])
        ->test(EditQuotation::class, ['record' => $draft['quotation']->getKey()])
        ->assertSuccessful()
        ->assertNoRedirect();
});

it('Isu 4: halaman Lihat menyembunyikan Ubah/Hapus dan menampilkan Buat Revisi untuk quotation Approved', function () {
    $ctx = phase3Context('approve');

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->assertActionHidden('edit')
        ->assertActionHidden('delete')
        ->assertActionVisible('revise');
});

it('Isu 4: halaman Lihat quotation Draft menampilkan Ubah/Hapus dan tidak menampilkan Buat Revisi', function () {
    $ctx = phase3Context('draft');

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->assertActionVisible('edit')
        ->assertActionVisible('delete')
        ->assertActionHidden('revise');
});

it('Isu 4: tabel — Ubah/Hapus hanya tampil untuk Draft/Ditolak, Buat Revisi untuk Approved/Kedaluwarsa', function () {
    $draft = phase3Context('draft');
    $approved = Quotation::create([
        'quotation_number' => 'QO-P3-APP-' . uniqid(), 'customer_id' => $draft['customer']->id, 'cabang_id' => $draft['cabang']->id,
        'date' => now(), 'valid_until' => now()->addDays(10), 'currency_id' => $draft['idr']->id, 'exchange_rate' => 1,
        'status' => 'approve', 'total_amount' => 1000,
    ]);
    $expired = Quotation::create([
        'quotation_number' => 'QO-P3-EXP-' . uniqid(), 'customer_id' => $draft['customer']->id, 'cabang_id' => $draft['cabang']->id,
        'date' => now()->subDays(40), 'valid_until' => now()->subDays(10), 'currency_id' => $draft['idr']->id, 'exchange_rate' => 1,
        'status' => 'expired', 'total_amount' => 1000,
    ]);

    Livewire::actingAs($draft['user'])
        ->test(ListQuotations::class)
        ->assertTableActionVisible('edit', $draft['quotation'])
        ->assertTableActionVisible('delete', $draft['quotation'])
        ->assertTableActionHidden('revise', $draft['quotation'])
        ->assertTableActionHidden('edit', $approved)
        ->assertTableActionHidden('delete', $approved)
        ->assertTableActionVisible('revise', $approved)
        ->assertTableActionHidden('edit', $expired)
        ->assertTableActionVisible('revise', $expired);
});

it('Isu 4: hapus massal hanya menghapus quotation yang masih dapat diubah', function () {
    $ctx = phase3Context('draft');
    $approved = Quotation::create([
        'quotation_number' => 'QO-P3-BULK-' . uniqid(), 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'valid_until' => now()->addDays(10), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1,
        'status' => 'approve', 'total_amount' => 1000,
    ]);

    Livewire::actingAs($ctx['user'])
        ->test(ListQuotations::class)
        ->callTableBulkAction('delete', [$ctx['quotation'], $approved]);

    // withoutGlobalScopes() juga melepas SoftDeletingScope, jadi baris terhapus dicek lewat trashed()
    expect(Quotation::withoutGlobalScopes()->find($ctx['quotation']->id)->trashed())->toBeTrue()
        ->and(Quotation::withoutGlobalScopes()->find($approved->id)->trashed())->toBeFalse();
});

// ───────────────────────────── Kedaluwarsa ─────────────────────────────

it('Isu 4: batas kedaluwarsa — valid_until hari ini masih berlaku, kemarin sudah kedaluwarsa', function () {
    $ctx = phase3Context('approve', ['valid_until' => now()->toDateString()]);
    expect($ctx['quotation']->fresh()->isExpired())->toBeFalse()
        ->and($ctx['quotation']->fresh()->unusableReasonForSaleOrder())->toBeNull();

    $ctx['quotation']->update(['valid_until' => now()->subDay()->toDateString()]);
    expect($ctx['quotation']->fresh()->isExpired())->toBeTrue()
        ->and($ctx['quotation']->fresh()->unusableReasonForSaleOrder())->toContain('kedaluwarsa');

    // Tanpa valid_until = tidak pernah kedaluwarsa
    $ctx['quotation']->update(['valid_until' => null]);
    expect($ctx['quotation']->fresh()->isExpired())->toBeFalse();
});

it('Isu 4: label status Kedaluwarsa dan status lain memakai satu peta label', function () {
    expect(Quotation::STATUS_LABELS['expired'])->toBe('Kedaluwarsa')
        ->and(QuotationResource::quotationStatusLabel('expired'))->toBe('Kedaluwarsa')
        ->and(QuotationResource::quotationStatusLabel('approve'))->toBe('Disetujui');
});

it('Isu 4: scope usable — hanya Approved, belum digantikan, dan belum kedaluwarsa', function () {
    $ctx = phase3Context('approve');
    $base = [
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(),
        'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'total_amount' => 1000,
    ];
    $expiredByDate = Quotation::create($base + ['quotation_number' => 'QO-P3-U1', 'status' => 'approve', 'valid_until' => now()->subDays(3)]);
    $expiredStatus = Quotation::create($base + ['quotation_number' => 'QO-P3-U2', 'status' => 'expired', 'valid_until' => now()->subDays(3)]);
    $superseded = Quotation::create($base + ['quotation_number' => 'QO-P3-U3', 'status' => 'approve', 'valid_until' => now()->addDays(3), 'superseded_at' => now()]);
    $draft = Quotation::create($base + ['quotation_number' => 'QO-P3-U4', 'status' => 'draft', 'valid_until' => now()->addDays(3)]);
    $noExpiry = Quotation::create($base + ['quotation_number' => 'QO-P3-U5', 'status' => 'approve', 'valid_until' => null]);

    $usableIds = Quotation::withoutGlobalScopes()->usable()->pluck('id')->all();

    expect($usableIds)->toContain($ctx['quotation']->id, $noExpiry->id)
        ->not->toContain($expiredByDate->id, $expiredStatus->id, $superseded->id, $draft->id);
});

it('Isu 4: quotations:expire — dry-run tidak mengubah, tanpa dry-run menandai kedaluwarsa dan mengisi expired_at', function () {
    $ctx = phase3Context('approve', ['valid_until' => now()->subDays(2)->toDateString()]);
    $fresh = Quotation::create([
        'quotation_number' => 'QO-P3-FRESH', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(),
        'valid_until' => now()->toDateString(), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'approve', 'total_amount' => 1,
    ]);
    $draftOverdue = Quotation::create([
        'quotation_number' => 'QO-P3-DRAFTOLD', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(),
        'valid_until' => now()->subDays(5)->toDateString(), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'draft', 'total_amount' => 1,
    ]);

    Artisan::call('quotations:expire', ['--dry-run' => true]);
    expect(Artisan::output())->toContain($ctx['quotation']->quotation_number)
        ->and($ctx['quotation']->fresh()->status)->toBe('approve')
        ->and($ctx['quotation']->fresh()->expired_at)->toBeNull();

    Artisan::call('quotations:expire');
    $expired = $ctx['quotation']->fresh();
    expect($expired->status)->toBe('expired')
        ->and($expired->expired_at)->not->toBeNull()
        ->and($fresh->fresh()->status)->toBe('approve')          // jatuh tempo HARI INI: belum kedaluwarsa
        ->and($draftOverdue->fresh()->status)->toBe('draft');    // hanya Approved yang dikedaluwarsakan
});

it('Isu 4: quotations:expire tidak menyentuh quotation yang sudah dihapus (soft delete)', function () {
    $ctx = phase3Context('approve', ['valid_until' => now()->subDays(2)->toDateString()]);
    $ctx['quotation']->delete();

    Artisan::call('quotations:expire');

    $row = Quotation::withTrashed()->find($ctx['quotation']->id);
    expect($row->status)->toBe('approve')->and($row->expired_at)->toBeNull();
});

it('Isu 4: job quotations:expire terdaftar di scheduler harian (routes/console.php)', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'quotations:expire'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('10 0 * * *');
});

it('Isu 4: quotation kedaluwarsa tidak dapat dijadikan SO — tombol, handler, dan daftar pilihan', function () {
    $ctx = phase3Context('approve', ['valid_until' => now()->subDay()->toDateString()]);   // status masih approve, job belum jalan

    expect(QuotationResource::canCreateSaleOrder($ctx['quotation']->fresh()))->toBeFalse();

    expect(fn () => QuotationResource::createSaleOrderFromQuotation($ctx['quotation']->fresh(), [
        'so_number' => 'SO-EXP-1', 'order_date' => now()->format('Y-m-d'), 'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Alamat', 'notes' => '',
    ]))->toThrow(ValidationException::class);

    expect(SaleOrder::withoutGlobalScopes()->where('so_number', 'SO-EXP-1')->exists())->toBeFalse();
});

it('Isu 4: aksi "Buat Sales Order" di tabel tidak tampil untuk quotation kedaluwarsa', function () {
    $ctx = phase3Context('expired', ['valid_until' => now()->subDays(5)->toDateString()]);

    Livewire::actingAs($ctx['user'])
        ->test(ListQuotations::class)
        ->assertTableActionHidden('create_sale_order', $ctx['quotation']);
});

it('Isu 4: approve quotation yang sudah lewat masa berlaku ditolak', function () {
    $ctx = phase3Context('request_approve', ['valid_until' => now()->subDay()->toDateString()]);

    expect(fn () => app(QuotationService::class)->approve($ctx['quotation']))->toThrow(ValidationException::class);
    expect($ctx['quotation']->fresh()->status)->toBe('request_approve');

    // Valid_until hari ini masih boleh disetujui
    $ctx['quotation']->update(['valid_until' => now()->toDateString()]);
    app(QuotationService::class)->approve($ctx['quotation']->fresh());
    expect($ctx['quotation']->fresh()->status)->toBe('approve');
});

it('Isu 4: API SO — getQuotation dan store menolak quotation kedaluwarsa; quotation valid tetap lolos', function () {
    $expired = phase3Context('approve', ['valid_until' => now()->subDay()->toDateString()]);

    $this->actingAs($expired['user'])->getJson("/api/v1/sales-orders/quotation/{$expired['quotation']->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('quotation_id');

    $payload = [
        'header' => [
            'so_number' => 'SO-P3-EXP', 'customer_id' => $expired['customer']->id, 'cabang_id' => $expired['cabang']->id,
            'quotation_id' => $expired['quotation']->id, 'order_date' => now()->format('Y-m-d'),
            'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $expired['idr']->id, 'exchange_rate' => 1,
        ],
        'items' => [['product_id' => $expired['product']->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax_type' => 'eksklusif', 'tax' => 11]],
    ];
    $this->actingAs($expired['user'])->postJson('/api/v1/sales-orders', $payload)->assertStatus(422);
    expect(SaleOrder::withoutGlobalScopes()->where('so_number', 'SO-P3-EXP')->exists())->toBeFalse();

    $expired['quotation']->update(['valid_until' => now()->addDay()->toDateString()]);
    $this->actingAs($expired['user'])->getJson("/api/v1/sales-orders/quotation/{$expired['quotation']->id}")->assertOk();
    $this->actingAs($expired['user'])->postJson('/api/v1/sales-orders', $payload)->assertOk();
});

// ───────────────────────────── T1: API tidak lagi melewati persetujuan ─────────────────────────────

it('T1: API quotation — update quotation Approved/Menunggu/Kedaluwarsa ditolak 422 dan data tidak berubah', function (string $status) {
    $ctx = phase3Context($status);
    $payload = phase3ApiPayload($ctx, ['quotation_number' => $ctx['quotation']->quotation_number, 'notes' => 'Diubah diam-diam']);

    $this->actingAs($ctx['user'])->putJson("/api/v1/quotations/{$ctx['quotation']->id}", $payload)->assertStatus(422);

    $after = $ctx['quotation']->fresh();
    expect($after->notes)->toBe('Catatan Fase 3')
        ->and($after->status)->toBe($status)
        ->and($after->quotationItem()->count())->toBe(1)
        ->and((float) $after->quotationItem()->first()->quantity)->toBe(20.0);
})->with(['approve', 'request_approve', 'expired']);

it('T1: API quotation — update tanpa izin update quotation ditolak 403', function () {
    $ctx = phase3Context('draft', permissions: ['view any quotation', 'view quotation', 'create quotation']);
    $payload = phase3ApiPayload($ctx, ['quotation_number' => $ctx['quotation']->quotation_number]);

    $this->actingAs($ctx['user'])->putJson("/api/v1/quotations/{$ctx['quotation']->id}", $payload)->assertStatus(403);
});

it('T1: API quotation — status "approve"/"reject" dari klien ditolak validasi', function (string $status) {
    $ctx = phase3Context('draft');

    $this->actingAs($ctx['user'])
        ->postJson('/api/v1/quotations', phase3ApiPayload($ctx, ['status' => $status]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('header.status');

    $this->actingAs($ctx['user'])
        ->putJson("/api/v1/quotations/{$ctx['quotation']->id}", phase3ApiPayload($ctx, ['quotation_number' => $ctx['quotation']->quotation_number, 'status' => $status]))
        ->assertStatus(422);

    expect($ctx['quotation']->fresh()->status)->toBe('draft');
})->with(['approve', 'reject']);

it('T1: API quotation — store tanpa izin create quotation ditolak 403', function () {
    $ctx = phase3Context('draft', permissions: ['view any quotation', 'view quotation']);

    $this->actingAs($ctx['user'])->postJson('/api/v1/quotations', phase3ApiPayload($ctx))->assertStatus(403);
});

it('T1: API quotation — request_approve butuh izin request-approve; hasilnya lewat service (pencatat pengaju terisi)', function () {
    $noPermission = phase3Context('draft', permissions: ['view any quotation', 'view quotation', 'create quotation']);
    $this->actingAs($noPermission['user'])
        ->postJson('/api/v1/quotations', phase3ApiPayload($noPermission, ['status' => 'request_approve']))
        ->assertStatus(403);

    $ctx = phase3Context('draft');
    $payload = phase3ApiPayload($ctx, ['status' => 'request_approve']);
    $id = $this->actingAs($ctx['user'])->postJson('/api/v1/quotations', $payload)->assertOk()->json('data.id');

    $created = Quotation::withoutGlobalScopes()->findOrFail($id);
    expect($created->status)->toBe('request_approve')
        ->and($created->request_approve_by)->toBe($ctx['user']->id)
        ->and($created->request_approve_at)->not->toBeNull()
        ->and($created->created_by)->toBe($ctx['user']->id);
});

it('T1: API quotation — simpan tanpa status menghasilkan Draft; Ditolak yang diperbaiki kembali menjadi Draft', function () {
    $ctx = phase3Context('reject');

    $id = $this->actingAs($ctx['user'])->postJson('/api/v1/quotations', phase3ApiPayload($ctx))->assertOk()->json('data.id');
    expect(Quotation::withoutGlobalScopes()->find($id)->status)->toBe('draft');

    $payload = phase3ApiPayload($ctx, ['quotation_number' => $ctx['quotation']->quotation_number, 'notes' => 'Sudah diperbaiki']);
    $this->actingAs($ctx['user'])->putJson("/api/v1/quotations/{$ctx['quotation']->id}", $payload)->assertOk();

    $after = $ctx['quotation']->fresh();
    expect($after->status)->toBe('draft')
        ->and($after->notes)->toBe('Sudah diperbaiki');
});

it('T1: API sales order — status "approved" dari klien ditolak; update SO yang sudah lewat Draft ditolak 422', function () {
    $ctx = phase3Context('approve');

    $payload = [
        'header' => [
            'so_number' => 'SO-P3-BYPASS', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
            'order_date' => now()->format('Y-m-d'), 'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'approved',
        ],
        'items' => [['product_id' => $ctx['product']->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax_type' => 'eksklusif', 'tax' => 11]],
    ];
    $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload)->assertStatus(422);
    expect(SaleOrder::withoutGlobalScopes()->where('so_number', 'SO-P3-BYPASS')->exists())->toBeFalse();

    $so = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-P3-APPROVED',
        'order_date' => now(), 'tipe_pengiriman' => 'Kirim Langsung', 'status' => 'approved',
        'currency_id' => $ctx['idr']->id, 'tempo_pembayaran' => 30, 'notes' => 'Asli',
    ]);
    $update = $payload;
    $update['header']['so_number'] = 'SO-P3-APPROVED';
    $update['header']['status'] = 'draft';
    $update['header']['notes'] = 'Diubah lewat API';
    $this->actingAs($ctx['user'])->putJson("/api/v1/sales-orders/{$so->id}", $update)->assertStatus(422);

    expect($so->fresh()->notes)->toBe('Asli')->and($so->fresh()->status)->toBe('approved');
});

it('T1: API sales order — store tanpa izin create sales order ditolak 403', function () {
    $ctx = phase3Context('approve', permissions: ['view any sales order', 'view sales order']);
    $payload = [
        'header' => [
            'so_number' => 'SO-P3-NOPERM', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
            'order_date' => now()->format('Y-m-d'), 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1,
        ],
        'items' => [['product_id' => $ctx['product']->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax_type' => 'eksklusif', 'tax' => 11]],
    ];

    $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload)->assertStatus(403);
});

// ───────────────────────────── Revisi ─────────────────────────────

it('Isu 4: revisi menyalin header + item menjadi Draft baru bernomor -R1 dan versi lama tidak berubah', function () {
    $ctx = phase3Context('approve', ['shipped_to' => 'Gudang Klien']);
    $source = $ctx['quotation'];

    $revision = app(QuotationService::class)->createRevision($source);

    expect($revision->quotation_number)->toBe($source->quotation_number . '-R1')
        ->and($revision->status)->toBe('draft')
        ->and($revision->revision_of_id)->toBe($source->id)
        ->and($revision->revision_no)->toBe(1)
        ->and($revision->created_by)->toBe($ctx['user']->id)
        ->and($revision->customer_id)->toBe($source->customer_id)
        ->and($revision->shipped_to)->toBe('Gudang Klien')
        ->and($revision->notes)->toBe('Catatan Fase 3')
        ->and($revision->valid_until->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($revision->quotationItem()->count())->toBe(1)
        ->and((float) $revision->quotationItem()->first()->quantity)->toBe(20.0)
        ->and(round((float) $revision->total_amount, 2))->toBe(round((float) $source->fresh()->total_amount, 2));

    $original = $source->fresh();
    expect($original->status)->toBe('approve')
        ->and($original->superseded_at)->toBeNull()
        ->and($original->quotationItem()->count())->toBe(1);
});

it('Isu 4: revisi dari quotation Kedaluwarsa diperbolehkan; dari Draft/Ditolak/Menunggu ditolak', function () {
    $expired = phase3Context('expired');
    expect(app(QuotationService::class)->createRevision($expired['quotation'])->status)->toBe('draft');

    foreach (['draft', 'reject', 'request_approve'] as $status) {
        $ctx = phase3Context($status);
        expect(fn () => app(QuotationService::class)->createRevision($ctx['quotation']))->toThrow(ValidationException::class);
    }
});

it('Isu 4: tidak boleh ada dua revisi terbuka; setelah revisi ditolak dan dihapus, revisi baru bernomor unik', function () {
    $ctx = phase3Context('approve');
    $service = app(QuotationService::class);

    $first = $service->createRevision($ctx['quotation']);
    expect(fn () => $service->createRevision($ctx['quotation']->fresh()))->toThrow(ValidationException::class);
    expect(Quotation::withoutGlobalScopes()->where('revision_of_id', $ctx['quotation']->id)->count())->toBe(1);

    // Revisi dihapus (soft delete): nomor -R1 tidak dipakai ulang
    $first->delete();
    $second = $service->createRevision($ctx['quotation']->fresh());
    expect($second->quotation_number)->toBe($ctx['quotation']->quotation_number . '-R2');
});

it('Isu 4: revisi yang DISETUJUI menggantikan versi lama; versi lama tak dapat dijadikan SO dan tak dapat direvisi lagi', function () {
    $ctx = phase3Context('approve');
    $service = app(QuotationService::class);

    $revision = $service->createRevision($ctx['quotation']);
    expect($ctx['quotation']->fresh()->superseded_at)->toBeNull()
        ->and(QuotationResource::canCreateSaleOrder($ctx['quotation']->fresh()))->toBeTrue();   // versi lama tetap berlaku selama revisi belum disetujui

    $service->requestApprove($revision);
    $service->approve($revision->fresh());

    $old = $ctx['quotation']->fresh();
    expect($old->superseded_at)->not->toBeNull()
        ->and($old->unusableReasonForSaleOrder())->toContain('digantikan')
        ->and(QuotationResource::canCreateSaleOrder($old))->toBeFalse()
        ->and($ctx['user']->can('revise', $old))->toBeFalse()
        ->and(Quotation::withoutGlobalScopes()->usable()->pluck('id')->all())->toContain($revision->id)->not->toContain($old->id);

    expect(fn () => $service->createRevision($old))->toThrow(ValidationException::class);

    // Revisi berikutnya berasal dari versi terbaru dan tetap memakai nomor dasar yang sama
    $next = $service->createRevision($revision->fresh());
    expect($next->quotation_number)->toBe($ctx['quotation']->quotation_number . '-R2')
        ->and($next->revision_no)->toBe(2);
});

it('Isu 4: aksi Buat Revisi dari halaman Lihat membuat Draft dan mengarahkan ke halaman Ubah', function () {
    $ctx = phase3Context('approve');

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->callAction('revise')
        ->assertHasNoActionErrors();

    $revision = Quotation::withoutGlobalScopes()->where('revision_of_id', $ctx['quotation']->id)->firstOrFail();
    expect($revision->status)->toBe('draft')->and($revision->quotation_number)->toEndWith('-R1');
});

// ───────────────────────────── created_by ─────────────────────────────

it('Isu 4: created_by terisi otomatis dari pengguna login pada jalur pembuatan apa pun', function () {
    $ctx = phase3Context('draft');

    $quotation = Quotation::create([
        'quotation_number' => 'QO-P3-NOCREATOR', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'valid_until' => now()->addDays(5), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1,
        'status' => 'draft', 'total_amount' => 0,
    ]);

    expect($quotation->fresh()->created_by)->toBe($ctx['user']->id);
});

it('Isu 4: created_by yang diberikan eksplisit tidak ditimpa', function () {
    $ctx = phase3Context('draft');
    $other = User::factory()->create(['cabang_id' => $ctx['cabang']->id]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-P3-EXPLICIT', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'draft', 'total_amount' => 0,
        'created_by' => $other->id,
    ]);

    expect($quotation->fresh()->created_by)->toBe($other->id);
});

it('Isu 4: backfill created_by — dry-run tidak mengubah; --apply hanya mengisi yang NULL dan dapat dilacak dari activity log', function () {
    $ctx = phase3Context('draft');
    $traceable = Quotation::create([
        'quotation_number' => 'QO-P3-BF-1', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'draft', 'total_amount' => 0,
    ]);
    $untraceable = Quotation::create([
        'quotation_number' => 'QO-P3-BF-2', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'draft', 'total_amount' => 0,
    ]);
    $filled = $ctx['quotation'];   // created_by sudah terisi

    // Simulasikan data lama: created_by kosong, tetapi log aktivitas mengenal pembuatnya (hanya untuk $traceable)
    DB::table('quotations')->whereIn('id', [$traceable->id, $untraceable->id])->update(['created_by' => null]);
    DB::table('activity_log')->where('subject_type', Quotation::class)->whereIn('subject_id', [$traceable->id, $untraceable->id])->delete();
    DB::table('activity_log')->insert([
        'log_name' => 'default', 'description' => 'Quotation dibuat.', 'subject_type' => Quotation::class, 'subject_id' => $traceable->id,
        'causer_type' => User::class, 'causer_id' => $ctx['user']->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Artisan::call('quotations:backfill-creator');
    expect($traceable->fresh()->created_by)->toBeNull()
        ->and(Artisan::output())->toContain('DRY-RUN');

    Artisan::call('quotations:backfill-creator', ['--apply' => true]);
    expect($traceable->fresh()->created_by)->toBe($ctx['user']->id)
        ->and($untraceable->fresh()->created_by)->toBeNull()
        ->and($filled->fresh()->created_by)->toBe($ctx['user']->id);
});
