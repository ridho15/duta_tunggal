<?php

namespace Tests\Feature\Kasir;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\PosOrder;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladePosWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-POS-TEST',
            'nama' => 'Cabang Kasir POS Test',
            'status' => true,
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);

        $unique = uniqid();
        $this->user = User::factory()->create([
            'email' => "cashier_{$unique}@example.com",
            'username' => "cashier_{$unique}",
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->user->assignRole($roleAdmin);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'name' => 'Gudang Toko POS Test',
                'cabang_id' => $this->cabang->id,
                'kode' => 'G-POS-01',
                'tipe' => 'utama',
                'status' => 'aktif',
            ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create([
            'name' => 'Kategori POS Test',
            'code' => 'KAT-POS',
        ]);

        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create([
            'name' => 'Pcs',
            'symbol' => 'pcs',
        ]);

        $cashCoa = ChartOfAccount::firstOrCreate(['code' => '1111.03'], ['name' => 'KAS PENJUALAN', 'type' => 'Asset', 'is_active' => true]);
        $salesCoa = ChartOfAccount::firstOrCreate(['code' => '4100.10'], ['name' => 'Penjualan Produk', 'type' => 'Revenue', 'is_active' => true]);
        $invCoa = ChartOfAccount::firstOrCreate(['code' => '1140.10'], ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'is_active' => true]);
        $cogsCoa = ChartOfAccount::firstOrCreate(['code' => '5100.10'], ['name' => 'HPP Barang Dagangan', 'type' => 'Expense', 'is_active' => true]);

        // Product A: Normal with harga_batas 50.000, sell_price 65.000, cost_price 45.000
        $this->productA = Product::withoutGlobalScopes()->create([
            'name' => 'Semen Gresik 50kg POS Test',
            'sku' => 'SMN-GRS-' . $unique,
            'kode_merk' => 'MRK-GRS',
            'product_category_id' => $category->id,
            'cabang_id' => $this->cabang->id,
            'uom_id' => $uom->id,
            'sales_coa_id' => $salesCoa->id,
            'inventory_coa_id' => $invCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'cost_price' => 45000,
            'sell_price' => 65000,
            'harga_batas' => 50000,
            'is_active' => true,
        ]);

        // Product B: Normal with cost_price 20.000, sell_price 30.000, harga_batas 0 (fallback to cost_price)
        $this->productB = Product::withoutGlobalScopes()->create([
            'name' => 'Paku Beton 10cm POS Test',
            'sku' => 'PKU-BTN-' . $unique,
            'kode_merk' => 'MRK-BTN',
            'product_category_id' => $category->id,
            'cabang_id' => $this->cabang->id,
            'uom_id' => $uom->id,
            'sales_coa_id' => $salesCoa->id,
            'inventory_coa_id' => $invCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'cost_price' => 20000,
            'sell_price' => 30000,
            'harga_batas' => 0,
            'is_active' => true,
        ]);

        // Setup stock in warehouse (Product::created automatically creates 0-stock rows for all warehouses)
        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->productA->id,
                'warehouse_id' => $this->warehouse->id,
            ],
            [
                'qty_available' => 100,
                'qty_reserved' => 0,
            ]
        );

        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->productB->id,
                'warehouse_id' => $this->warehouse->id,
            ],
            [
                'qty_available' => 50,
                'qty_reserved' => 0,
            ]
        );
    }

    public function test_cashier_can_view_pos_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('kasir.pos.index'));

        $response->assertStatus(200);
        $response->assertSee('Kasir POS Ritel');
        $response->assertSee('Scan Barcode / Cari Produk Cepat');
        $response->assertSee('Keranjang Belanja');
    }

    public function test_cashier_can_open_shift_with_starting_cash(): void
    {
        $response = $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Shift Pagi Kasir 1',
        ]);

        $response->assertRedirect(route('kasir.pos.index'));
        $response->assertSessionHas('success');

        $shift = PosShift::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->where('status', 'open')
            ->first();

        $this->assertNotNull($shift);
        $this->assertEquals(500000, (float) $shift->starting_cash);
        $this->assertEquals($this->warehouse->id, $shift->warehouse_id);
    }

    public function test_cashier_cannot_open_duplicate_active_shift(): void
    {
        // Open first shift
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 300000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Attempt second shift
        $response = $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 400000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasErrors('starting_cash');
    }

    public function test_barcode_search_api_returns_product_details(): void
    {
        $response = $this->actingAs($this->user)->get(route('kasir.pos.search', [
            'barcode' => $this->productA->sku,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'found' => true,
            'product' => [
                'id' => $this->productA->id,
                'name' => $this->productA->name,
                'sku' => $this->productA->sku,
                'sell_price' => 65000,
                'harga_batas' => 50000,
                'available_stock' => 100,
            ],
        ]);
    }

    public function test_pos_transaction_violating_harga_batas_is_rejected(): void
    {
        // Buka shift terlebih dahulu
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Coba jual Product A (harga_batas = 50.000) dengan unit_price = 45.000 (di bawah batas!)
        $response = $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => 45000, // VIOLATION!
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.unit_price']);

        // Pastikan tidak ada order yang tersimpan
        $this->assertDatabaseMissing('pos_orders', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_pos_transaction_violating_effective_harga_batas_via_discount_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Product A: unit_price 65.000, tapi diskon 40.000 untuk 2 item -> net = (130.000 - 40.000)/2 = 45.000 (< 50.000)
        $response = $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => 65000,
                    'discount_amount' => 40000, // VIOLATION via effective line discount!
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.unit_price']);
    }

    public function test_pos_cash_transaction_completes_successfully_and_deducts_stock(): void
    {
        // 1. Buka shift kasir
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $shift = PosShift::withoutGlobalScopes()->where('user_id', $this->user->id)->where('status', 'open')->first();

        $initialStockA = (float) InventoryStock::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        // 2. Transaksi sah: 2 Semen @ Rp 60.000 (di atas batas 50.000) = Rp 120.000, Bayar Rp 150.000, Kembalian Rp 30.000
        $response = $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Pak Haji Syahrul',
            'payment_method' => 'cash',
            'payment_amount' => 150000,
            'discount_amount' => 0,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => 60000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // 3. Verifikasi PosOrder tersimpan
        $order = PosOrder::withoutGlobalScopes()->where('user_id', $this->user->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(120000, (float) $order->grand_total);
        $this->assertEquals(150000, (float) $order->payment_amount);
        $this->assertEquals(30000, (float) $order->change_amount);
        $this->assertEquals('completed', $order->status);

        // 4. Verifikasi pemotongan stok & StockMovement
        $finalStockA = (float) InventoryStock::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        $this->assertEquals($initialStockA - 2, $finalStockA);

        $movement = StockMovement::where('product_id', $this->productA->id)
            ->where('reference_id', $order->id)
            ->where('type', 'sales')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(2, (float) $movement->quantity);

        // 5. Verifikasi akumulasi omzet pada shift kasir
        $shift->refresh();
        $this->assertEquals(120000, (float) $shift->cash_sales_total);
        $this->assertEquals(120000, (float) $shift->total_sales);
        $this->assertEquals(1, $shift->total_transactions);
    }

    public function test_pos_transaction_creates_balanced_general_ledger_journal_entries(): void
    {
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Walk-in Customer',
            'payment_method' => 'cash',
            'payment_amount' => 200000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 65000,
                    'discount_amount' => 0,
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 2,
                    'unit_price' => 30000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $order = PosOrder::withoutGlobalScopes()->where('user_id', $this->user->id)->latest('id')->first();
        $this->assertNotNull($order);
        // Total = 65.000 + 60.000 = 125.000
        $this->assertEquals(125000, (float) $order->grand_total);

        // Verifikasi Jurnal Terposting Seimbang (Total Debit == Total Kredit)
        $journals = JournalEntry::where('source_type', PosOrder::class)
            ->where('source_id', $order->id)
            ->get();

        $this->assertNotEmpty($journals);

        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');

        $this->assertEquals(round($totalDebit, 2), round($totalCredit, 2), 'Total Debit dan Total Kredit jurnal POS harus seimbang (balanced)!');

        // Pastikan ada ayat jurnal penerimaan kas
        $cashJournal = $journals->where('debit', 125000)->first();
        $this->assertNotNull($cashJournal, 'Harus ada jurnal penerimaan kas di sisi Debit senilai total tagihan.');
    }

    public function test_pos_receipt_view_renders_correctly(): void
    {
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Ibu Siti',
            'payment_method' => 'cash',
            'payment_amount' => 100000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 65000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $order = PosOrder::withoutGlobalScopes()->where('user_id', $this->user->id)->latest('id')->first();

        $response = $this->actingAs($this->user)->get(route('kasir.pos.receipt', $order));

        $response->assertStatus(200);
        $response->assertSee($order->order_number);
        $response->assertSee('Ibu Siti');
        $response->assertSee('DUTA TUNGGAL');
        $response->assertSee('Cetak Struk (Print)');
    }

    public function test_cashier_can_close_shift_and_generate_z_report_with_reconciliation(): void
    {
        // 1. Buka shift
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $shift = PosShift::withoutGlobalScopes()->where('user_id', $this->user->id)->where('status', 'open')->first();

        // 2. Buat transaksi tunai Rp 65.000
        $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Pelanggan Ritel',
            'payment_method' => 'cash',
            'payment_amount' => 70000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 65000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        // Uang seharusnya di laci = 500.000 + 65.000 = 565.000
        // Kasir menghitung fisik uang dan memasukkan 565.000 (cocok!)
        $response = $this->actingAs($this->user)->post(route('kasir.shift.close'), [
            'cash_actual' => 565000,
            'notes' => 'Tutup shift selesai kas cocok.',
        ]);

        $response->assertRedirect(route('kasir.shift.summary', $shift));

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals(565000, (float) $shift->cash_actual);
        $this->assertEquals(0, (float) $shift->cash_difference);

        // 3. Tampilan Z-Report
        $summaryResponse = $this->actingAs($this->user)->get(route('kasir.shift.summary', $shift));
        $summaryResponse->assertStatus(200);
        $summaryResponse->assertSee($shift->shift_number);
        $summaryResponse->assertSee('Fisik Kas Cocok (Seimbang)');
        $summaryResponse->assertSee('Rekonsiliasi Kas Laci Kasir');
    }

    public function test_pos_history_lists_past_orders(): void
    {
        $this->actingAs($this->user)->post(route('kasir.shift.open'), [
            'starting_cash' => 500000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->actingAs($this->user)->postJson(route('kasir.pos.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Bapak Joko',
            'payment_method' => 'cash',
            'payment_amount' => 100000,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 65000,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $order = PosOrder::withoutGlobalScopes()->where('user_id', $this->user->id)->latest('id')->first();

        $response = $this->actingAs($this->user)->get(route('kasir.pos.history', ['q' => $order->order_number]));

        $response->assertStatus(200);
        $response->assertSee($order->order_number);
        $response->assertSee('Bapak Joko');
    }
}
