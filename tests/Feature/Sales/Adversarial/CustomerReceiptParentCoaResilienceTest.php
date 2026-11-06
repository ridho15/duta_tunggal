<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\User;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang = Cabang::factory()->create(['nama' => 'Cabang Utama Bandung']);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $permissions = [
        'view any customer receipt',
        'view customer receipt',
        'create customer receipt',
        'view any sales invoice',
        'view sales invoice',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->roleKasir = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);
    $this->roleKasir->givePermissionTo($permissions);

    $this->user = User::factory()->create([
        'cabang_id' => $this->cabang->id,
        'manage_type' => [$this->cabang->id],
    ]);
    $this->user->assignRole($this->roleKasir);

    // Setup COA
    $this->aktivaLancar = ChartOfAccount::create([
        'code' => '1100',
        'name' => 'AKTIVA LANCAR',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->kasBesar = ChartOfAccount::create([
        'code' => '1111.01',
        'name' => 'KAS BESAR',
        'type' => 'Asset',
        'parent_id' => $this->aktivaLancar->id,
        'is_active' => true,
        'is_cash_bank' => true,
    ]);

    $this->pendapatan = ChartOfAccount::create([
        'code' => '4000',
        'name' => 'PENDAPATAN PENJUALAN',
        'type' => 'Revenue',
        'is_active' => true,
    ]);

    // Setup 1120 sebagai AKUN INDUK yang memiliki sub-akun anak
    $this->piutangInduk = ChartOfAccount::create([
        'code' => '1120',
        'name' => 'PIUTANG DAGANG (INDUK)',
        'type' => 'Asset',
        'parent_id' => $this->aktivaLancar->id,
        'is_active' => true,
    ]);

    $this->piutangAnakBandung = ChartOfAccount::create([
        'code' => '1120.01',
        'name' => 'Piutang Usaha Cabang Bandung',
        'type' => 'Asset',
        'parent_id' => $this->piutangInduk->id,
        'is_active' => true,
    ]);

    $this->piutangAnakJakarta = ChartOfAccount::create([
        'code' => '1120.02',
        'name' => 'Piutang Usaha Cabang Jakarta',
        'type' => 'Asset',
        'parent_id' => $this->piutangInduk->id,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang->id,
        'name' => 'PT CITRA TEKNIK MEDICA',
        'kredit_limit' => 50000000,
        'tipe_pembayaran' => 'Kredit',
        'tempo_kredit' => 30,
    ]);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'sell_price' => 74000,
        'is_active' => true,
    ]);
});

test('RES-01: postCustomerReceipt otomatis memakai sub-akun transaksi ketika 1120 merupakan akun induk', function () {
    $this->actingAs($this->user);

    // Verifikasi bahwa 1120 memang akun induk
    expect($this->piutangInduk->isParent())->toBeTrue()
        ->and($this->piutangInduk->isLeaf())->toBeFalse()
        ->and($this->piutangInduk->firstLeaf()?->id)->toBe($this->piutangAnakBandung->id);

    // Buat SO dan Invoice Rp 74.000 (sesuai foto layar pengguna)
    $so = \App\Models\SaleOrder::create([
        'so_number' => 'SO-TEST-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'status' => 'confirmed',
        'created_by' => $this->user->id,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20261106-0001',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $so->id,
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'subtotal' => 74000,
        'total' => 74000,
        'status' => Invoice::STATUS_SENT,
        'created_by' => $this->user->id,
    ]);

    $payload = [
        'customer_id' => $this->customer->id,
        'payment_date' => now()->format('Y-m-d'),
        'payment_method' => 'Cash',
        'coa_id' => $this->kasBesar->id,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [
            $invoice->id => 74000,
        ],
    ];

    // Eksekusi penyimpanan penerimaan customer
    $response = $this->post(route('penjualan.receipts.store'), $payload);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $receipt = CustomerReceipt::latest('id')->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('Paid')
        ->and((float) $receipt->total_payment)->toBe(74000.0);

    // Verifikasi entri jurnal akuntansi terbentuk dan seimbang
    $entries = JournalEntry::where('source_type', CustomerReceipt::class)
        ->where('source_id', $receipt->id)
        ->get();

    expect($entries)->toHaveCount(2);

    $debitEntry = $entries->firstWhere('debit', '>', 0);
    $creditEntry = $entries->firstWhere('credit', '>', 0);

    expect($debitEntry)->not->toBeNull()
        ->and($debitEntry->coa_id)->toBe($this->kasBesar->id)
        ->and((float) $debitEntry->debit)->toBe(74000.0);

    // Sisi kredit HARUS diposting ke sub-akun transaksi (1120.01), BUKAN ke akun induk 1120
    expect($creditEntry)->not->toBeNull()
        ->and($creditEntry->coa_id)->toBe($this->piutangAnakBandung->id)
        ->and((float) $creditEntry->credit)->toBe(74000.0)
        ->and($creditEntry->coa_id)->not->toBe($this->piutangInduk->id);
});

test('RES-02: Invoice dengan ar_coa_id spesifik menghasilkan kredit pelunasan simetris pada Customer Receipt', function () {
    $this->actingAs($this->user);

    $so2 = \App\Models\SaleOrder::create([
        'so_number' => 'SO-TEST-002',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'status' => 'confirmed',
        'created_by' => $this->user->id,
    ]);

    // Invoice dicatat dengan akun piutang Jakarta (1120.02)
    $invoice = Invoice::create([
        'invoice_number' => 'INV-20261106-0002',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $so2->id,
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'subtotal' => 150000,
        'total' => 150000,
        'ar_coa_id' => $this->piutangAnakJakarta->id,
        'status' => Invoice::STATUS_SENT,
        'created_by' => $this->user->id,
    ]);

    $payload = [
        'customer_id' => $this->customer->id,
        'payment_date' => now()->format('Y-m-d'),
        'payment_method' => 'Cash',
        'coa_id' => $this->kasBesar->id,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [
            $invoice->id => 150000,
        ],
    ];

    $response = $this->post(route('penjualan.receipts.store'), $payload);
    $response->assertSessionHasNoErrors();

    $receipt = CustomerReceipt::latest('id')->first();
    $creditEntry = JournalEntry::where('source_type', CustomerReceipt::class)
        ->where('source_id', $receipt->id)
        ->where('credit', '>', 0)
        ->first();

    // Verifikasi receipt mengkredit akun yang sama persis dengan yang ada di invoice (1120.02)
    expect($creditEntry)->not->toBeNull()
        ->and($creditEntry->coa_id)->toBe($this->piutangAnakJakarta->id);
});

test('RES-03: Migrasi perbaikan memindahkan akun 1121/1122 yang keliru menginduk ke 1120 kembali ke 1100', function () {
    // Simulasikan akun 1121 dan 1122 yang keliru menginduk ke 1120
    $piutangMarketplace = ChartOfAccount::create([
        'code' => '1121',
        'name' => 'PIUTANG MARKET PLACE',
        'type' => 'Asset',
        'parent_id' => $this->piutangInduk->id,
        'is_active' => true,
    ]);

    $piutangGiro = ChartOfAccount::create([
        'code' => '1122',
        'name' => 'PIUTANG GIRO MUNDUR',
        'type' => 'Asset',
        'parent_id' => $this->piutangInduk->id,
        'is_active' => true,
    ]);

    // Jalankan logika migrasi up()
    $migration = require database_path('migrations/2026_11_06_150000_repair_chart_of_accounts_parent_receivable.php');
    $migration->up();

    // Verifikasi bahwa 1121 dan 1122 sekarang menginduk ke 1100 (Aktiva Lancar), bukan 1120
    expect($piutangMarketplace->fresh()->parent_id)->toBe($this->aktivaLancar->id)
        ->and($piutangGiro->fresh()->parent_id)->toBe($this->aktivaLancar->id);
});
