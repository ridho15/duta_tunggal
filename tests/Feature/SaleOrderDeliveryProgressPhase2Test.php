<?php

/**
 * Fase 2 audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md):
 *  - Isu 3: ringkasan & status SO harus mengikuti kuantitas yang benar-benar terkirim
 *  - Isu 5: pemilihan SO di Delivery Order dibatasi (status, sisa, customer & alamat sama), status berbahasa Indonesia
 */

use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderSourceValidator;
use App\Services\SaleOrderDeliveryProgress;
use App\Services\SaleOrderStatusSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function phase2Context(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'P2-' . strtoupper(substr(uniqid(), -6)), 'nama' => 'Cabang Fase 2', 'status' => 1]);

    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo(['view any sales order', 'view sales order', 'update sales order']);
    Auth::login($user);

    $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'name' => 'Gudang P2', 'kode' => 'G-' . strtoupper(substr(uniqid(), -5)), 'status' => 1]);

    ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);
    $revenue = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue']);
    ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran', 'type' => 'Liability']);
    $cogs = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP', 'type' => 'Expense']);
    $goods = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
    $inventory = ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Persediaan', 'type' => 'Asset']);

    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'P2-' . strtoupper(substr(uniqid(), -7)),
        'cost_price' => 5000, 'sell_price' => 8000,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id,
        'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'user', 'currency', 'warehouse', 'customer', 'product');
}

/** SO Disetujui 20 pcs dengan satu item. */
function phase2SaleOrder(array $ctx, array $so = [], int $qty = 20): array
{
    $saleOrder = SaleOrder::create(array_merge([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'so_number' => 'SO-P2-' . strtoupper(substr(uniqid(), -6)),
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $ctx['currency']->id,
        'exchange_rate' => 1.0,
        'tempo_pembayaran' => 30,
        'shipped_to' => 'Jl. Pengiriman No. 1, Jakarta',
    ], $so));

    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $ctx['product']->id,
        'quantity' => $qty,
        'delivered_quantity' => 0,
        'unit_price' => 8000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'currency_id' => $ctx['currency']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ]);

    return [$saleOrder, $item];
}

/** DO (terhubung ke SO lewat pivot) dengan satu item berkuantitas $qty. */
function phase2DeliveryOrder(array $ctx, SaleOrder $saleOrder, SaleOrderItem $item, float $qty, string $status = 'draft', bool $linkPivot = true): DeliveryOrder
{
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-P2-' . strtoupper(substr(uniqid(), -6)),
        'delivery_date' => now(),
        'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ]);
    if ($linkPivot) {
        $deliveryOrder->salesOrders()->attach($saleOrder->id);
    }

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $item->id,
        'product_id' => $ctx['product']->id,
        'quantity' => $qty,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity' => $qty,
    ]);

    // jalur status normal (memicu observer), mis. draft -> sent -> completed
    foreach (match ($status) {
        'draft' => [],
        'sent' => ['sent'],
        'completed' => ['sent', 'completed'],
        default => [$status],
    } as $next) {
        $deliveryOrder->update(['status' => $next]);
    }

    return $deliveryOrder->fresh();
}

function phase2Progress(SaleOrderItem $item, ?int $excludeDo = null): array
{
    return app(SaleOrderDeliveryProgress::class)->forItems([$item->id], $excludeDo)[$item->id];
}

// ───────────────────────────── Isu 3: progres & status ─────────────────────────────

it('Isu 3: DO terbuka dihitung "dalam proses", bukan "terkirim"; sisanya belum dijadwalkan', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);

    phase2DeliveryOrder($ctx, $so, $item, 12, 'approved');

    expect(phase2Progress($item))->toMatchArray(['ordered' => 20.0, 'delivered' => 0.0, 'in_process' => 12.0, 'remaining' => 20.0, 'available' => 8.0]);
    expect($so->fresh()->status)->toBe('approved');   // belum ada yang keluar gudang
});

it('Isu 3: contoh UAT — 12 dari 20 pcs terkirim => terkirim 12, sisa 8, status "Dikirim Sebagian" (bukan completed)', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);

    phase2DeliveryOrder($ctx, $so, $item, 12, 'completed');

    expect(phase2Progress($item))->toMatchArray(['delivered' => 12.0, 'in_process' => 0.0, 'remaining' => 8.0, 'available' => 8.0]);
    expect((float) $item->fresh()->delivered_quantity)->toBe(12.0);   // cache
    $so = $so->fresh();
    expect($so->status)->toBe('partially_delivered')->and($so->completed_at)->toBeNull();
});

it('Isu 3: status berubah ke completed hanya setelah SEMUA kuantitas terkirim', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);

    phase2DeliveryOrder($ctx, $so, $item, 12, 'completed');
    expect($so->fresh()->status)->toBe('partially_delivered');

    phase2DeliveryOrder($ctx, $so, $item, 8, 'completed');
    $so = $so->fresh();
    expect($so->status)->toBe('completed')->and($so->completed_at)->not->toBeNull()
        ->and((float) $item->fresh()->delivered_quantity)->toBe(20.0);
});

it('Isu 3: guard menolak alokasi ganda — dua DO tidak boleh mengklaim kuantitas yang sama', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    phase2DeliveryOrder($ctx, $so, $item, 12, 'approved');   // masih terbuka, belum dikirim

    $do2 = DeliveryOrder::create(['do_number' => 'DO-P2-X', 'delivery_date' => now(), 'status' => 'draft', 'cabang_id' => $ctx['cabang']->id]);

    expect(fn () => DeliveryOrderItem::create(['delivery_order_id' => $do2->id, 'sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => 9]))
        ->toThrow(\Exception::class, 'melebihi sisa quantity yang tersedia (8)');

    expect(DeliveryOrderItem::create(['delivery_order_id' => $do2->id, 'sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => 8]))->toBeInstanceOf(DeliveryOrderItem::class);
});

it('Isu 3: DO yang ditutup (closed) melepas kuantitasnya kembali ke SO', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    $do = phase2DeliveryOrder($ctx, $so, $item, 12, 'approved');
    expect(phase2Progress($item)['available'])->toBe(8.0);

    $do->update(['status' => 'closed']);
    expect(phase2Progress($item)['available'])->toBe(20.0);
});

it('Isu 3: pengiriman gagal mengembalikan terkirim ke 0 dan status SO mundur ke approved', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    $do = phase2DeliveryOrder($ctx, $so, $item, 12, 'sent');
    expect($so->fresh()->status)->toBe('partially_delivered');

    $do->update(['status' => 'delivery_failed']);

    expect((float) $item->fresh()->delivered_quantity)->toBe(0.0)
        ->and($so->fresh()->status)->toBe('approved')
        ->and(phase2Progress($item)['in_process'])->toBe(12.0);   // tetap terikat: bisa dijadwalkan ulang
});

it('Isu 3: DO dihapus mengembalikan progres dan status SO', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    $do = phase2DeliveryOrder($ctx, $so, $item, 12, 'completed');
    expect($so->fresh()->status)->toBe('partially_delivered');

    $do->delete();

    expect((float) $item->fresh()->delivered_quantity)->toBe(0.0)
        ->and($so->fresh()->status)->toBe('approved')
        ->and(phase2Progress($item)['available'])->toBe(20.0);
});

it('Isu 3: SO tanpa DO (Ambil Sendiri diselesaikan manual) TIDAK dimundurkan oleh sinkronisasi', function () {
    $ctx = phase2Context();
    [$so] = phase2SaleOrder($ctx, ['status' => 'completed', 'tipe_pengiriman' => 'Ambil Sendiri']);

    expect(app(SaleOrderStatusSynchronizer::class)->sync($so->fresh()))->toBeNull()
        ->and($so->fresh()->status)->toBe('completed');
});

it('Isu 3: DO tanpa pivot ke SO tidak mengubah status SO (perilaku lama), namun cache terisi', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);

    phase2DeliveryOrder($ctx, $so, $item, 12, 'completed', linkPivot: false);

    expect($so->fresh()->status)->toBe('approved')
        ->and((float) $item->fresh()->delivered_quantity)->toBe(12.0);
});

it('Isu 3: ringkasan di halaman SO memperlihatkan Terkirim, Dalam Proses DO, dan status Dikirim Sebagian', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    phase2DeliveryOrder($ctx, $so, $item, 12, 'completed');
    phase2DeliveryOrder($ctx, $so, $item, 5, 'approved');

    Livewire::actingAs($ctx['user'])
        ->test(ViewSaleOrder::class, ['record' => $so->getKey()])
        ->assertSee('Dikirim Sebagian')
        ->assertSee('Total Qty Terkirim')
        ->assertSee('Dalam Proses DO')
        ->assertSee('Sisa Qty Belum Dikirim')
        ->assertSee('Belum Dijadwalkan')
        ->assertDontSee('partially_delivered');
});

// ───────────────────── Isu 5: validator sumber DO ─────────────────────

it('Isu 5: SO yang sudah Selesai TIDAK bisa dijadikan sumber DO; Dikirim Sebagian bisa', function () {
    $ctx = phase2Context();
    [$done] = phase2SaleOrder($ctx, ['status' => 'completed']);
    [$partial] = phase2SaleOrder($ctx, ['status' => 'partially_delivered']);
    [$approved] = phase2SaleOrder($ctx);

    $validator = app(DeliveryOrderSourceValidator::class);

    expect($validator->errors([$done->id]))->not->toBeEmpty()
        ->and(implode(' ', $validator->errors([$done->id])))->toContain('Selesai')
        ->and($validator->errors([$partial->id]))->toBeEmpty()
        ->and($validator->errors([$approved->id]))->toBeEmpty();
});

it('Isu 5: SO tanpa sisa yang belum terikat DO ditolak; DO yang sedang diedit dikecualikan', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    $do = phase2DeliveryOrder($ctx, $so, $item, 20, 'approved');   // seluruhnya sudah dialokasikan

    $validator = app(DeliveryOrderSourceValidator::class);
    expect(implode(' ', $validator->errors([$so->id])))->toContain('tidak memiliki sisa kuantitas');
    expect($validator->errors([$so->id], $do->id))->toBeEmpty();               // mengedit DO itu sendiri
    expect($validator->errors([$so->id], null, [$so->id]))->toBeEmpty();       // SO yang sudah terhubung tidak diperiksa ulang
});

it('Isu 5: menggabung SO hanya untuk customer DAN alamat kirim yang sama', function () {
    $ctx = phase2Context();
    $other = Customer::factory()->create(['cabang_id' => $ctx['cabang']->id]);

    [$a] = phase2SaleOrder($ctx, ['shipped_to' => 'Jl. Sudirman No. 5, Jakarta']);
    [$sameAddr] = phase2SaleOrder($ctx, ['shipped_to' => '  jl. sudirman  no. 5,   JAKARTA. ']);   // beda spasi/kapital saja
    [$diffAddr] = phase2SaleOrder($ctx, ['shipped_to' => 'Jl. Thamrin No. 9, Jakarta']);
    [$noAddr] = phase2SaleOrder($ctx, ['shipped_to' => null]);
    [$diffCustomer] = phase2SaleOrder($ctx, ['customer_id' => $other->id, 'shipped_to' => 'Jl. Sudirman No. 5, Jakarta']);

    $validator = app(DeliveryOrderSourceValidator::class);

    expect($validator->errors([$a->id, $sameAddr->id]))->toBeEmpty();
    expect(implode(' ', $validator->errors([$a->id, $diffAddr->id])))->toContain('alamat kirim yang sama');
    expect(implode(' ', $validator->errors([$a->id, $noAddr->id])))->toContain('Alamat kirim belum diisi');
    expect(implode(' ', $validator->errors([$a->id, $diffCustomer->id])))->toContain('customer yang sama');
    expect($validator->errors([$a->id]))->toBeEmpty();   // satu SO: alamat tidak diperiksa
});

it('Isu 5: assertValid melempar ValidationException pada field salesOrders', function () {
    $ctx = phase2Context();
    [$done] = phase2SaleOrder($ctx, ['status' => 'completed']);

    expect(fn () => app(DeliveryOrderSourceValidator::class)->assertValid([$done->id]))->toThrow(ValidationException::class);
});

it('Isu 5: scope deliverable menyaring SO tanpa sisa dan mengikutkan SO yang sudah terhubung', function () {
    $ctx = phase2Context();
    [$open] = phase2SaleOrder($ctx);
    [$full, $fullItem] = phase2SaleOrder($ctx);
    phase2DeliveryOrder($ctx, $full, $fullItem, 20, 'approved');
    [$done] = phase2SaleOrder($ctx, ['status' => 'completed']);

    $ids = SaleOrder::withoutGlobalScopes()->deliverable()->pluck('id')->all();
    expect($ids)->toContain($open->id)->not->toContain($full->id)->not->toContain($done->id);

    expect(SaleOrder::withoutGlobalScopes()->deliverable([$full->id])->pluck('id')->all())->toContain($full->id);
});

it('Isu 5: label status berbahasa Indonesia untuk SO, DO, dan item DO', function () {
    expect(SaleOrder::statusLabel('partially_delivered'))->toBe('Dikirim Sebagian')
        ->and(SaleOrder::statusLabel('approved'))->toBe('Disetujui')
        ->and(SaleOrder::statusColor('partially_delivered'))->toBe('warning')
        ->and(DeliveryOrder::statusLabel('request_stock'))->toBe('Menunggu Konfirmasi Stok')
        ->and(DeliveryOrder::statusLabel('approved'))->toBe('Disetujui')
        ->and(DeliveryOrder::statusLabel('sent'))->toBe('Sedang Dikirim')
        ->and(\App\Models\DeliveryOrderItem::statusLabel('requested'))->toBe('Menunggu Konfirmasi Gudang')
        ->and(SaleOrder::statusLabel('status_baru_x'))->toBe('Status baru x');   // fallback tetap terbaca

    // setiap status pada enum DB harus punya label (tidak ada yang tampil mentah)
    foreach (['draft', 'request_approve', 'request_close', 'approved', 'closed', 'completed', 'partial_confirmed', 'confirmed', 'received', 'canceled', 'reject', 'partially_delivered'] as $status) {
        expect(SaleOrder::STATUS_LABELS)->toHaveKey($status);
    }
    foreach (['draft', 'sent', 'confirmed', 'received', 'supplier', 'completed', 'request_approve', 'approved', 'request_close', 'closed', 'reject', 'delivery_failed', 'request_stock', 'partial'] as $status) {
        expect(DeliveryOrder::STATUS_LABELS)->toHaveKey($status);
    }
});

// ───────────── Regresi: invoice SO parsial harus tetap muncul di penerimaan customer ─────────────

it('Regresi: invoice dari SO Dikirim Sebagian tetap muncul di daftar penerimaan customer (disaring dari AR, Fase 5A)', function () {
    $ctx = phase2Context();
    [$partial] = phase2SaleOrder($ctx, ['status' => 'partially_delivered']);
    [$paidSo] = phase2SaleOrder($ctx, ['status' => 'completed']);

    foreach ([[$partial, 'INV-P2-PARSIAL'], [$paidSo, 'INV-P2-LUNAS']] as [$order, $number]) {
        Invoice::create([
            'invoice_number' => $number, 'from_model_type' => SaleOrder::class, 'from_model_id' => $order->id,
            'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100, 'total' => 100, 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
        ]);
    }
    // Invoice kedua sudah lunas: sisa piutang 0
    \App\Models\AccountReceivable::whereHas('invoice', fn ($q) => $q->where('invoice_number', 'INV-P2-LUNAS'))
        ->update(['paid' => 100, 'remaining' => 0]);

    $numbers = CustomerReceiptResource::invoiceableInvoicesQuery($ctx['customer']->id)->pluck('invoices.invoice_number')->all();

    // Daftar mengikuti sisa piutang (AR), bukan status SO
    expect($numbers)->toContain('INV-P2-PARSIAL')->not->toContain('INV-P2-LUNAS');
});

// ───────────────────────────── Backfill data lama ─────────────────────────────

it('Backfill: SO "completed" padahal baru 12/20 terkirim dibetulkan lewat --apply; dry-run tidak mengubah apa pun', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    $do = phase2DeliveryOrder($ctx, $so, $item, 12, 'draft');

    // Keadaan warisan aturan lama: DO selesai, SO 'completed', cache belum terisi (dibuat lewat query builder, tanpa observer)
    DB::table('delivery_orders')->where('id', $do->id)->update(['status' => 'completed']);
    DB::table('sale_orders')->where('id', $so->id)->update(['status' => 'completed', 'completed_at' => now()]);
    DB::table('sale_order_items')->where('id', $item->id)->update(['delivered_quantity' => 0]);

    Artisan::call('sales:resync-so-status');   // dry-run
    expect($so->fresh()->status)->toBe('completed')
        ->and((float) $item->fresh()->delivered_quantity)->toBe(0.0);

    Artisan::call('sales:resync-so-status', ['--apply' => true]);

    $so = $so->fresh();
    expect($so->status)->toBe('partially_delivered')
        ->and($so->completed_at)->toBeNull()
        ->and((float) $item->fresh()->delivered_quantity)->toBe(12.0);

    foreach (glob(storage_path('app/backfill/resync-so-status-*.csv')) ?: [] as $csv) {
        @unlink($csv);
    }
});

it('Backfill: SO Ambil Sendiri tanpa DO tidak disentuh', function () {
    $ctx = phase2Context();
    [$so] = phase2SaleOrder($ctx, ['status' => 'completed', 'tipe_pengiriman' => 'Ambil Sendiri']);

    Artisan::call('sales:resync-so-status', ['--apply' => true]);

    expect($so->fresh()->status)->toBe('completed');
});

// ───────────── Data legacy: item tanpa riwayat DO memakai cache delivered_quantity ─────────────

it('Legacy: SO hasil impor dengan delivered_quantity tanpa DO tetap dihitung terkirim, bukan 0', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    DB::table('sale_order_items')->where('id', $item->id)->update(['delivered_quantity' => 15]);   // hasil impor sistem lama

    expect(phase2Progress($item->fresh()))->toMatchArray(['delivered' => 15.0, 'in_process' => 0.0, 'remaining' => 5.0, 'available' => 5.0]);

    // guard: hanya sisa 5 yang boleh dibuatkan DO
    $do = DeliveryOrder::create(['do_number' => 'DO-P2-LEG', 'delivery_date' => now(), 'status' => 'draft', 'cabang_id' => $ctx['cabang']->id]);
    expect(fn () => DeliveryOrderItem::create(['delivery_order_id' => $do->id, 'sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => 6]))
        ->toThrow(\Exception::class, 'melebihi sisa quantity yang tersedia (5)');
});

it('Legacy: SO yang seluruhnya sudah terkirim menurut cache tidak muncul sebagai SO yang bisa dikirim', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    DB::table('sale_order_items')->where('id', $item->id)->update(['delivered_quantity' => 20]);

    expect(SaleOrder::withoutGlobalScopes()->deliverable()->pluck('id')->all())->not->toContain($so->id);
    expect(implode(' ', app(DeliveryOrderSourceValidator::class)->errors([$so->id])))->toContain('tidak memiliki sisa kuantitas');
});

it('Legacy: resync tidak menyentuh SO impor yang belum punya DO sama sekali', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx, ['status' => 'completed']);
    DB::table('sale_order_items')->where('id', $item->id)->update(['delivered_quantity' => 20]);

    Artisan::call('sales:resync-so-status', ['--apply' => true]);

    expect($so->fresh()->status)->toBe('completed')
        ->and((float) $item->fresh()->delivered_quantity)->toBe(20.0);
});

// ───────────── Kabel di halaman Create Delivery Order (server-side) ─────────────

function phase2CreatePage(array $data): array
{
    $page = new \App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder();
    $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    return $method->invoke($page, $data);
}

it('Isu 5: halaman Create DO menolak SO Selesai di sisi server', function () {
    $ctx = phase2Context();
    [$done, $doneItem] = phase2SaleOrder($ctx, ['status' => 'completed']);

    expect(fn () => phase2CreatePage([
        'do_number' => 'DO-P2-CR1',
        'salesOrders' => [$done->id],
        'delivery_date' => now()->toDateTimeString(),
        'deliveryOrderItem' => [['sale_order_item_id' => $doneItem->id, 'product_id' => $ctx['product']->id, 'quantity' => 5]],
    ]))->toThrow(ValidationException::class, 'Selesai');
});

it('Isu 5: halaman Create DO menolak penggabungan SO dengan alamat kirim berbeda', function () {
    $ctx = phase2Context();
    [$a, $itemA] = phase2SaleOrder($ctx, ['shipped_to' => 'Jl. A No. 1']);
    [$b] = phase2SaleOrder($ctx, ['shipped_to' => 'Jl. B No. 2']);

    expect(fn () => phase2CreatePage([
        'do_number' => 'DO-P2-CR2',
        'salesOrders' => [$a->id, $b->id],
        'delivery_date' => now()->toDateTimeString(),
        'deliveryOrderItem' => [['sale_order_item_id' => $itemA->id, 'product_id' => $ctx['product']->id, 'quantity' => 5]],
    ]))->toThrow(ValidationException::class, 'alamat kirim yang sama');
});

it('Isu 5: halaman Create DO menerima SO Dikirim Sebagian untuk sisanya, dan menolak melebihi sisa', function () {
    $ctx = phase2Context();
    [$so, $item] = phase2SaleOrder($ctx);
    phase2DeliveryOrder($ctx, $so, $item, 12, 'completed');   // -> partially_delivered, sisa 8
    expect($so->fresh()->status)->toBe('partially_delivered');

    $payload = fn (float $qty) => [
        'do_number' => 'DO-P2-CR3',
        'salesOrders' => [$so->id],
        'delivery_date' => now()->toDateTimeString(),
        'deliveryOrderItem' => [['sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty]],
    ];

    expect(phase2CreatePage($payload(8)))->toBeArray();   // sisa 8: lolos validasi
    expect(fn () => phase2CreatePage($payload(9)))->toThrow(ValidationException::class);   // 9 > sisa 8
});
