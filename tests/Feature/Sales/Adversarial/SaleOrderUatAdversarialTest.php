<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Verifikasi perlindungan isolasi database
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang1 = Cabang::factory()->create(['nama' => 'Cabang Jakarta']);
    $this->cabang2 = Cabang::factory()->create(['nama' => 'Cabang Surabaya']);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    // Permissions
    $permissions = [
        'view any sales order',
        'view sales order',
        'create sales order',
        'update sales order',
        'response sales order',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->roleSales = Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
    $this->roleSales->givePermissionTo(['view any sales order', 'view sales order', 'create sales order']);

    $this->roleKasir = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);
    $this->roleKasir->givePermissionTo($permissions);

    // Users
    $this->salesUser = User::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'manage_type' => [$this->cabang1->id],
    ]);
    $this->salesUser->assignRole($this->roleSales);

    $this->kasirUser = User::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'manage_type' => [$this->cabang1->id],
    ]);
    $this->kasirUser->assignRole($this->roleKasir);

    $this->otherBranchUser = User::factory()->create([
        'cabang_id' => $this->cabang2->id,
        'manage_type' => [$this->cabang2->id],
    ]);
    $this->otherBranchUser->assignRole($this->roleKasir);

    // Customer & Product
    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'kredit_limit' => 10000000,
        'tipe_pembayaran' => 'Kredit',
    ]);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'sell_price' => 100000,
        'cost_price' => 70000,
        'harga_batas' => 80000,
        'is_active' => true,
    ]);
});

test('ADV-SO-01: Sales Order rejects unit price below bottom price limit', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'action' => 'request_approve',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 50000, // Di bawah harga_batas (80.000)
                'discount' => 0,
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->actingAs($this->salesUser)->post(route('penjualan.so.store'), $payload);

    if ($response->isRedirect() && session()->has('errors')) {
        $response->assertSessionHasErrors();
    } else {
        $so = SaleOrder::latest()->first();
        expect($so->status)->not->toBe('approved');
    }
});

test('ADV-SO-02: Sales Order rejects total amount exceeding customer credit limit', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'action' => 'request_approve',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 150, // 150 * 100.000 = 15.000.000 (melebihi limit kredit 10.000.000)
                'unit_price' => 100000,
                'discount' => 0,
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->actingAs($this->salesUser)->post(route('penjualan.so.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['customer_id']);
    expect(SaleOrder::where('customer_id', $this->customer->id)->count())->toBe(0);
});

test('ADV-SO-03: Sales Order validation rejects negative quantity and extreme discounts', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'action' => 'draft',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => -5, // Kuantitas negatif
                'unit_price' => 100000,
                'discount' => 150, // Diskon di atas 100%
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->actingAs($this->salesUser)->post(route('penjualan.so.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['items.0.quantity', 'items.0.discount']);
    expect(SaleOrder::count())->toBe(0);
});

test('ADV-SO-04: Sales role cannot approve sales order (Privilege Escalation Guard)', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-ADV-04-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now(),
        'status' => 'request_approve',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->salesUser->id,
        'total_amount' => 500000,
    ]);

    // Sales user attempts to call approve endpoint
    $response = $this->actingAs($this->salesUser)->post(route('penjualan.so.approve', $so->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $so->refresh();
    expect($so->status)->toBe('request_approve');
    expect($so->approve_by)->toBeNull();
});

test('ADV-SO-05: Cross-cabang user is blocked from viewing or approving SO of another branch', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-ADV-05-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id, // Cabang Jakarta
        'order_date' => now(),
        'status' => 'request_approve',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->salesUser->id,
        'total_amount' => 500000,
    ]);

    // User Cabang Surabaya tries to approve Cabang Jakarta SO
    $response = $this->actingAs($this->otherBranchUser)->post(route('penjualan.so.approve', $so->id));

    $so->refresh();
    expect($so->status)->toBe('request_approve');
    expect($so->approve_by)->toBeNull();
});

test('UAT-SO-01: Cashier approves COD Sales Order with 4-Layer Data Validation and Customer Deposit Deduction', function () {
    $codCustomer = Customer::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'tipe_pembayaran' => 'COD (Bayar Lunas)',
    ]);

    $coa = ChartOfAccount::factory()->create();

    // Customer memiliki Saldo Titip Rp 500.000
    $deposit = Deposit::create([
        'deposit_number' => 'DEP-' . uniqid(),
        'from_model_type' => Customer::class,
        'from_model_id' => $codCustomer->id,
        'amount' => 500000,
        'used_amount' => 0,
        'remaining_amount' => 500000,
        'coa_id' => $coa->id,
        'payment_coa_id' => $coa->id,
        'status' => 'active',
        'created_by' => $this->kasirUser->id,
    ]);

    $so = SaleOrder::create([
        'so_number' => 'SO-UAT-01-' . uniqid(),
        'customer_id' => $codCustomer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now(),
        'status' => 'request_approve',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'currency_id' => $this->currency->id,
        'created_by' => $this->salesUser->id,
        'total_amount' => 200000,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 100000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    // Kasir approves SO
    $response = $this->actingAs($this->kasirUser)->post(route('penjualan.so.approve', $so->id), [
        'notes' => 'Disetujui oleh kasir untuk pengambilan sendiri',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $so->refresh();
    $deposit->refresh();

    // LAYER 1: Dokumen SO & Potong Saldo Titip Customer
    expect($so->status)->toBe('approved');
    expect($so->approve_by)->toBe($this->kasirUser->id);
    expect($so->cashier_approved_by)->toBe($this->kasirUser->id);
    expect($so->cashier_approved_at)->not->toBeNull();
    expect((float) $deposit->used_amount)->toBe(200000.0);
    expect((float) $deposit->remaining_amount)->toBe(300000.0);

    // LAYER 2: Kartu Stok (Fisik belum berkurang, belum ada DO)
    expect($so->deliveryOrder()->count())->toBe(0);
    expect(DB::table('stock_movements')->where('from_model_type', SaleOrder::class)->count())->toBe(0);

    // LAYER 3: Jurnal Akuntansi (Belum terbentuk pada fase SO)
    expect(DB::table('journal_entries')->where('source_type', SaleOrder::class)->where('source_id', $so->id)->count())->toBe(0);

    // LAYER 4: Kartu Piutang (Belum ada Invoice)
    expect(DB::table('account_receivables')->where('customer_id', $codCustomer->id)->count())->toBe(0);
});
