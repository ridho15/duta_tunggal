<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;

// Set a fake user for authentication
$user = \App\Models\User::first();
if (!$user) {
    $user = \App\Models\User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
}
Auth::login($user);

$supplier = \App\Models\Supplier::factory()->create(['name' => 'Test Supplier']);
$titipanCoa = \App\Models\ChartOfAccount::where('code', '2101')->first();
$kasCoa = \App\Models\ChartOfAccount::where('code', '1101')->first();

if (!$titipanCoa) {
    $titipanCoa = \App\Models\ChartOfAccount::create([
        'code' => '2101',
        'name' => 'UANG MUKA PEMBELIAN',
        'type' => 'asset',
        'is_active' => 1
    ]);
}

if (!$kasCoa) {
    $kasCoa = \App\Models\ChartOfAccount::create([
        'code' => '1101',
        'name' => 'KAS',
        'type' => 'asset',
        'is_active' => 1
    ]);
}

$deposit = \App\Models\Deposit::create([
    'from_model_type' => 'App\\Models\\Supplier',
    'from_model_id' => $supplier->id,
    'deposit_number' => 'DEP-SUP-TEST-' . time(),
    'amount' => 1000000,
    'remaining_amount' => 1000000,
    'note' => 'Test deposit supplier',
    'coa_id' => $titipanCoa->id,
    'payment_coa_id' => $kasCoa->id,
    'status' => true,
    'created_by' => $user->id
]);

// Manually call journal creation since afterCreate() is not triggered for manual model creation
$page = new \App\Filament\Resources\DepositResource\Pages\CreateDeposit();
$page->record = $deposit;
$page->createDepositJournalEntries();

echo 'Deposit created with ID: ' . $deposit->id . PHP_EOL;
echo 'Journal entries count via relationship: ' . $deposit->journalEntry()->count() . PHP_EOL;

$journalEntries = $deposit->journalEntry()->get();
foreach ($journalEntries as $entry) {
    echo 'Journal Entry ID: ' . $entry->id . ' - COA: ' . $entry->coa->code . ' - Debit: ' . ($entry->debit ?? 0) . ' - Credit: ' . ($entry->credit ?? 0) . PHP_EOL;
}

$allJournalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Deposit::class)->where('source_id', $deposit->id)->get();
echo 'All journal entries for this deposit: ' . $allJournalEntries->count() . PHP_EOL;
foreach ($allJournalEntries as $entry) {
    echo 'Journal Entry ID: ' . $entry->id . ' - COA: ' . $entry->coa->code . ' - Debit: ' . ($entry->debit ?? 0) . ' - Credit: ' . ($entry->credit ?? 0) . PHP_EOL;
}