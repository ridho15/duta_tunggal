<?php

/**
 * T7.3 — Anggaran query per aksi (usulan 19b): tes GAGAL bila jumlah kueri sebuah aksi kunci membengkak, dan daftar SO tidak boleh
 * menambah kueri per baris (N+1). Anggaran = hasil ukur data dev + ±15%; target akhir audit (Jadwal Selesai ≤ 60) dicatat sebagai lanjutan.
 */

use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function qbCount(callable $fn): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    $fn();

    return $count;
}

it('anggaran: Jadwal Mulai/Selesai (rantai DO → stok → jurnal → invoice), SO Approve, Nota Kredit Terbit', function () {
    config(['sales.controls.credit_notes' => true]);
    $ctx = stkContext();

    [$so, $item] = stkSaleOrder($ctx, 5);
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);
    $deliveryOrder = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');
    $schedule = stkSchedule($ctx, $deliveryOrder);

    $start = qbCount(fn () => $schedule->update(['status' => 'on_the_way']));
    $complete = qbCount(fn () => $schedule->fresh()->update(['status' => 'delivered']));

    [$so2] = stkSaleOrder($ctx, 5, ['status' => 'request_approve']);
    Auth::login(ctlUser($ctx, 'Super Admin', ['response sales order']));
    $approve = qbCount(fn () => app(\App\Services\SalesOrderService::class)->approve($so2->fresh()));

    $finance = ctlUser($ctx, 'Finance Manager', ['approve credit note', 'create credit note']);
    Auth::login($finance);
    [$invoice, $invoiceItem] = ctlCreditInvoice($ctx);
    $creditNote = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$invoiceItem->id => 3], 'Barang rusak dikembalikan customer', actor: $finance);
    $issue = qbCount(fn () => app(CreditNoteService::class)->issue($creditNote, $finance));

    // Terukur (data dev): mulai 37 · selesai 100 (target audit ≤ 60, lanjutan) · approve 7 · terbit 37
    expect($start)->toBeLessThanOrEqual(45, "Jadwal Mulai: {$start} kueri")
        ->and($complete)->toBeLessThanOrEqual(115, "Jadwal Selesai: {$complete} kueri")
        ->and($approve)->toBeLessThanOrEqual(10, "SO Approve: {$approve} kueri")
        ->and($issue)->toBeLessThanOrEqual(45, "Nota Kredit Terbit: {$issue} kueri");
});

it('daftar Sales Order: jumlah kueri TIDAK bertambah per baris (10 vs 25 baris) dan tetap di bawah anggaran', function () {
    $ctx = stkContext();
    for ($i = 0; $i < 30; $i++) {
        stkSaleOrder($ctx, 2);
    }
    $viewer = ctlUser($ctx, 'Super Admin', ['view any sale order', 'view sale order', 'create purchase order']);

    $render = fn (int $perPage) => qbCount(fn () => Livewire::actingAs($viewer)->test(\App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders::class)->set('tableRecordsPerPage', $perPage)->assertSuccessful());
    $ten = $render(10);
    $twentyFive = $render(25);

    expect($twentyFive - $ten)->toBeLessThanOrEqual(3, "10 baris = {$ten} kueri, 25 baris = {$twentyFive} kueri (N+1?)")->and($twentyFive)->toBeLessThanOrEqual(60);
});
