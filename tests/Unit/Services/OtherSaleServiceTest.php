<?php

namespace Tests\Unit\Services;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\OtherSale;
use App\Models\User;
use App\Services\OtherSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OtherSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_configured_accounts_receivable_coa_when_cash_bank_is_absent(): void
    {
        Config::set('coa.accounts_receivable', '1120.10');

        $receivableCoa = ChartOfAccount::factory()->create([
            'code' => '1120.10',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $revenueCoa = ChartOfAccount::factory()->create([
            'code' => '7200.01',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $cabang = Cabang::factory()->create();
        $user = User::factory()->create(['cabang_id' => $cabang->id]);

        $otherSale = OtherSale::create([
            'reference_number' => 'TEST-OS-001',
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Configured AR other sale',
            'amount' => 500000,
            'coa_id' => $revenueCoa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $cabang->id,
            'status' => 'draft',
            'notes' => null,
            'created_by' => $user->id,
        ]);

        app(OtherSaleService::class)->postJournalEntries($otherSale);

        $debitEntry = JournalEntry::query()
            ->where('source_type', OtherSale::class)
            ->where('source_id', $otherSale->id)
            ->where('debit', '>', 0)
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertSame($receivableCoa->id, $debitEntry->coa_id);
        $this->assertSame('500000.00', $debitEntry->debit);
    }
}