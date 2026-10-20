<?php

/**
 * Tahap 3 — "Ada kurang / rusak" (Retur DO dari HP) dan picking list per gudang.
 */

use App\Filament\Pages\Hp\ReturDoPage;
use App\Models\ReturnProduct;
use App\Services\Hp\DeliveryOrderShortageReturn;
use App\Services\Hp\PickingList;
use App\Support\MenuAccess;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['sales.stock' => ['require_surat_jalan' => false, 'ledger' => false, 'strict_dispatch' => true, 'reserve_on_so_approve' => false, 'block_short_approval' => false]]);

    $this->ctx = stkContext();
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 50);
    [$so, $soItem] = stkSaleOrder($this->ctx, 5);
    $this->do = stkDeliveryOrder($this->ctx, $so, $soItem, 5, 'approved');
    $this->item = $this->do->deliveryOrderItem()->first();

    $this->checker = \App\Models\User::factory()->create(['cabang_id' => $this->ctx['cabang']->id, 'warehouse_id' => $this->ctx['warehouse']->id]);
    $this->checker->assignRole('Checker');
    MenuAccess::flush();
});

it('retur dari HP membuat draft DO…R: kurang = good, rusak = damage', function () {
    $return = app(DeliveryOrderShortageReturn::class)->create($this->do, [$this->item->id => ['short' => 1, 'reject' => 2]]);

    expect($return->return_number)->toBe($this->do->do_number.'R')
        ->and($return->status)->toBe('draft')
        ->and($return->returnProductItem()->where('condition', 'good')->value('quantity'))->toEqual(1)
        ->and($return->returnProductItem()->where('condition', 'damage')->value('quantity'))->toEqual(2);
});

it('retur ditolak bila kurang + rusak melebihi qty DO atau semuanya nol', function () {
    $service = app(DeliveryOrderShortageReturn::class);

    expect(fn () => $service->create($this->do, [$this->item->id => ['short' => 4, 'reject' => 2]]))->toThrow(ValidationException::class);
    expect(fn () => $service->create($this->do, [$this->item->id => ['short' => 0, 'reject' => 0]]))->toThrow(ValidationException::class);
    expect(ReturnProduct::count())->toBe(0);
});

it('halaman HP: tombol −/+ dibatasi qty DO dan simpan membuat retur', function () {
    $this->actingAs($this->checker);

    Livewire::test(ReturDoPage::class, ['record' => $this->do->id])
        ->call('step', $this->item->id, 'short', 1)
        ->call('step', $this->item->id, 'short', 10)
        ->assertSet("lines.{$this->item->id}.short", 5)
        ->call('step', $this->item->id, 'reject', 1)
        ->assertSet("lines.{$this->item->id}.reject", 0)
        ->call('save');

    expect(ReturnProduct::where('from_model_id', $this->do->id)->exists())->toBeTrue();
});

it('picking list menjumlahkan barang per rak & produk dari DO siap kirim', function () {
    [$so2, $item2] = stkSaleOrder($this->ctx, 3);
    stkDeliveryOrder($this->ctx, $so2, $item2, 3, 'approved');

    $data = app(PickingList::class)->build($this->ctx['warehouse']->id);

    expect($data['orders'])->toHaveCount(2)
        ->and($data['rows'])->toHaveCount(1)
        ->and($data['rows']->first()['total'])->toEqual(8)
        ->and($data['rows']->first()['orders'])->toHaveCount(2);
});

it('PDF picking list bisa dicetak checker gudang tersebut', function () {
    $this->actingAs($this->checker)
        ->get(route('pdf.picking-list', ['warehouse' => $this->ctx['warehouse']->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
