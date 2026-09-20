<?php

/**
 * Fase 4 audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md):
 *  - Isu 6: Surat Jalan layak cetak (PDF tanpa harga, driver/kendaraan dari jadwal, barang per DO,
 *           tanda tangan), halaman Lihat/daftar benar, siklus terbit → terkunci → batal → terbit ulang
 *  - Isu 8: Jadwal Pengiriman dengan master driver/kendaraan kosong (empty-state, Ekspedisi, validasi server,
 *           nomor resi) dan checklist kesiapan master
 */

use App\Filament\Resources\DeliveryScheduleResource\Pages\CreateDeliverySchedule;
use App\Filament\Resources\SuratJalanResource;
use App\Filament\Resources\SuratJalanResource\Pages\EditSuratJalan;
use App\Filament\Resources\SuratJalanResource\Pages\ListSuratJalans;
use App\Filament\Resources\SuratJalanResource\Pages\ViewSuratJalan;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryScheduleService;
use App\Services\MasterDataReadiness;
use App\Services\SuratJalanDocumentBuilder;
use App\Services\SuratJalanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const P4_SJ_PERMISSIONS = [
    'view any surat jalan', 'view surat jalan', 'create surat jalan', 'update surat jalan', 'delete surat jalan',
    'view any delivery schedule', 'view delivery schedule', 'create delivery schedule', 'update delivery schedule',
];

function phase4Context(?array $permissions = null): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'P4-' . strtoupper(substr(uniqid(), -6)), 'nama' => 'Cabang Fase 4', 'status' => 1,
        'alamat' => 'Jl. Sudirman No. 1, Jakarta Pusat', 'telepon' => '021-1234567',
    ]);

    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions ?? P4_SJ_PERMISSIONS);
    Auth::login($user);

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Maju Bersama']);
    $product = Product::factory()->create([
        'name' => 'Alat Medis Fase 4', 'sku' => 'AM4-' . strtoupper(substr(uniqid(), -6)), 'uom_id' => $uom->id, 'sell_price' => 999000,
    ]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

    return compact('cabang', 'user', 'uom', 'customer', 'product', 'warehouse');
}

/** DO approved dengan satu SO (alamat kirim) dan item tanpa tautan item SO (agar tak terkena guard alokasi). */
function phase4DeliveryOrder(array $ctx, string $doNumber, array $items = [], string $address = 'Jl. Kirim No. 9, Bekasi', string $status = 'approved'): DeliveryOrder
{
    $so = SaleOrder::factory()->create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'status' => 'approved',
        'shipped_to' => $address, 'so_number' => 'SO-' . $doNumber,
    ]);

    $do = DeliveryOrder::factory()->create([
        'do_number' => $doNumber, 'delivery_date' => now(), 'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id, 'status' => $status,
    ]);
    $do->salesOrders()->attach($so->id);

    foreach ($items ?: [['quantity' => 12, 'reason' => 'Rak A']] as $item) {
        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $item['product_id'] ?? $ctx['product']->id,
            'quantity' => $item['quantity'],
            'reason' => $item['reason'] ?? null,
        ]);
    }

    return $do;
}

function phase4SuratJalan(array $ctx, array $deliveryOrders, int $status = SuratJalan::STATUS_ISSUED, array $attributes = []): SuratJalan
{
    $sj = SuratJalan::create(array_merge([
        'sj_number' => app(SuratJalanService::class)->generateCode(),
        'issued_at' => Carbon::create(2026, 9, 19, 9, 30),
        'status' => $status,
        'created_by' => $ctx['user']->id,
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
    $sj->deliveryOrder()->sync(collect($deliveryOrders)->pluck('id')->all());

    return $sj;
}

function phase4Schedule(array $ctx, SuratJalan $sj, array $attributes = []): DeliverySchedule
{
    $schedule = DeliverySchedule::create(array_merge([
        'schedule_number' => 'SCH-P4-' . strtoupper(substr(uniqid(), -6)),
        'scheduled_date' => Carbon::create(2026, 9, 21, 8, 0),
        'delivery_method' => 'internal',
        'status' => 'pending',
        'cabang_id' => $ctx['cabang']->id,
        'created_by' => $ctx['user']->id,
    ], $attributes));
    $schedule->suratJalan()->attach($sj->id);

    return $schedule;
}

// ───────────────────────────── Isu 6: dokumen cetak ─────────────────────────────

it('Isu 6: endpoint PDF Surat Jalan ber-DO tunggal dan jamak berhasil (sebelumnya 500 karena relasi customer)', function () {
    $ctx = phase4Context();
    $do1 = phase4DeliveryOrder($ctx, 'DO-P4-001');
    $do2 = phase4DeliveryOrder($ctx, 'DO-P4-002', [['quantity' => 3], ['quantity' => 1200, 'reason' => 'Dus 2']]);

    $single = phase4SuratJalan($ctx, [$do1]);
    $multi = phase4SuratJalan($ctx, [$do1, $do2], attributes: ['sj_number' => 'SJ-MULTI-P4']);

    foreach ([$single, $multi] as $sj) {
        $response = $this->actingAs($ctx['user'])->get(route('pdf-stream', ['type' => 'surat-jalan', 'id' => $sj->id]));
        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf');
    }
});

it('Isu 6: data cetak memuat nomor DO/SO, customer unik, alamat, driver + plat dari jadwal, dan barang per DO tanpa harga', function () {
    $ctx = phase4Context();
    $do1 = phase4DeliveryOrder($ctx, 'DO-P4-101', [['quantity' => 12, 'reason' => 'Rak A']]);
    $do2 = phase4DeliveryOrder($ctx, 'DO-P4-102', [['quantity' => 1200], ['quantity' => 7]]);
    $sj = phase4SuratJalan($ctx, [$do1, $do2]);

    $driver = Driver::factory()->create(['cabang_id' => $ctx['cabang']->id, 'name' => 'Budi Driver']);
    $vehicle = Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id, 'plate' => 'B 1234 XYZ', 'type' => 'Truck']);
    phase4Schedule($ctx, $sj, ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

    $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());

    expect($doc['number'])->toBe($sj->sj_number)
        ->and($doc['issued_date'])->toBe('Sabtu, 19 September 2026')
        ->and($doc['delivery_order_numbers'])->toBe(['DO-P4-101', 'DO-P4-102'])
        ->and($doc['sales_order_numbers'])->toBe(['SO-DO-P4-101', 'SO-DO-P4-102'])
        ->and($doc['customers'])->toBe(['PT Maju Bersama'])          // unik, tidak diulang per SO
        ->and($doc['addresses'])->toBe(['Jl. Kirim No. 9, Bekasi'])
        ->and($doc['delivery']['scheduled'])->toBeTrue()
        ->and($doc['delivery']['sender_name'])->toBe('Budi Driver')
        ->and($doc['delivery']['vehicle'])->toBe('B 1234 XYZ (Truck)')
        ->and($doc['signatures']['driver_name'])->toBe('Budi Driver')
        ->and($doc['company']['address'])->toBe('Jl. Sudirman No. 1, Jakarta Pusat')
        ->and($doc['company']['phone'])->toBe('021-1234567')
        ->and($doc['groups'])->toHaveCount(2)
        ->and($doc['groups'][0]['do_number'])->toBe('DO-P4-101')
        ->and($doc['groups'][0]['items'][0])->toMatchArray(['no' => 1, 'quantity' => '12', 'unit' => 'pcs', 'note' => 'Rak A'])
        ->and(collect($doc['groups'][1]['items'])->pluck('quantity')->all())->toBe(['1.200', '7'])   // tidak digabung lintas DO
        ->and($doc['total_lines'])->toBe(3);

    // tidak ada satu pun kunci harga pada data cetak
    $flattened = json_encode($doc);
    foreach (['unit_price', 'harga', 'subtotal', 'discount', 'tax'] as $forbidden) {
        expect(strtolower($flattened))->not->toContain($forbidden);
    }
});

it('Isu 6: Surat Jalan yang belum dijadwalkan dicetak "Belum dijadwalkan" (D3b), bukan kosong', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-201')]);

    $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());
    expect($doc['delivery']['scheduled'])->toBeFalse()
        ->and($doc['delivery']['method_label'])->toBe('Belum dijadwalkan');

    $html = view('pdf.surat-jalan', ['suratJalan' => $sj, 'doc' => $doc])->render();
    expect($html)->toContain('Belum dijadwalkan');
});

it('Isu 6: ekspedisi menampilkan nama ekspedisi dan nomor resi', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-301')]);
    phase4Schedule($ctx, $sj, [
        'delivery_method' => 'ekspedisi', 'driver_name' => 'JNE Cargo', 'vehicle_info' => 'Truk Box', 'tracking_number' => 'JNE-778899',
    ]);

    $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());
    expect($doc['delivery']['sender_name'])->toBe('JNE Cargo')
        ->and($doc['delivery']['tracking_number'])->toBe('JNE-778899')
        ->and($doc['signatures']['driver_role'])->toBe('Ekspedisi / Kurir');

    $html = view('pdf.surat-jalan', ['suratJalan' => $sj, 'doc' => $doc])->render();
    expect($html)->toContain('JNE Cargo')->toContain('JNE-778899');
});

it('Isu 6: template memuat blok tanda tangan, tanpa harga, tanpa alamat placeholder, dan kop dari cabang', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-401')]);

    $html = view('pdf.surat-jalan', ['suratJalan' => $sj, 'doc' => app(SuratJalanDocumentBuilder::class)->build($sj->fresh())])->render();

    expect($html)
        ->toContain('Yang Menyerahkan')->toContain('Penerima')->toContain('Mengetahui')
        ->toContain('Jl. Sudirman No. 1, Jakarta Pusat')->toContain('Cabang Fase 4')
        ->toContain('SKU')->toContain('Satuan')->toContain('DO-P4-401')->toContain('SO-DO-P4-401')
        ->not->toContain('Jl. Contoh')->not->toContain('12345678')
        ->not->toContain('Harga')->not->toContain('Subtotal')->not->toContain('Discount');
});

it('Isu 6: alamat/telepon cabang kosong dikosongkan, bukan diisi placeholder', function () {
    $ctx = phase4Context();
    $ctx['cabang']->update(['alamat' => '', 'telepon' => '']);
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-451')]);

    $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());
    expect($doc['company']['address'])->toBeNull()->and($doc['company']['phone'])->toBeNull();

    $html = view('pdf.surat-jalan', ['suratJalan' => $sj, 'doc' => $doc])->render();
    expect($html)->not->toContain('Jl. Contoh');
});

it('Isu 6: Surat Jalan dibatalkan dicetak dengan banner DIBATALKAN dan alasan', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-501')]);
    app(SuratJalanService::class)->cancel($sj, 'Salah alamat kirim');

    $html = view('pdf.surat-jalan', ['suratJalan' => $sj, 'doc' => app(SuratJalanDocumentBuilder::class)->build($sj->fresh())])->render();
    expect($html)->toContain('DIBATALKAN')->toContain('Salah alamat kirim');
});

// ───────────────────────────── Isu 6: siklus hidup & kunci ─────────────────────────────

it('Isu 6: policy — hanya Draft yang boleh diubah/dihapus; kemampuan lain mengikuti status', function () {
    $ctx = phase4Context(array_merge(P4_SJ_PERMISSIONS));
    $do = phase4DeliveryOrder($ctx, 'DO-P4-601');
    $user = $ctx['user'];

    $draft = phase4SuratJalan($ctx, [$do], SuratJalan::STATUS_DRAFT);
    $issued = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-602')], SuratJalan::STATUS_ISSUED);
    $cancelled = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-603')], SuratJalan::STATUS_CANCELLED);

    expect($user->can('update', $draft))->toBeTrue()
        ->and($user->can('delete', $draft))->toBeTrue()
        ->and($user->can('issue', $draft))->toBeTrue()
        ->and($user->can('cancel', $draft))->toBeFalse()
        ->and($user->can('uploadDocument', $draft))->toBeTrue();

    expect($user->can('update', $issued))->toBeFalse()
        ->and($user->can('delete', $issued))->toBeFalse()
        ->and($user->can('cancel', $issued))->toBeTrue()
        ->and($user->can('issue', $issued))->toBeFalse()
        ->and($user->can('reissue', $issued))->toBeFalse()
        ->and($user->can('uploadDocument', $issued))->toBeTrue();

    expect($user->can('update', $cancelled))->toBeFalse()
        ->and($user->can('delete', $cancelled))->toBeFalse()
        ->and($user->can('cancel', $cancelled))->toBeFalse()
        ->and($user->can('reissue', $cancelled))->toBeTrue()
        ->and($user->can('uploadDocument', $cancelled))->toBeFalse();

    $viewOnly = phase4Context(['view any surat jalan', 'view surat jalan']);
    $sj = phase4SuratJalan($viewOnly, [phase4DeliveryOrder($viewOnly, 'DO-P4-604')], SuratJalan::STATUS_DRAFT);
    expect($viewOnly['user']->can('update', $sj))->toBeFalse()
        ->and($viewOnly['user']->can('issue', $sj))->toBeFalse();
});

it('Isu 6: halaman Edit Surat Jalan terbit diarahkan ke halaman Lihat; Draft tetap dapat dibuka', function () {
    $ctx = phase4Context();
    $issued = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-701')]);
    $draft = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-702')], SuratJalan::STATUS_DRAFT);

    Livewire::actingAs($ctx['user'])
        ->test(EditSuratJalan::class, ['record' => $issued->getKey()])
        ->assertRedirect(SuratJalanResource::getUrl('view', ['record' => $issued]));

    Livewire::actingAs($ctx['user'])
        ->test(EditSuratJalan::class, ['record' => $draft->getKey()])
        ->assertSuccessful()
        ->assertNoRedirect();
});

it('Isu 6: tabel — Ubah/Hapus hanya untuk Draft; Batalkan untuk Terbit; Terbitkan Ulang untuk Dibatalkan', function () {
    $ctx = phase4Context();
    $draft = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-801')], SuratJalan::STATUS_DRAFT);
    $issued = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-802')]);
    $cancelled = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-803')], SuratJalan::STATUS_CANCELLED);

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->assertTableActionVisible('edit', $draft)
        ->assertTableActionVisible('delete', $draft)
        ->assertTableActionVisible('issue', $draft)
        ->assertTableActionHidden('cancel', $draft)
        ->assertTableActionHidden('edit', $issued)
        ->assertTableActionHidden('delete', $issued)
        ->assertTableActionHidden('issue', $issued)
        ->assertTableActionVisible('cancel', $issued)
        ->assertTableActionVisible('upload_document', $issued)
        ->assertTableActionHidden('reissue', $issued)
        ->assertTableActionHidden('edit', $cancelled)
        ->assertTableActionHidden('cancel', $cancelled)
        ->assertTableActionHidden('upload_document', $cancelled)
        ->assertTableActionVisible('reissue', $cancelled);
});

it('Isu 6: halaman Lihat menyembunyikan Ubah/Hapus dan menampilkan Batalkan untuk Surat Jalan terbit', function () {
    $ctx = phase4Context();
    $issued = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-851')]);

    Livewire::actingAs($ctx['user'])
        ->test(ViewSuratJalan::class, ['record' => $issued->getKey()])
        ->assertActionHidden('edit')
        ->assertActionHidden('delete')
        ->assertActionVisible('cancel')
        ->assertActionVisible('upload_document')
        ->assertActionHidden('reissue')
        ->assertActionHidden('issue');
});

it('Isu 6: hapus massal hanya menghapus Draft', function () {
    $ctx = phase4Context();
    $draft = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-861')], SuratJalan::STATUS_DRAFT);
    $issued = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-862')]);

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->callTableBulkAction('delete', [$draft, $issued]);

    expect(SuratJalan::withTrashed()->find($draft->id)->trashed())->toBeTrue()
        ->and(SuratJalan::withTrashed()->find($issued->id)->trashed())->toBeFalse();
});

it('Isu 6: Batalkan — alasan wajib (min 5 karakter), tercatat siapa/kapan/alasan, tidak dapat dua kali', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-901')]);
    $service = app(SuratJalanService::class);

    foreach (['', '   ', 'abc'] as $badReason) {
        expect(fn () => $service->cancel($sj, $badReason))->toThrow(ValidationException::class);
    }
    expect($sj->fresh()->status)->toBe(SuratJalan::STATUS_ISSUED);

    $service->cancel($sj, '  Alamat kirim salah  ');
    $cancelled = $sj->fresh();
    expect($cancelled->status)->toBe(SuratJalan::STATUS_CANCELLED)
        ->and($cancelled->cancel_reason)->toBe('Alamat kirim salah')
        ->and($cancelled->cancelled_by)->toBe($ctx['user']->id)
        ->and($cancelled->cancelled_at)->not->toBeNull();

    expect(fn () => $service->cancel($cancelled, 'Batal lagi'))->toThrow(ValidationException::class);
});

it('Isu 6: Draft tidak dapat dibatalkan (cukup dihapus)', function () {
    $ctx = phase4Context();
    $draft = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-911')], SuratJalan::STATUS_DRAFT);

    expect(fn () => app(SuratJalanService::class)->cancel($draft, 'Tidak jadi kirim'))->toThrow(ValidationException::class);
});

it('Isu 6: Batalkan ditolak selama Surat Jalan masih dipakai jadwal berjalan/selesai; boleh bila jadwal gagal/batal', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-921')]);
    $driver = Driver::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $vehicle = Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $schedule = phase4Schedule($ctx, $sj, ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'status' => 'pending']);

    foreach (['pending', 'on_the_way', 'partial_delivered', 'delivered'] as $status) {
        DeliverySchedule::withoutGlobalScopes()->whereKey($schedule->id)->update(['status' => $status]);
        expect(fn () => app(SuratJalanService::class)->cancel($sj->fresh(), 'Perlu dibatalkan'))
            ->toThrow(ValidationException::class, $schedule->schedule_number);
        expect($sj->fresh()->status)->toBe(SuratJalan::STATUS_ISSUED);
    }

    DeliverySchedule::withoutGlobalScopes()->whereKey($schedule->id)->update(['status' => 'cancelled']);
    app(SuratJalanService::class)->cancel($sj->fresh(), 'Jadwal sudah dibatalkan');
    expect($sj->fresh()->status)->toBe(SuratJalan::STATUS_CANCELLED);
});

it('Isu 6: aksi Batalkan dari tabel dan halaman Lihat mewajibkan alasan dan menyimpannya', function () {
    $ctx = phase4Context();
    $a = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-931')]);
    $b = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-932')]);

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->callTableAction('cancel', $a, data: ['cancel_reason' => ''])
        ->assertHasTableActionErrors(['cancel_reason' => 'required']);
    expect($a->fresh()->status)->toBe(SuratJalan::STATUS_ISSUED);

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->callTableAction('cancel', $a, data: ['cancel_reason' => 'Salah customer'])
        ->assertHasNoTableActionErrors();
    expect($a->fresh()->status)->toBe(SuratJalan::STATUS_CANCELLED)->and($a->fresh()->cancel_reason)->toBe('Salah customer');

    Livewire::actingAs($ctx['user'])
        ->test(ViewSuratJalan::class, ['record' => $b->getKey()])
        ->callAction('cancel', data: ['cancel_reason' => 'Barang kosong'])
        ->assertHasNoActionErrors();
    expect($b->fresh()->status)->toBe(SuratJalan::STATUS_CANCELLED);
});

it('Isu 6: hanya dokumen bertanda tangan yang dapat diunggah pada Surat Jalan terbit', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-941')]);

    Storage::fake('public');

    Livewire::actingAs($ctx['user'])
        ->test(ViewSuratJalan::class, ['record' => $sj->getKey()])
        ->callAction('upload_document', data: ['document_path' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
        ->assertHasNoActionErrors();

    $after = $sj->fresh();
    expect($after->document_path)->toStartWith('surat-jalan-documents/')
        ->and($after->status)->toBe(SuratJalan::STATUS_ISSUED);
});

it('Isu 6: Terbitkan Draft — DO harus approved dan belum di Surat Jalan lain', function () {
    $ctx = phase4Context();
    $do = phase4DeliveryOrder($ctx, 'DO-P4-951');
    $draft = phase4SuratJalan($ctx, [$do], SuratJalan::STATUS_DRAFT);

    app(SuratJalanService::class)->issue($draft);
    expect($draft->fresh()->status)->toBe(SuratJalan::STATUS_ISSUED);

    // Draft kedua untuk DO yang sama tidak dapat diterbitkan karena sudah di SJ terbit lain
    $second = phase4SuratJalan($ctx, [$do], SuratJalan::STATUS_DRAFT);
    expect(fn () => app(SuratJalanService::class)->issue($second))->toThrow(ValidationException::class);
});

// ───────────────────────────── Isu 6: DO tak boleh ganda, terbit ulang, nomor ─────────────────────────────

it('Isu 6: satu DO tidak boleh tercantum di dua Surat Jalan yang berlaku; setelah dibatalkan boleh lagi', function () {
    $ctx = phase4Context();
    $do = phase4DeliveryOrder($ctx, 'DO-P4-A01');
    $sj = phase4SuratJalan($ctx, [$do]);
    $service = app(SuratJalanService::class);

    expect(fn () => $service->assertDeliveryOrdersUsable(collect([$do->fresh()])))->toThrow(ValidationException::class, $sj->sj_number);

    $service->cancel($sj, 'Koreksi data pengiriman');
    $service->assertDeliveryOrdersUsable(collect([$do->fresh()]));   // tidak melempar

    expect(true)->toBeTrue();
});

it('Isu 6: validasi DO — hanya approved, satu cabang, minimal satu', function () {
    $ctx = phase4Context();
    $service = app(SuratJalanService::class);

    expect(fn () => $service->assertDeliveryOrdersUsable(collect()))->toThrow(ValidationException::class);

    $notApproved = phase4DeliveryOrder($ctx, 'DO-P4-B01', status: 'draft');
    expect(fn () => $service->assertDeliveryOrdersUsable(collect([$notApproved])))->toThrow(ValidationException::class, 'DO-P4-B01');

    $otherCabang = Cabang::factory()->create();
    $a = phase4DeliveryOrder($ctx, 'DO-P4-B02');
    $b = phase4DeliveryOrder($ctx, 'DO-P4-B03');
    $b->update(['cabang_id' => $otherCabang->id]);
    expect(fn () => $service->assertDeliveryOrdersUsable(collect([$a->fresh(), $b->fresh()])))->toThrow(ValidationException::class, 'cabang yang sama');
});

it('Isu 6: Terbitkan Ulang membuat Surat Jalan baru (nomor baru, terbit) untuk DO yang sama', function () {
    $ctx = phase4Context();
    $do1 = phase4DeliveryOrder($ctx, 'DO-P4-C01');
    $do2 = phase4DeliveryOrder($ctx, 'DO-P4-C02');
    $old = phase4SuratJalan($ctx, [$do1, $do2]);
    $service = app(SuratJalanService::class);

    expect(fn () => $service->reissue($old))->toThrow(ValidationException::class);   // belum dibatalkan

    $service->cancel($old, 'Salah tanggal terbit');
    $new = $service->reissue($old->fresh());

    expect($new->id)->not->toBe($old->id)
        ->and($new->sj_number)->not->toBe($old->sj_number)
        ->and($new->status)->toBe(SuratJalan::STATUS_ISSUED)
        ->and($new->cabang_id)->toBe($old->cabang_id)
        ->and($new->deliveryOrder()->pluck('delivery_orders.id')->sort()->values()->all())->toBe([$do1->id, $do2->id])
        ->and($old->fresh()->status)->toBe(SuratJalan::STATUS_CANCELLED);

    // tidak dapat diterbitkan ulang dua kali (DO sudah ada di SJ baru)
    expect(fn () => $service->reissue($old->fresh()))->toThrow(ValidationException::class);
});

it('Isu 6: Terbitkan Ulang ditolak bila DO sudah tidak approved', function () {
    $ctx = phase4Context();
    $do = phase4DeliveryOrder($ctx, 'DO-P4-D01');
    $old = phase4SuratJalan($ctx, [$do]);
    app(SuratJalanService::class)->cancel($old, 'Dokumen keliru');

    $do->update(['status' => 'sent']);
    expect(fn () => app(SuratJalanService::class)->reissue($old->fresh()))->toThrow(ValidationException::class, 'approved');
});

it('Isu 6: aksi Terbitkan Ulang dari tabel', function () {
    $ctx = phase4Context();
    $do = phase4DeliveryOrder($ctx, 'DO-P4-E01');
    $old = phase4SuratJalan($ctx, [$do]);
    app(SuratJalanService::class)->cancel($old, 'Perlu dicetak ulang');

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->callTableAction('reissue', $old->fresh());

    expect(SuratJalan::where('status', SuratJalan::STATUS_ISSUED)->count())->toBe(1);
});

it('Isu 6: nomor Surat Jalan berurutan per hari, melanjutkan dari nomor terbesar (bukan acak)', function () {
    $ctx = phase4Context();
    $service = app(SuratJalanService::class);
    $prefix = 'SJ-' . now()->format('Ymd') . '-';

    expect($service->generateCode())->toBe($prefix . '0001');

    SuratJalan::create(['sj_number' => $prefix . '4831', 'issued_at' => now(), 'status' => 1, 'created_by' => $ctx['user']->id]);   // nomor acak lama
    expect($service->generateCode())->toBe($prefix . '4832');

    SuratJalan::create(['sj_number' => $prefix . '4832', 'issued_at' => now(), 'status' => 1, 'created_by' => $ctx['user']->id]);
    expect($service->generateCode())->toBe($prefix . '4833');
});

// ───────────────────────────── Isu 6: halaman Lihat & daftar ─────────────────────────────

it('Isu 6: halaman Lihat menampilkan status, pengiriman dari jadwal, dan DO tanpa error', function () {
    $ctx = phase4Context();
    $do = phase4DeliveryOrder($ctx, 'DO-P4-F01');
    $sj = phase4SuratJalan($ctx, [$do]);
    phase4Schedule($ctx, $sj, ['delivery_method' => 'ekspedisi', 'driver_name' => 'Ekspedisi Lancar', 'tracking_number' => 'RESI-001']);

    Livewire::actingAs($ctx['user'])
        ->test(ViewSuratJalan::class, ['record' => $sj->getKey()])
        ->assertSuccessful()
        ->assertSee($sj->sj_number)
        ->assertSee('Terbit')
        ->assertSee('Ekspedisi Lancar')
        ->assertSee('RESI-001')
        ->assertSee('DO-P4-F01')
        ->assertSee('PT Maju Bersama');
});

it('Isu 6: halaman Lihat Surat Jalan dibatalkan menampilkan bagian Pembatalan beserta alasan', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-F11')]);
    app(SuratJalanService::class)->cancel($sj, 'Barang tidak jadi dikirim');

    Livewire::actingAs($ctx['user'])
        ->test(ViewSuratJalan::class, ['record' => $sj->getKey()])
        ->assertSee('Dibatalkan')
        ->assertSee('Barang tidak jadi dikirim');
});

it('Isu 6: daftar menampilkan driver dari jadwal / "Belum dijadwalkan", badge status, dan filter Draft (0) bekerja', function () {
    $ctx = phase4Context();
    $scheduled = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-G01')]);
    $unscheduled = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-G02')]);
    $draft = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-G03')], SuratJalan::STATUS_DRAFT);
    phase4Schedule($ctx, $scheduled, ['delivery_method' => 'ekspedisi', 'driver_name' => 'Kurir Cepat']);

    Livewire::actingAs($ctx['user'])
        ->test(ListSuratJalans::class)
        ->assertTableColumnStateSet('driver_info', 'Kurir Cepat', $scheduled)
        ->assertTableColumnStateSet('driver_info', 'Belum dijadwalkan', $unscheduled)
        ->assertTableColumnFormattedStateSet('status', 'Terbit', $scheduled)
        ->assertTableColumnFormattedStateSet('status', 'Draft', $draft)
        ->filterTable('status', '0')
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$scheduled, $unscheduled]);
});

it('Isu 6: rekap Surat Jalan memakai label status Draft/Terbit/Dibatalkan', function () {
    $ctx = phase4Context();
    phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-H01')], SuratJalan::STATUS_CANCELLED, ['sj_number' => 'SJ-REKAP-BATAL']);

    $suratJalans = ListSuratJalans::getRekapSuratJalanQuery(null, null, 'all')->get();
    $html = view('pdf.surat-jalan-recap', [
        'suratJalans' => $suratJalans, 'tanggalMulai' => null, 'tanggalSelesai' => null, 'statusPengiriman' => '2',
    ])->render();

    expect($html)->toContain('SJ-REKAP-BATAL')->toContain('Dibatalkan');
});

// ───────────────────────────── Isu 8: master kosong & pengirim ─────────────────────────────

it('Isu 8: validasi server — internal butuh driver & kendaraan master; ekspedisi cukup nama ekspedisi', function () {
    $ctx = phase4Context();
    $service = app(DeliveryScheduleService::class);

    $capture = function (array $data) use ($service) {
        try {
            $service->validateSender($data, 'data.');
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    };

    expect($capture(['delivery_method' => 'internal']))->toBe(['data.driver_id', 'data.vehicle_id'])
        ->and($capture(['delivery_method' => 'kurir_internal', 'driver_id' => 999999, 'vehicle_id' => 999999]))->toBe(['data.driver_id', 'data.vehicle_id'])
        ->and($capture(['delivery_method' => 'ekspedisi']))->toBe(['data.driver_name'])
        ->and($capture(['delivery_method' => 'ekspedisi', 'driver_name' => 'JNE']))->toBe([]);

    $driver = Driver::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $vehicle = Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    expect($capture(['delivery_method' => 'internal', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]))->toBe([]);

    // driver yang sudah dihapus dari master tidak dianggap ada
    $driver->delete();
    expect($capture(['delivery_method' => 'internal', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]))->toBe(['data.driver_id']);
});

it('Isu 8: master kosong → default metode Ekspedisi dan terdeteksi driver/kendaraan kosong; terisi → Internal', function () {
    $ctx = phase4Context();
    $service = app(DeliveryScheduleService::class);

    expect($service->missingFleetMasters())->toBe(['driver', 'kendaraan'])
        ->and($service->defaultDeliveryMethod())->toBe('ekspedisi');

    Driver::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    expect($service->missingFleetMasters())->toBe(['kendaraan'])->and($service->defaultDeliveryMethod())->toBe('ekspedisi');

    Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    expect($service->missingFleetMasters())->toBe([])->and($service->defaultDeliveryMethod())->toBe('internal');
});

it('Isu 8: form Jadwal dengan master kosong menampilkan penjelasan dan default Ekspedisi; pengiriman ekspedisi dapat dijadwalkan tanpa driver internal', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-J01')]);

    $component = Livewire::actingAs($ctx['user'])
        ->test(CreateDeliverySchedule::class)
        ->assertSee('Master driver dan kendaraan belum diisi')
        ->assertFormSet(['delivery_method' => 'ekspedisi'])
        ->fillForm([
            'schedule_number' => DeliveryScheduleService::generateStaticScheduleNumber(),
            'cabang_id' => $ctx['cabang']->id,
            'suratJalan' => [$sj->id],
            'scheduled_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'delivery_method' => 'ekspedisi',
            'driver_name' => 'Ekspedisi Nusantara',
            'vehicle_info' => 'Truk Box B 9999 ZZ',
            'tracking_number' => 'ENU-2026-0001',
            'status' => 'pending',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $schedule = DeliverySchedule::withoutGlobalScopes()->where('driver_name', 'Ekspedisi Nusantara')->firstOrFail();
    expect($schedule->tracking_number)->toBe('ENU-2026-0001')
        ->and($schedule->driver_id)->toBeNull()
        ->and($schedule->suratJalan()->pluck('surat_jalans.id')->all())->toBe([$sj->id]);
});

it('Isu 8: jadwal internal tanpa driver/kendaraan ditolak dengan pesan yang menjelaskan', function () {
    $ctx = phase4Context();
    $sj = phase4SuratJalan($ctx, [phase4DeliveryOrder($ctx, 'DO-P4-J11')]);

    Livewire::actingAs($ctx['user'])
        ->test(CreateDeliverySchedule::class)
        ->fillForm([
            'schedule_number' => DeliveryScheduleService::generateStaticScheduleNumber(),
            'cabang_id' => $ctx['cabang']->id,
            'suratJalan' => [$sj->id],
            'scheduled_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'delivery_method' => 'internal',
            'status' => 'pending',
        ])
        ->call('create')
        ->assertHasFormErrors(['driver_id' => 'required', 'vehicle_id' => 'required']);

    expect(DeliverySchedule::withoutGlobalScopes()->count())->toBe(0);
});

it('Isu 8: tautan tambah master hanya untuk pengguna berizin; lainnya diminta menghubungi admin', function () {
    $withPermission = phase4Context(array_merge(P4_SJ_PERMISSIONS, ['create driver', 'create vehicle']));
    Livewire::actingAs($withPermission['user'])
        ->test(CreateDeliverySchedule::class)
        ->assertSee('Tambah Driver')
        ->assertSee('Tambah Kendaraan')
        ->assertDontSee('hubungi admin master data');

    $without = phase4Context();
    Livewire::actingAs($without['user'])
        ->test(CreateDeliverySchedule::class)
        ->assertSee('hubungi admin master data')
        ->assertDontSee('Tambah Driver');
});

it('Isu 8: banner tidak tampil dan default Internal bila master lengkap', function () {
    $ctx = phase4Context();
    Driver::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id]);

    Livewire::actingAs($ctx['user'])
        ->test(CreateDeliverySchedule::class)
        ->assertDontSee('belum diisi')
        ->assertFormSet(['delivery_method' => 'internal']);
});

it('Isu 8: status "Sebagian Terkirim" tersedia dan berlabel; DO ikut berstatus sent (bukan completed)', function () {
    $ctx = phase4Context();
    expect(DeliverySchedule::STATUS_LABELS)->toHaveKey('partial_delivered')
        ->and(DeliverySchedule::statusLabel('partial_delivered'))->toBe('Sebagian Terkirim');

    $do = phase4DeliveryOrder($ctx, 'DO-P4-K01');
    $sj = phase4SuratJalan($ctx, [$do]);
    $driver = Driver::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $vehicle = Vehicle::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $schedule = phase4Schedule($ctx, $sj, ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

    $schedule->update(['status' => 'partial_delivered']);

    expect($do->fresh()->status)->toBe('sent');
});

// ───────────────────────────── Isu 8: checklist kesiapan master ─────────────────────────────

it('Isu 8: master:readiness melaporkan master kosong dan exit 1 bila ada yang KRITIS', function () {
    DB::table('warehouses')->delete();
    DB::table('currencies')->delete();

    $exit = Artisan::call('master:readiness');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Gudang')->toContain('Driver')->toContain('Kendaraan')
        ->and($output)->toContain('KOSONG (KRITIS)');
});

it('Isu 8: readiness — status siap/sebagian/kosong dan hanya kosong total pada master kritis yang memblokir', function () {
    DB::table('warehouses')->delete();
    $cabangA = Cabang::factory()->create(['status' => 1, 'kode' => 'RD-A', 'nama' => 'Cabang A']);
    $cabangB = Cabang::factory()->create(['status' => 1, 'kode' => 'RD-B', 'nama' => 'Cabang B']);

    $result = collect(app(MasterDataReadiness::class)->check()['checks'])->keyBy('key');
    expect($result['gudang']['state'])->toBe('kosong')->and($result['gudang']['ok'])->toBeFalse();

    Warehouse::factory()->create(['cabang_id' => $cabangA->id]);
    $result = collect(app(MasterDataReadiness::class)->check()['checks'])->keyBy('key');
    expect($result['gudang']['state'])->toBe('sebagian')
        ->and($result['gudang']['ok'])->toBeTrue()
        ->and(collect($result['gudang']['missing_cabang'])->implode(' '))->toContain('Cabang B');

    Warehouse::factory()->create(['cabang_id' => $cabangB->id]);
    $only = app(MasterDataReadiness::class)->check($cabangA->id);
    expect(collect($only['checks'])->keyBy('key')['gudang']['state'])->toBe('siap')
        ->and($only['cabang'])->toBe('Cabang A');
});

it('Isu 8: readiness --json memuat pemeriksaan dan kesiapan', function () {
    Artisan::call('master:readiness', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toHaveKeys(['checks', 'ready', 'cabang'])
        ->and(collect($decoded['checks'])->pluck('key')->all())->toContain('gudang', 'driver', 'kendaraan', 'rak', 'coa_kas_bank', 'mata_uang_idr', 'pajak_ppn');
});
