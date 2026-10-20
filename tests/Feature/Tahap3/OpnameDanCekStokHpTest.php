<?php

/**
 * Tahap 3 — Stock Opname blind count di HP dan Cek Stok HP + scan (6.12).
 */

use App\Filament\Pages\Hp\CekStokHpPage;
use App\Filament\Pages\Hp\OpnameHpPage;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Support\MenuAccess;
use Livewire\Livewire;

beforeEach(function () {
    $this->ctx = stkContext();
    $this->checker = \App\Models\User::factory()->create(['cabang_id' => $this->ctx['cabang']->id, 'warehouse_id' => $this->ctx['warehouse']->id]);
    $this->checker->assignRole('Checker');
    MenuAccess::flush();
});

function tahap3Opname(array $ctx, array $quantities): StockOpname
{
    $opname = StockOpname::create([
        'opname_number' => 'OP-UJI-'.strtoupper(substr(uniqid(), -5)),
        'opname_date' => now(),
        'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'draft',
        'created_by' => $ctx['user']->id,
    ]);

    foreach ($quantities as $qty) {
        $product = stkProduct($ctx);
        stkSetStock($product, $ctx['warehouse'], $qty);
    }

    return $opname;
}

it('jumlah sistem tidak pernah tampil selama menghitung (blind count)', function () {
    $opname = tahap3Opname($this->ctx, [7777]);
    $this->actingAs($this->checker);

    $component = Livewire::test(OpnameHpPage::class)->call('start', $opname->id);

    expect($opname->fresh()->status)->toBe('in_progress');
    $component->assertSee('Hitung barang ini')->assertDontSee('7777')->assertDontSee('7.777');
    expect(json_encode($component->instance()->all()))->not->toContain('7777');
});

it('simpan & berikutnya mengisi hasil hitung, selesai ditolak bila masih ada yang belum dihitung', function () {
    $opname = tahap3Opname($this->ctx, [5, 9]);
    $this->actingAs($this->checker);

    $component = Livewire::test(OpnameHpPage::class)->call('start', $opname->id);
    $count = StockOpnameItem::where('stock_opname_id', $opname->id)->count();

    $component->set('qty', '4')->call('saveAndNext');
    expect(StockOpnameItem::where('stock_opname_id', $opname->id)->whereNotNull('physical_qty')->count())->toBe(1);

    $component->call('finish');
    expect($opname->fresh()->status)->toBe('in_progress');

    for ($i = 1; $i < $count; $i++) {
        $component->set('qty', '9')->call('saveAndNext');
    }
    $component->assertSee('Semua barang sudah dihitung')->call('finish');

    expect($opname->fresh()->status)->toBe('completed')
        ->and(StockOpnameItem::where('stock_opname_id', $opname->id)->whereNotNull('difference_qty')->count())->toBe($count);
});

it('Cek Stok HP: stok bebas = fisik − dikunci DO, dan scan membuka barangnya', function () {
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 20, 6);
    $this->actingAs($this->checker);

    Livewire::test(CekStokHpPage::class)
        ->dispatch('barcode-scanned', code: $this->ctx['product']->sku)
        ->assertSet('productId', $this->ctx['product']->id)
        ->assertSee($this->ctx['product']->name)
        ->assertSee('14')
        ->assertSee('dikunci DO 6');
});
