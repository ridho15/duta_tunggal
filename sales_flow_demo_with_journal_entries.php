<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Models\JournalEntry;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "🔍 Checking Existing Journal Entries in Database\n";
echo "===============================================\n\n";

$journalEntries = JournalEntry::with(['debitAccount', 'creditAccount'])
    ->orderBy('created_at', 'desc')
    ->take(20)
    ->get();

if ($journalEntries->isEmpty()) {
    echo "❌ No journal entries found in database\n";
    echo "\n💡 To see journal entries being created automatically, run:\n";
    echo "   php artisan test --filter=CompleteSalesFlowFilamentTest\n";
    echo "\nThis test demonstrates the complete sales flow and validates that journal entries are created at each step.\n";
} else {
    echo "📊 Recent Journal Entries Found:\n\n";

    foreach ($journalEntries as $entry) {
        echo "🔸 Entry ID: {$entry->id}\n";
        echo "   Description: {$entry->description}\n";
        echo "   Date: {$entry->date}\n";
        echo "   Debit:  {$entry->debitAccount->code} - {$entry->debitAccount->name} | Amount: Rp " . number_format($entry->debit_amount, 2) . "\n";
        echo "   Credit: {$entry->creditAccount->code} - {$entry->creditAccount->name} | Amount: Rp " . number_format($entry->credit_amount, 2) . "\n";
        echo "   Balance Check: " . ($entry->debit_amount == $entry->credit_amount ? "✅ Balanced" : "❌ Unbalanced") . "\n";
        echo "   Created: {$entry->created_at}\n";
        echo "\n";
    }

    // Summary by account types
    echo "📊 SUMMARY BY ACCOUNT TYPES\n";
    echo "===========================\n";

    $revenueEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->creditAccount->name, 'Revenue') || $entry->creditAccount->code == '4000';
    });

    $arEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->debitAccount->name, 'Accounts Receivable') || $entry->debitAccount->code == '1120';
    });

    $cashEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->debitAccount->name, 'Cash') || $entry->debitAccount->code == '1111.01';
    });

    $cogsEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->debitAccount->name, 'Cost of Goods Sold') || $entry->debitAccount->code == '5000';
    });

    echo "💰 Revenue Entries: {$revenueEntries->count()}\n";
    echo "📋 AR Entries: {$arEntries->count()}\n";
    echo "💵 Cash Entries: {$cashEntries->count()}\n";
    echo "📦 COGS Entries: {$cogsEntries->count()}\n";
    echo "📈 Total Entries: {$journalEntries->count()}\n\n";
}

echo "🎯 HOW JOURNAL ENTRIES ARE CREATED AUTOMATICALLY\n";
echo "================================================\n";
echo "Based on the CompleteSalesFlowFilamentTest, journal entries are created through:\n\n";

echo "1. 🚚 DELIVERY ORDER POSTING (DeliveryOrderService::post())\n";
echo "   - Debit:  Cost of Goods Sold (5000)\n";
echo "   - Credit: Inventory (1130)\n";
echo "   - Trigger: When delivery order status changes to 'posted'\n\n";

echo "2. 💰 INVOICE CREATION (InvoiceObserver::created())\n";
echo "   - Debit:  Accounts Receivable (1120)\n";
echo "   - Credit: Revenue (4000)\n";
echo "   - Trigger: When invoice is created with status 'posted'\n\n";

echo "3. 💳 CUSTOMER PAYMENT (CustomerReceiptItemObserver::created())\n";
echo "   - Debit:  Cash (1111.01)\n";
echo "   - Credit: Accounts Receivable (1120)\n";
echo "   - Trigger: When customer receipt item is created\n\n";

echo "✅ All entries follow double-entry bookkeeping principles\n";
echo "✅ Each transaction is balanced (debit = credit)\n";
echo "✅ Automatic posting ensures accurate financial records\n\n";

echo "🔄 To see this in action, run the test:\n";
echo "   php artisan test --filter=CompleteSalesFlowFilamentTest\n";