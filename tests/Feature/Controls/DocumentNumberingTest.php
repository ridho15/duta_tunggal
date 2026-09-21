<?php

/**
 * T4.1 — penomoran terpusat (flag sales.controls.central_numbering): format {PREFIX}-{KODECABANG}-{YYMM}-{SEQ4}, urutan atomik,
 * reset bulanan per cabang, nomor lama utuh, generator lama mendelegasikan, benih & pemeriksaan.
 */

use App\Models\Cabang;
use App\Models\CustomerReturn;
use App\Services\DeliveryOrderService;
use App\Services\DeliveryScheduleService;
use App\Services\DocumentNumberService;
use App\Services\InvoiceService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Services\SuratJalanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Kode cabang pada nomor dokumen = kode cabang huruf besar tanpa tanda baca. */
function numKode(Cabang $cabang): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $cabang->kode));
}

function numCabang(string $kode, array $extra = []): Cabang
{
    return Cabang::factory()->create(array_merge(['kode' => $kode, 'nama' => "Cabang {$kode}", 'status' => 1], $extra));
}

it('format {PREFIX}-{KODECABANG}-{YYMM}-{SEQ4}, berurutan, terpisah per jenis dan per cabang', function () {
    $jkt = numCabang('JKT');
    $sby = numCabang('SBY');
    $service = app(DocumentNumberService::class);
    $period = now()->format('ym');

    expect($service->next('sale_order', $jkt->id))->toBe("SO-JKT-{$period}-0001")
        ->and($service->next('sale_order', $jkt->id))->toBe("SO-JKT-{$period}-0002")
        ->and($service->next('sale_order', $sby->id))->toBe("SO-SBY-{$period}-0001")     // cabang lain mulai dari 1
        ->and($service->next('quotation', $jkt->id))->toBe("QO-JKT-{$period}-0001")      // jenis lain mulai dari 1
        ->and($service->next('sale_order', $jkt->id))->toBe("SO-JKT-{$period}-0003");
});

it('reset bulanan: bulan baru memulai dari 0001; bulan lama tetap berlanjut', function () {
    $jkt = numCabang('JKT');
    $service = app(DocumentNumberService::class);

    expect($service->next('sale_order', $jkt->id, \Carbon\Carbon::parse('2026-08-15')))->toBe('SO-JKT-2608-0001')
        ->and($service->next('sale_order', $jkt->id, \Carbon\Carbon::parse('2026-09-02')))->toBe('SO-JKT-2609-0001')
        ->and($service->next('sale_order', $jkt->id, \Carbon\Carbon::parse('2026-08-30')))->toBe('SO-JKT-2608-0002');
});

it('tanpa cabang → kode "PST"; cabang pengguna yang login dipakai bila tidak diberikan', function () {
    $service = app(DocumentNumberService::class);
    $period = now()->format('ym');
    expect($service->next('delivery_order', null))->toBe("DO-PST-{$period}-0001");

    $jkt = numCabang('JKT');
    Auth::login(\App\Models\User::factory()->create(['cabang_id' => $jkt->id]));
    expect($service->next('delivery_order'))->toBe("DO-JKT-{$period}-0001");
});

it('invoice pajak/non-pajak memakai kode Cabang (INV-PJK-001 → INV-PJK); kode kosong → prefiks bawaan', function () {
    $jkt = numCabang('JKT', ['kode_invoice_pajak' => 'INV-PJK-001', 'kode_invoice_non_pajak' => 'FKT-NP-007']);
    $plain = numCabang('BDG', ['kode_invoice_pajak' => null, 'kode_invoice_non_pajak' => null]);
    $service = app(DocumentNumberService::class);
    $period = now()->format('ym');

    expect($service->next('invoice_tax', $jkt->id))->toBe("INV-PJK-JKT-{$period}-0001")
        ->and($service->next('invoice_non_tax', $jkt->id))->toBe("FKT-NP-JKT-{$period}-0001")
        ->and($service->next('invoice_tax', $plain->id))->toBe("INV-PJK-BDG-{$period}-0001")
        ->and($service->next('invoice', $jkt->id))->toBe("INV-JKT-{$period}-0001");
});

it('nomor lama tidak berubah dan tidak dihitung; nomor yang sudah ada (input manual) dilewati', function () {
    $ctx = stkContext();
    $cabang = $ctx['cabang'];
    $kode = numKode($cabang);
    [$legacy] = stkSaleOrder($ctx, 1, ['so_number' => 'SO-00005']);
    $period = now()->format('ym');
    stkSaleOrder($ctx, 1, ['so_number' => "SO-{$kode}-{$period}-0002"]);   // seseorang mengetik nomor format baru secara manual

    $service = app(DocumentNumberService::class);

    expect($legacy->fresh()->so_number)->toBe('SO-00005')
        ->and($service->next('sale_order', $cabang->id))->toBe("SO-{$kode}-{$period}-0001")
        ->and($service->next('sale_order', $cabang->id))->toBe("SO-{$kode}-{$period}-0003");   // 0002 sudah dipakai → dilewati
});

it('atomik: baris urutan dikunci (SELECT … FOR UPDATE); transaksi yang dibatalkan tidak meninggalkan celah', function () {
    $jkt = numCabang('JKT');
    $service = app(DocumentNumberService::class);
    $period = now()->format('ym');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $service->next('sale_order', $jkt->id);
    $firstSequenceQuery = collect($queries)->first(fn ($sql) => stripos($sql, 'document_sequences') !== false);
    expect($firstSequenceQuery)->toContain('for update');   // pembacaan PERTAMA baris urutan sudah mengunci (bukan hanya pembacaan ulang)

    try {
        DB::transaction(function () use ($service, $jkt) {
            $service->next('sale_order', $jkt->id);   // 0002 — lalu transaksi pemanggil dibatalkan
            throw new \RuntimeException('gagal');
        });
    } catch (\RuntimeException) {
    }

    expect($service->next('sale_order', $jkt->id))->toBe("SO-JKT-{$period}-0002");   // tidak ada celah
});

it('jenis tak dikenal ditolak', function () {
    expect(fn () => app(DocumentNumberService::class)->next('bukan_jenis'))->toThrow(InvalidArgumentException::class);
});

// ------------------------------------------------------------------ generator lama mendelegasikan

it('[flag hidup] generator SO, Quotation, DO, Invoice, SJ, Jadwal, Retur memakai format baru; [flag mati] format lama utuh', function (bool $flag) {
    config(['sales.controls.central_numbering' => $flag]);
    $ctx = stkContext();
    Auth::login($ctx['user']);
    $kode = numKode($ctx['cabang']);
    $period = now()->format('ym');

    $numbers = [
        'so' => app(SalesOrderService::class)->generateSoNumber(),
        'qo' => app(QuotationService::class)->generateCode(),
        'do' => DeliveryOrderService::generateStaticDoNumber(),
        'inv' => app(InvoiceService::class)->generateSalesInvoiceNumber(),
        'sj' => app(SuratJalanService::class)->generateCode(),
        'sch' => DeliveryScheduleService::generateStaticScheduleNumber(),
        'cr' => CustomerReturn::generateReturnNumber(),
    ];

    if ($flag) {
        expect($numbers['so'])->toBe("SO-{$kode}-{$period}-0001")
            ->and($numbers['qo'])->toBe("QO-{$kode}-{$period}-0001")
            ->and($numbers['do'])->toBe("DO-{$kode}-{$period}-0001")
            ->and($numbers['inv'])->toBe("INV-{$kode}-{$period}-0001")
            ->and($numbers['sj'])->toBe("SJ-{$kode}-{$period}-0001")
            ->and($numbers['sch'])->toBe("SCH-{$kode}-{$period}-0001")
            ->and($numbers['cr'])->toBe("CR-{$kode}-{$period}-0001");
    } else {
        $date = now()->format('Ymd');
        expect($numbers['so'])->toBe('SO-00001')
            ->and($numbers['qo'])->toBe("QO-{$date}-0001")
            ->and($numbers['do'])->toBe("DO-{$date}-0001")
            ->and($numbers['inv'])->toBe("INV-{$date}-0001")
            ->and($numbers['sj'])->toBe("SJ-{$date}-0001")
            ->and($numbers['sch'])->toBe("SCH-{$date}-0001")
            ->and($numbers['cr'])->toBe('CR-'.now()->format('Y').'-0001');
        expect(DB::table('document_sequences')->count())->toBe(0);   // flag mati: tabel urutan tidak disentuh
    }
})->with([[true], [false]]);

// ------------------------------------------------------------------ benih & pemeriksaan

it('documents:seed-sequences: dry-run tidak mengubah; --apply menaikkan urutan dari nomor format baru; nomor lama diabaikan; aman diulang', function () {
    $ctx = stkContext();
    $kode = numKode($ctx['cabang']);
    $period = now()->format('ym');
    stkSaleOrder($ctx, 1, ['so_number' => "SO-{$kode}-{$period}-0007"]);
    stkSaleOrder($ctx, 1, ['so_number' => "SO-{$kode}-{$period}-0003"]);
    stkSaleOrder($ctx, 1, ['so_number' => 'SO-00099']);   // format lama → diabaikan
    $dir = sys_get_temp_dir().'/doc-seq-'.uniqid();

    $this->artisan('documents:seed-sequences', ['--out-dir' => $dir])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
    expect(DB::table('document_sequences')->count())->toBe(0);

    $this->artisan('documents:seed-sequences', ['--apply' => true, '--out-dir' => $dir])->assertSuccessful();
    expect((int) DB::table('document_sequences')->where(['type' => 'sale_order', 'period' => $period])->value('last_number'))->toBe(7)
        ->and(glob($dir.'/seed-urutan-dokumen-*.csv'))->not->toBeEmpty();

    // nomor berikutnya melanjutkan dari 7
    expect(app(DocumentNumberService::class)->next('sale_order', $ctx['cabang']->id))->toBe("SO-{$kode}-{$period}-0008");

    // diulang: tidak menurunkan / mengubah
    $this->artisan('documents:seed-sequences', ['--apply' => true, '--no-csv' => true])->assertSuccessful();
    expect((int) DB::table('document_sequences')->where(['type' => 'sale_order', 'period' => $period])->value('last_number'))->toBe(8);
});

it('documents:check-numbering melaporkan nomor ganda, celah urutan, dan urutan tertinggal (hanya informasi)', function () {
    $ctx = stkContext();
    $kode = numKode($ctx['cabang']);
    $period = now()->format('ym');
    stkSaleOrder($ctx, 1, ['so_number' => "SO-{$kode}-{$period}-0001"]);
    stkSaleOrder($ctx, 1, ['so_number' => "SO-{$kode}-{$period}-0004"]);   // celah: 0002, 0003
    DB::table('sale_orders')->where('so_number', "SO-{$kode}-{$period}-0004")->limit(1)->get()->each(fn ($row) => DB::table('sale_orders')->insert(collect((array) $row)->except('id')->all()));

    $this->artisan('documents:check-numbering')
        ->expectsOutputToContain('Nomor GANDA: 1')
        ->expectsOutputToContain('Kelompok dengan celah urutan: 1')
        ->expectsOutputToContain('Kelompok dengan urutan tertinggal: 1')
        ->assertSuccessful();
});
