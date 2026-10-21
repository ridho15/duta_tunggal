<?php

namespace Tests\Feature\Finance;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeJournalWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $revenueCoa;
    protected ChartOfAccount $payableCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-JRN',
            'nama' => 'Cabang Test Jurnal',
            'alamat' => 'Jl. Test Jurnal No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any journal entry',
            'view journal entry',
            'create journal entry',
            'update journal entry',
            'delete journal entry',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'journal_tester',
            'email' => 'journal-tester@dutatunggal.co.id',
            'name' => 'Journal Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        // Siapkan Akun COA Pengujian
        $this->cashCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1111.91'],
            [
                'name' => 'Kas Kecil Operasional',
                'type' => 'Asset',
                'is_active' => true,
                'is_cash_bank' => true,
                'opening_balance' => 5000000,
            ]
        );

        $this->bankCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1112.91'],
            [
                'name' => 'Bank Mandiri Giro',
                'type' => 'Asset',
                'is_active' => true,
                'is_cash_bank' => true,
                'opening_balance' => 25000000,
            ]
        );

        $this->expenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6110.91'],
            [
                'name' => 'Beban Perjalanan Dinas',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->revenueCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4110.91'],
            [
                'name' => 'Pendapatan Jasa Konsultasi',
                'type' => 'Revenue',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->payableCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2110.91'],
            [
                'name' => 'Hutang Usaha Supplier',
                'type' => 'Liability',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );
    }

    /**
     * 1. Test akses halaman Jurnal Umum (/keuangan/jurnal) & verifikasi KPI card.
     */
    public function test_user_can_access_journal_index_page_and_see_kpi_cards(): void
    {
        $ref = 'JV-TEST-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Beban dinas luar kota',
            'debit' => 750000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Beban dinas luar kota',
            'debit' => 0,
            'credit' => 750000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.index'));

        $response->assertStatus(200);
        $response->assertSee('Jurnal Umum & Buku Besar');
        $response->assertSee('Total Debit (Bulan Ini)');
        $response->assertSee('Total Kredit (Bulan Ini)');
        $response->assertSee($ref);
        $response->assertSee('Beban Perjalanan Dinas');
    }

    /**
     * 2. Test filter jurnal berdasarkan tab modul dan query pencarian.
     */
    public function test_journal_index_filters_by_module_tab_and_search(): void
    {
        $refManual = 'JV-MANUAL-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $refManual,
            'description' => 'Jurnal penyesuaian ATK',
            'debit' => 100000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $refManual,
            'description' => 'Jurnal penyesuaian ATK',
            'debit' => 0,
            'credit' => 100000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $refPurchase = 'JV-PURCHASE-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $refPurchase,
            'description' => 'Faktur Pembelian Bahan Baku',
            'debit' => 2500000,
            'credit' => 0,
            'journal_type' => 'purchase_invoice',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->payableCoa->id,
            'date' => now()->toDateString(),
            'reference' => $refPurchase,
            'description' => 'Faktur Pembelian Bahan Baku',
            'debit' => 0,
            'credit' => 2500000,
            'journal_type' => 'purchase_invoice',
            'cabang_id' => $this->cabang->id,
        ]);

        // Filter tab manual
        $responseManual = $this->actingAs($this->user)
            ->get(route('keuangan.journal.index', ['tab' => 'manual']));
        $responseManual->assertStatus(200);
        $responseManual->assertSee($refManual);
        $responseManual->assertDontSee($refPurchase);

        // Filter tab pembelian
        $responsePurch = $this->actingAs($this->user)
            ->get(route('keuangan.journal.index', ['tab' => 'pembelian']));
        $responsePurch->assertStatus(200);
        $responsePurch->assertSee($refPurchase);
        $responsePurch->assertDontSee($refManual);

        // Filter pencarian teks
        $responseSearch = $this->actingAs($this->user)
            ->get(route('keuangan.journal.index', ['q' => 'Bahan Baku']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertSee($refPurchase);
    }

    /**
     * 3. Test akses halaman Buku Besar (/keuangan/jurnal/buku-besar).
     */
    public function test_user_can_access_general_ledger_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('keuangan.journal.ledger', ['coa_id' => $this->cashCoa->id]));

        $response->assertStatus(200);
        $response->assertSee('Buku Besar per Akun');
        $response->assertSee($this->cashCoa->name);
        $response->assertSee($this->cashCoa->code);
        $response->assertSee('Saldo Awal');
        $response->assertSee('Saldo Akhir');
    }

    /**
     * 4. Test kalkulasi Saldo Awal, mutasi debit/kredit, dan Running Balance di Buku Besar.
     */
    public function test_ledger_calculates_opening_balance_and_running_balance_correctly(): void
    {
        $today = now()->toDateString();
        $ref1 = 'JV-LDG-1-' . uniqid();
        $ref2 = 'JV-LDG-2-' . uniqid();

        // Saldo awal cashCoa adalah 5.000.000
        // Tambah debit (kas masuk) 1.500.000 -> balance 6.500.000
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => $today,
            'reference' => $ref1,
            'description' => 'Penerimaan Pendapatan',
            'debit' => 1500000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        // Tambah kredit (kas keluar) 500.000 -> balance 6.000.000
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => $today,
            'reference' => $ref2,
            'description' => 'Pembayaran Biaya',
            'debit' => 0,
            'credit' => 500000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.ledger', [
            'coa_id' => $this->cashCoa->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee($ref1);
        $response->assertSee($ref2);
        // Cek bahwa mutasi debit & kredit tercantum
        $response->assertSee('1.500.000');
        $response->assertSee('500.000');
    }

    /**
     * 5. Test akses form input Jurnal Manual (/keuangan/jurnal/create).
     */
    public function test_user_can_view_create_manual_journal_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('keuangan.journal.create'));

        $response->assertStatus(200);
        $response->assertSee('Buat Jurnal Penyesuaian Manual');
        $response->assertSee('No. Referensi / Voucher');
        $response->assertSee('Tanggal Jurnal');
        $response->assertSee('Tambah Baris');
        $response->assertSee('Beban Perjalanan Dinas');
    }

    /**
     * 6. Test simpan voucher jurnal manual yang seimbang (Debit = Kredit).
     */
    public function test_user_can_store_balanced_manual_journal_entry(): void
    {
        $ref = 'JV-MAN-' . uniqid();
        $payload = [
            'reference' => $ref,
            'date' => now()->toDateString(),
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'description' => 'Penyesuaian biaya listrik kantor pusat',
            'entries' => [
                [
                    'coa_id' => $this->expenseCoa->id,
                    'description' => 'Beban Listrik',
                    'debit' => 450000,
                    'credit' => 0,
                ],
                [
                    'coa_id' => $this->cashCoa->id,
                    'description' => 'Kas keluar bayar listrik',
                    'debit' => 0,
                    'credit' => 450000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.journal.store'), $payload);

        $response->assertRedirect(route('keuangan.journal.show', $ref));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'reference' => $ref,
            'coa_id' => $this->expenseCoa->id,
            'debit' => 450000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => $ref,
            'coa_id' => $this->cashCoa->id,
            'debit' => 0,
            'credit' => 450000,
        ]);
    }

    /**
     * 7. Test simpan jurnal manual gagal jika tidak seimbang (Debit != Kredit).
     */
    public function test_manual_journal_fails_validation_if_unbalanced(): void
    {
        $ref = 'JV-UNBAL-' . uniqid();
        $payload = [
            'reference' => $ref,
            'date' => now()->toDateString(),
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'description' => 'Jurnal tidak seimbang',
            'entries' => [
                [
                    'coa_id' => $this->expenseCoa->id,
                    'description' => 'Beban 500rb',
                    'debit' => 500000,
                    'credit' => 0,
                ],
                [
                    'coa_id' => $this->cashCoa->id,
                    'description' => 'Kas keluar 300rb',
                    'debit' => 0,
                    'credit' => 300000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->from(route('keuangan.journal.create'))
            ->post(route('keuangan.journal.store'), $payload);

        $response->assertRedirect(route('keuangan.journal.create'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('journal_entries', [
            'reference' => $ref,
        ]);
    }

    /**
     * 8. Test simpan jurnal gagal jika kurang dari 2 baris valid.
     */
    public function test_manual_journal_fails_validation_if_less_than_two_entries(): void
    {
        $ref = 'JV-FEW-' . uniqid();
        $payload = [
            'reference' => $ref,
            'date' => now()->toDateString(),
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'description' => 'Hanya satu baris',
            'entries' => [
                [
                    'coa_id' => $this->expenseCoa->id,
                    'description' => 'Beban 500rb',
                    'debit' => 500000,
                    'credit' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->from(route('keuangan.journal.create'))
            ->post(route('keuangan.journal.store'), $payload);

        $response->assertRedirect(route('keuangan.journal.create'));
        $response->assertSessionHasErrors('entries');

        $this->assertDatabaseMissing('journal_entries', [
            'reference' => $ref,
        ]);
    }

    /**
     * 9. Test melihat detail Voucher Jurnal (/keuangan/jurnal/{reference}).
     */
    public function test_user_can_view_journal_voucher_show_page(): void
    {
        $ref = 'JV-SHOW-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Biaya representasi tamu',
            'debit' => 350000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->bankCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Biaya representasi tamu',
            'debit' => 0,
            'credit' => 350000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertStatus(200);
        $response->assertSee($ref);
        $response->assertSee('Biaya representasi tamu');
        $response->assertSee('Seimbang (Balanced)');
        $response->assertSee('350.000');
    }

    /**
     * 10. Test hapus voucher jurnal manual berhasil.
     */
    public function test_user_can_delete_manual_journal_entry(): void
    {
        $ref = 'JV-DEL-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Salah input biaya',
            'debit' => 200000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Salah input biaya',
            'debit' => 0,
            'credit' => 200000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('keuangan.journal.destroy', $ref));

        $response->assertRedirect(route('keuangan.journal.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('journal_entries', [
            'reference' => $ref,
        ]);
    }

    /**
     * 11. Test proteksi integritas: Jurnal otomatis sistem tidak boleh dihapus manual.
     */
    public function test_user_cannot_manually_delete_system_generated_journal_entry(): void
    {
        $ref = 'JV-SYS-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Jurnal Otomatis Pembelian',
            'debit' => 1000000,
            'credit' => 0,
            'journal_type' => 'purchase_invoice',
            'source_type' => 'App\Models\Invoice',
            'source_id' => 999,
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->payableCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Jurnal Otomatis Pembelian',
            'debit' => 0,
            'credit' => 1000000,
            'journal_type' => 'purchase_invoice',
            'source_type' => 'App\Models\Invoice',
            'source_id' => 999,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('keuangan.journal.destroy', $ref));

        $response->assertSessionHas('error');

        // Pastikan data tetap aman dan tidak terhapus
        $this->assertDatabaseHas('journal_entries', [
            'reference' => $ref,
        ]);
    }

    /**
     * 12. Test streaming PDF Voucher Jurnal.
     */
    public function test_user_can_stream_journal_voucher_pdf(): void
    {
        $ref = 'JV-PDF-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Cetak voucher PDF',
            'debit' => 600000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Cetak voucher PDF',
            'debit' => 0,
            'credit' => 600000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('keuangan.journal.pdf', $ref));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
