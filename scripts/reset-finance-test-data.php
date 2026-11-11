<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

echo "=== [RESET FINANCE TEST DATA (SIKLUS E)] ===\n";

DB::transaction(function () {
    // 1. Delete Test CashBank Transactions
    $testTransactions = CashBankTransaction::withTrashed()->where('number', 'like', 'CB-E-%')->get();
    foreach ($testTransactions as $t) {
        $t->journalEntries()->forceDelete();
        $t->transactionDetails()->delete();
        $t->forceDelete();
    }
    echo "Dihapus: " . $testTransactions->count() . " CashBankTransaction (CB-E-%)\n";

    // 2. Delete Test CashBank Transfers
    $testTransfers = CashBankTransfer::withTrashed()->where('number', 'like', 'TRF-E-%')->get();
    foreach ($testTransfers as $t) {
        $t->journalEntries()->forceDelete();
        $t->forceDelete();
    }
    echo "Dihapus: " . $testTransfers->count() . " CashBankTransfer (TRF-E-%)\n";

    // 3. Delete Test Manual Journals
    $testJournals = JournalEntry::withTrashed()->where('reference', 'like', 'JV-E-%')->forceDelete();
    echo "Dihapus: {$testJournals} JournalEntry (JV-E-%)\n";

    // 4. Delete Closing Journals
    $closingJournals = JournalEntry::withTrashed()->where('journal_type', 'closing')->orWhere('reference', 'like', 'CLOSE-%')->forceDelete();
    echo "Dihapus: {$closingJournals} Closing JournalEntry\n";

    // 5. Delete Test Assets
    $testAssets = Asset::withTrashed()->where('code', 'like', 'AST-E-%')->get();
    foreach ($testAssets as $a) {
        AssetDepreciation::where('asset_id', $a->id)->delete();
        JournalEntry::withTrashed()->where('source_type', Asset::class)->where('source_id', $a->id)->forceDelete();
        JournalEntry::withTrashed()->where('reference', 'like', 'AST-' . $a->code . '%')->forceDelete();
        $a->forceDelete();
    }
    echo "Dihapus: " . $testAssets->count() . " Asset (AST-E-%)\n";

    // 6. Delete Test Accounting Periods
    $testPeriods = AccountingPeriod::whereYear('period_start', 2026)->delete();
    echo "Dihapus: {$testPeriods} AccountingPeriod (2026)\n";

    // 7. Ensure CashBank Accounts Exist
    $kasCoa = ChartOfAccount::where('code', '1111.01')->first() ?? ChartOfAccount::where('code', '1111')->first();
    $kasKecilCoa = ChartOfAccount::where('code', '1111.02')->first();
    $bcaCoa = ChartOfAccount::where('code', '1112.01')->first() ?? ChartOfAccount::where('code', '1112')->first();
    $mandiriCoa = ChartOfAccount::where('code', '1112.02')->first();

    if ($kasCoa && !CashBankAccount::where('coa_id', $kasCoa->id)->exists()) {
        CashBankAccount::create([
            'name' => 'Kas Operasional Kantor',
            'bank_name' => 'KAS TUNAI',
            'account_number' => 'KAS-01',
            'coa_id' => $kasCoa->id,
            'notes' => 'Kas tunai operasional kantor pusat',
        ]);
    }

    if ($kasKecilCoa && !CashBankAccount::where('coa_id', $kasKecilCoa->id)->exists()) {
        CashBankAccount::create([
            'name' => 'Kas Kecil Operasional',
            'bank_name' => 'KAS TUNAI',
            'account_number' => 'PETTY-01',
            'coa_id' => $kasKecilCoa->id,
            'notes' => 'Kas kecil pengeluaran rutin',
        ]);
    }

    if ($bcaCoa && !CashBankAccount::where('coa_id', $bcaCoa->id)->exists()) {
        CashBankAccount::create([
            'name' => 'Bank BCA Operasional',
            'bank_name' => 'Bank Central Asia',
            'account_number' => '800-123-4567',
            'coa_id' => $bcaCoa->id,
            'notes' => 'Rekening utama giro BCA',
        ]);
    }

    if ($mandiriCoa && !CashBankAccount::where('coa_id', $mandiriCoa->id)->exists()) {
        CashBankAccount::create([
            'name' => 'Bank Mandiri Giro',
            'bank_name' => 'Bank Mandiri',
            'account_number' => '123-00-9876543-1',
            'coa_id' => $mandiriCoa->id,
            'notes' => 'Rekening cadangan Mandiri',
        ]);
    }

    echo "Status CashBankAccount: " . CashBankAccount::count() . " akun terdaftar.\n";
});

echo "=== [RESET DATA SELESAI DENGAN SUKSES] ===\n";

