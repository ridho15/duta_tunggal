<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountPayableResource\Pages\ListAccountPayables;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UATPhase2Batch3VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountSeeder::class);
    }

    // =========================================================================
    // ISU 7 – Account Payable: Judul & Format Tanggal
    // =========================================================================

    /** @test */
    public function test_issue_7_account_payable_list_title_is_clean_and_formatted(): void
    {
        $page = new ListAccountPayables();
        $title = (string) $page->getTitle();

        $this->assertEquals('Hutang Usaha (Account Payable)', $title,
            'Judul halaman AP harus "Hutang Usaha (Account Payable)", bukan angka mentah "Rp 0,00".');

        $this->assertStringNotContainsString('Rp', $title);
        $this->assertStringNotContainsString('0,00', $title);
    }

    /** @test */
    public function test_issue_7_account_payable_resource_has_date_columns(): void
    {
        // Verifikasi bahwa AccountPayableResource mendefinisikan kolom tanggal
        // menggunakan refleksi pada source code (tanpa instantiate Filament Livewire)
        $resourceFile = file_get_contents(
            app_path('Filament/Resources/AccountPayableResource.php')
        );

        $this->assertStringContainsString('invoice_date', $resourceFile,
            'AccountPayableResource.php harus memiliki kolom invoice_date');
        $this->assertStringContainsString('due_date', $resourceFile,
            'AccountPayableResource.php harus memiliki kolom due_date');
        $this->assertStringContainsString('formatStateUsing', $resourceFile,
            'Kolom tanggal harus menggunakan formatStateUsing() untuk format d/m/Y tanpa timestamp');
        $this->assertStringContainsString('format(\'d/m/Y\')', $resourceFile,
            'Format tanggal harus "d/m/Y" (misal: 18/09/2026)');
    }

    // =========================================================================
    // ISU 8 – Otomasi Status Overdue
    // =========================================================================

    /** @test */
    public function test_issue_8_invoice_is_overdue_helper_correctly_detects(): void
    {
        $cabang = Cabang::factory()->create();
        $currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_default' => true]
        );

        // Invoice lewat jatuh tempo, belum lunas
        $overdueInvoice = Invoice::withoutGlobalScopes()->create([
            'invoice_number' => 'INV-OD-BATCH3-1',
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
            'currency_id' => $currency->id,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => Carbon::now()->subDays(30),
            'due_date' => Carbon::now()->subDays(5),
            'subtotal' => 1000000,
            'total' => 1000000,
            'cabang_id' => $cabang->id,
            'delivery_orders' => [],
            'purchase_receipts' => [],
        ]);

        // Invoice masih aktif (jatuh tempo di masa depan)
        $activeInvoice = Invoice::withoutGlobalScopes()->create([
            'invoice_number' => 'INV-ACT-BATCH3-1',
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
            'currency_id' => $currency->id,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(10),
            'subtotal' => 500000,
            'total' => 500000,
            'cabang_id' => $cabang->id,
            'delivery_orders' => [],
            'purchase_receipts' => [],
        ]);

        $this->assertTrue($overdueInvoice->isOverdue(),
            'Invoice dengan due_date lampau dan saldo tersisa harus isOverdue() = true');
        $this->assertFalse($activeInvoice->isOverdue(),
            'Invoice dengan due_date masa depan harus isOverdue() = false');
    }

    /** @test */
    public function test_issue_8_check_overdue_command_updates_invoice_status(): void
    {
        $cabang = Cabang::factory()->create();
        $currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_default' => true]
        );

        $invoice = Invoice::withoutGlobalScopes()->create([
            'invoice_number' => 'INV-CMD-BATCH3-2',
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
            'currency_id' => $currency->id,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => Carbon::now()->subDays(45),
            'due_date' => Carbon::now()->subDays(10),
            'subtotal' => 750000,
            'total' => 750000,
            'cabang_id' => $cabang->id,
            'delivery_orders' => [],
            'purchase_receipts' => [],
        ]);

        $exitCode = Artisan::call('invoices:check-overdue');

        $this->assertEquals(0, $exitCode,
            'Command invoices:check-overdue harus exit dengan kode 0');

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_OVERDUE, $invoice->status,
            'Invoice yang telah lampau jatuh tempo harus diubah statusnya menjadi "overdue"');
    }

    /** @test */
    public function test_issue_8_check_overdue_command_is_registered(): void
    {
        // Verifikasi command terdaftar di Kernel
        $kernelFile = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('CheckOverdueInvoicesCommand::class', $kernelFile,
            'CheckOverdueInvoicesCommand harus terdaftar di $commands array di Kernel.php');

        // Verifikasi command dapat dijalankan
        $exitCode = Artisan::call('invoices:check-overdue', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode, 'Command invoices:check-overdue harus dapat dijalankan');
    }

    // =========================================================================
    // ISU 10 – Vendor Payment: Kolom, Nomor, Cabang, Jurnal
    // =========================================================================

    /** @test */
    public function test_issue_10_vendor_payments_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('vendor_payments', 'payment_number'),
            'Tabel vendor_payments harus memiliki kolom payment_number');
        $this->assertTrue(Schema::hasColumn('vendor_payments', 'cabang_id'),
            'Tabel vendor_payments harus memiliki kolom cabang_id');
        $this->assertTrue(Schema::hasColumn('vendor_payments', 'target_bank_account'),
            'Tabel vendor_payments harus memiliki kolom target_bank_account');
        $this->assertTrue(Schema::hasColumn('vendor_payments', 'transfer_reference_number'),
            'Tabel vendor_payments harus memiliki kolom transfer_reference_number');
    }

    /** @test */
    public function test_issue_10_vendor_payment_generates_payment_number_on_create(): void
    {
        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['perusahaan' => 'PT Baja Perkasa Test']);

        $payment = VendorPayment::create([
            'cabang_id' => $cabang->id,
            'supplier_id' => $supplier->id,
            'payment_date' => Carbon::now(),
            'total_payment' => 1500000,
            'total_payment_idr' => 1500000,
            'payment_method' => 'transfer',
            'target_bank_account' => 'BCA 123-456-7890 a.n PT Baja Perkasa',
            'transfer_reference_number' => 'TRF-BCA-987654',
            'selected_invoices' => [],
            'invoice_receipts' => [],
            'status' => 'Draft',
        ]);

        $this->assertNotEmpty($payment->payment_number,
            'Setiap VendorPayment baru harus memiliki payment_number yang di-generate otomatis');
        $this->assertMatchesRegularExpression('/^VP-\d{6}-\d{4}$/', $payment->payment_number,
            'Format payment_number harus VP-YYYYMM-XXXX (contoh: VP-202609-0001)');
        $this->assertEquals($cabang->id, $payment->cabang->id,
            'Relasi cabang harus berfungsi melalui cabang_id');

        // reference accessor harus mengembalikan payment_number
        $this->assertEquals($payment->payment_number, $payment->reference,
            'Accessor reference harus mengembalikan payment_number');
    }

    /** @test */
    public function test_issue_10_vendor_payment_resource_form_has_bank_info_fields(): void
    {
        // Verifikasi source code VendorPaymentResource memiliki field yang diperlukan
        $resourceFile = file_get_contents(
            app_path('Filament/Resources/VendorPaymentResource.php')
        );

        $this->assertStringContainsString('payment_number', $resourceFile,
            'VendorPaymentResource harus memiliki field payment_number');
        $this->assertStringContainsString('cabang_id', $resourceFile,
            'VendorPaymentResource harus memiliki field cabang_id');
        $this->assertStringContainsString('target_bank_account', $resourceFile,
            'VendorPaymentResource harus memiliki field target_bank_account (Rekening Bank Tujuan)');
        $this->assertStringContainsString('transfer_reference_number', $resourceFile,
            'VendorPaymentResource harus memiliki field transfer_reference_number (Nomor Bukti Transfer)');
    }

    /** @test */
    public function test_issue_10_ledger_posting_uses_payment_number_as_reference(): void
    {
        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['perusahaan' => 'PT Mitra Dagang']);

        $bankCoa = ChartOfAccount::where('code', config('coa.cash_and_bank', '1112.01'))->first();
        $apCoa = ChartOfAccount::where('code', config('coa.accounts_payable', '2110'))->first();

        if (! $bankCoa || ! $apCoa) {
            $this->markTestSkipped('COA Bank atau AP tidak tersedia di database test');
        }

        $payment = VendorPayment::create([
            'cabang_id' => $cabang->id,
            'supplier_id' => $supplier->id,
            'payment_date' => Carbon::now(),
            'total_payment' => 2500000,
            'total_payment_idr' => 2500000,
            'payment_method' => 'transfer',
            'coa_id' => $bankCoa->id,
            'target_bank_account' => 'Mandiri 1400012345 a.n PT Mitra Dagang',
            'transfer_reference_number' => 'MDR-889900',
            'selected_invoices' => [],
            'invoice_receipts' => [],
            'status' => 'Draft',
        ]);

        // Verifikasi format payment_number sudah benar sebelum posting
        $this->assertMatchesRegularExpression('/^VP-\d{6}-\d{4}$/', $payment->payment_number,
            'Payment number harus terbentuk sebelum posting jurnal');

        // Jalankan ledger posting
        $service = app(LedgerPostingService::class);
        $result = $service->postVendorPayment($payment);

        $this->assertEquals('posted', $result['status'],
            'postVendorPayment harus mengembalikan status "posted"');

        // Cek entri jurnal
        $entries = JournalEntry::where('source_type', VendorPayment::class)
            ->where('source_id', $payment->id)
            ->get();

        $this->assertNotEmpty($entries,
            'Harus ada setidaknya satu entri jurnal untuk VendorPayment yang diposting');

        // Semua entri harus menggunakan payment_number sebagai referensi
        foreach ($entries as $entry) {
            $this->assertEquals($payment->payment_number, $entry->reference,
                "Kolom reference pada jurnal harus berisi payment_number ({$payment->payment_number}), bukan format lama 'PAY-1'");
        }

        // Entri AP (debit) harus memiliki deskripsi profesional
        $apEntry = $entries->firstWhere('debit', '>', 0);
        if ($apEntry) {
            $this->assertStringContainsString('Pembayaran Hutang:', $apEntry->description,
                'Deskripsi jurnal harus diawali dengan "Pembayaran Hutang:"');
            $this->assertStringContainsString('PT Mitra Dagang', $apEntry->description,
                'Deskripsi jurnal harus memuat nama supplier');
            $this->assertStringContainsString($payment->payment_number, $apEntry->description,
                'Deskripsi jurnal harus memuat payment_number');
            $this->assertStringContainsString('MDR-889900', $apEntry->description,
                'Deskripsi jurnal harus memuat nomor referensi transfer bank');
        }

        // Jurnal harus dikaitkan ke cabang yang benar
        $anyEntry = $entries->first();
        $this->assertEquals($cabang->id, $anyEntry->cabang_id,
            'Semua entri jurnal harus dikaitkan ke cabang dari VendorPayment');
    }

    // =========================================================================
    // ISU 15 – COA Persediaan Barang Dagangan
    // =========================================================================

    /** @test */
    public function test_issue_15_default_inventory_coa_is_now_barang_dagangan(): void
    {
        $this->assertEquals('1140.10', config('coa.inventory'),
            'config("coa.inventory") harus 1140.10 (Persediaan Barang Dagangan), bukan 1140.01 (Bahan Baku)');

        $standardPriority = config('coa.product.inventory_coa_id.standard');
        $this->assertEquals('1140.10', $standardPriority[0],
            'Prioritas pertama inventory_coa_id.standard harus 1140.10');
    }

    /** @test */
    public function test_issue_15_new_standard_product_gets_1140_10_inventory_coa(): void
    {
        // Buat produk baru tanpa flag manufacture/raw_material → produk standar
        $product = Product::factory()->create([
            'inventory_coa_id' => null,
            'is_manufacture' => false,
            'is_raw_material' => false,
        ]);

        $this->assertNotNull($product->inventory_coa_id,
            'Produk standar yang baru dibuat harus memiliki inventory_coa_id yang terisi otomatis oleh Observer');
        $this->assertEquals('1140.10', $product->inventoryCoa?->code,
            'Produk standar (barang dagangan) harus otomatis dipetakan ke 1140.10, bukan 1140.01');
    }

    /** @test */
    public function test_issue_15_sync_command_remaps_standard_products_from_1140_01_to_1140_10(): void
    {
        $rawCoa = ChartOfAccount::where('code', '1140.01')->first();
        $dagangCoa = ChartOfAccount::where('code', '1140.10')->first();
        $prodCoa = ChartOfAccount::where('code', '1140.02')->first();

        if (! $rawCoa || ! $dagangCoa) {
            $this->markTestSkipped('COA 1140.01 atau 1140.10 tidak tersedia di database test');
        }

        // Produk standar yang sebelumnya salah dikaitkan ke 1140.01 (Bahan Baku)
        $standardProduct = Product::withoutEvents(function () use ($rawCoa) {
            return Product::factory()->create([
                'is_raw_material' => false,
                'is_manufacture' => false,
                'inventory_coa_id' => $rawCoa->id,
            ]);
        });

        // Produk produksi yang salah dikaitkan ke 1140.01
        $manufactureProduct = Product::withoutEvents(function () use ($rawCoa) {
            return Product::factory()->create([
                'is_raw_material' => false,
                'is_manufacture' => true,
                'inventory_coa_id' => $rawCoa->id,
            ]);
        });

        // Produk bahan baku yang sudah benar di 1140.01 → tidak boleh diubah
        $rawProduct = Product::withoutEvents(function () use ($rawCoa) {
            return Product::factory()->create([
                'is_raw_material' => true,
                'is_manufacture' => false,
                'inventory_coa_id' => $rawCoa->id,
            ]);
        });

        // Jalankan command sinkronisasi
        $exitCode = Artisan::call('products:sync-inventory-coa');
        $this->assertEquals(0, $exitCode, 'Command products:sync-inventory-coa harus exit dengan kode 0');

        $standardProduct->refresh();
        $manufactureProduct->refresh();
        $rawProduct->refresh();

        $this->assertEquals($dagangCoa->id, $standardProduct->inventory_coa_id,
            'Produk standar (non-bahan-baku) harus dipindah dari 1140.01 ke 1140.10 (Barang Dagangan)');

        if ($prodCoa) {
            $this->assertEquals($prodCoa->id, $manufactureProduct->inventory_coa_id,
                'Produk produksi (is_manufacture) harus dipindah dari 1140.01 ke 1140.02 (Barang Produksi)');
        }

        $this->assertEquals($rawCoa->id, $rawProduct->inventory_coa_id,
            'Produk bahan baku (is_raw_material) harus tetap di 1140.01 dan tidak diubah');
    }

    /** @test */
    public function test_issue_15_sync_command_dry_run_does_not_modify_database(): void
    {
        $rawCoa = ChartOfAccount::where('code', '1140.01')->first();

        if (! $rawCoa) {
            $this->markTestSkipped('COA 1140.01 tidak tersedia di database test');
        }

        $product = Product::withoutEvents(function () use ($rawCoa) {
            return Product::factory()->create([
                'is_raw_material' => false,
                'is_manufacture' => false,
                'inventory_coa_id' => $rawCoa->id,
            ]);
        });

        // Jalankan dengan --dry-run
        $exitCode = Artisan::call('products:sync-inventory-coa', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        $product->refresh();
        $this->assertEquals($rawCoa->id, $product->inventory_coa_id,
            '--dry-run tidak boleh mengubah data di database, inventory_coa_id harus tetap sama');
    }
}
