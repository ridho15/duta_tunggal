<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LedgerPostingService;
use App\Traits\JournalValidationTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sprint10UATPostAuditPriorityFixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-TEST-SP10'],
            ['nama' => 'Cabang SP10 Test', 'alamat' => 'Jl. SP10', 'status' => 1]
        );

        $role = Role::findOrCreate('Super Admin', 'web');

        $this->user = User::factory()->create([
            'username' => 'sp10_admin_' . uniqid(),
            'email' => 'sp10_' . uniqid() . '@example.com',
            'kode_user' => 'SP10' . strtoupper(substr(uniqid(), -3)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    public function test_journal_validation_trait_blocks_parent_coa_with_children(): void
    {
        $tester = new class {
            use JournalValidationTrait;

            public function testValidate(array $entries): void
            {
                $this->validateJournalEntries($entries);
            }
        };

        $parentCoa = ChartOfAccount::create([
            'code' => 'TEST-PARENT-100',
            'name' => 'Akun Induk Uji',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $childCoa = ChartOfAccount::create([
            'code' => 'TEST-CHILD-101',
            'name' => 'Sub Akun Riil Uji',
            'type' => 'Asset',
            'parent_id' => $parentCoa->id,
            'is_active' => true,
        ]);

        $balancingCoa = ChartOfAccount::create([
            'code' => 'TEST-BAL-102',
            'name' => 'Akun Penyeimbang',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        // Attempt to validate journal with parent COA -> must throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("merupakan akun induk dan tidak dapat digunakan untuk transaksi jurnal");

        $tester->testValidate([
            ['coa_id' => $parentCoa->id, 'debit' => 100000, 'credit' => 0],
            ['coa_id' => $balancingCoa->id, 'debit' => 0, 'credit' => 100000],
        ]);
    }

    public function test_journal_validation_trait_allows_leaf_coa(): void
    {
        $tester = new class {
            use JournalValidationTrait;

            public function testValidate(array $entries): void
            {
                $this->validateJournalEntries($entries);
            }
        };

        $parentCoa = ChartOfAccount::create([
            'code' => 'TEST-PARENT-200',
            'name' => 'Akun Induk Uji 2',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $leafCoa1 = ChartOfAccount::create([
            'code' => 'TEST-CHILD-201',
            'name' => 'Sub Akun Riil Uji 1',
            'type' => 'Asset',
            'parent_id' => $parentCoa->id,
            'is_active' => true,
        ]);

        $leafCoa2 = ChartOfAccount::create([
            'code' => 'TEST-CHILD-202',
            'name' => 'Sub Akun Riil Uji 2',
            'type' => 'Asset',
            'parent_id' => $parentCoa->id,
            'is_active' => true,
        ]);

        // Leaf COAs must pass validation smoothly
        $tester->testValidate([
            ['coa_id' => $leafCoa1->id, 'debit' => 50000, 'credit' => 0],
            ['coa_id' => $leafCoa2->id, 'debit' => 0, 'credit' => 50000],
        ]);

        $this->assertTrue(true);
    }

    public function test_reconcile_command_reclassifies_parent_coa_to_child(): void
    {
        $parentCoa = ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Piutang Dagang Induk',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $childCoa = ChartOfAccount::create([
            'code' => '1120.01',
            'name' => 'Piutang Usaha Cabang',
            'type' => 'Asset',
            'parent_id' => $parentCoa->id,
            'is_active' => true,
        ]);

        $entry = JournalEntry::create([
            'coa_id' => $parentCoa->id,
            'reference' => 'TEST-LEGACY-01',
            'date' => now()->toDateString(),
            'debit' => 150000,
            'credit' => 0,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertEquals($parentCoa->id, $entry->coa_id);

        Artisan::call('system:reconcile-data', [
            '--task' => 'gl-legacy',
            '--force' => true,
        ]);

        $entry->refresh();
        $this->assertEquals($childCoa->id, $entry->coa_id);
    }

    public function test_reconcile_command_neutralizes_negative_transit_balance(): void
    {
        $transitCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Barang Terkirim', 'type' => 'Asset', 'is_active' => true]
        );

        $cogsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '5100.10'],
            ['name' => 'HPP / COGS', 'type' => 'Expense', 'is_active' => true]
        );

        // Simulate negative balance on transit account
        JournalEntry::create([
            'coa_id' => $transitCoa->id,
            'reference' => 'TEST-NEG-TRANSIT',
            'date' => now()->toDateString(),
            'debit' => 0,
            'credit' => 2000000,
            'cabang_id' => $this->cabang->id,
        ]);

        $balanceBefore = (float) DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->where('coa_id', $transitCoa->id)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->value('balance');

        $this->assertEquals(-2000000.0, $balanceBefore);

        Artisan::call('system:reconcile-data', [
            '--task' => 'gl-legacy',
            '--force' => true,
        ]);

        $balanceAfter = (float) DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->where('coa_id', $transitCoa->id)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->value('balance');

        $this->assertEquals(0.0, $balanceAfter);
    }

    public function test_purchase_order_invoice_pdf_template_uses_status_labels(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'PINV-TEST-PDF-01',
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now(),
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
        ]);

        $view = view('pdf.purchase-order-invoice-2', [
            'invoice' => $invoice,
            'company' => (object)[
                'name' => 'Duta Tunggal',
                'address' => 'Jl. Industri',
                'phone' => '021-123',
                'email' => 'info@dutabunggal.com',
                'logo_url' => '',
            ],
            'items' => collect([]),
            'payments' => collect([]),
            'qrCode' => null,
            'barcode' => null,
        ])->render();

        $this->assertStringContainsString('Menunggu Pembayaran', $view);
        $this->assertStringNotContainsString('<td>Sent</td>', $view);
    }
}
