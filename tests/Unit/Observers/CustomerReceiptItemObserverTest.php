<?php

namespace Tests\Unit\Observers;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\JournalEntry;
use App\Observers\CustomerReceiptItemObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CustomerReceiptItemObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_configured_accounts_receivable_coa_for_receipt_item_journal(): void
    {
        Config::set('coa.accounts_receivable', '1120.10');

        $cashCoa = ChartOfAccount::factory()->create([
            'code' => '1111.01',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $receivableCoa = ChartOfAccount::factory()->create([
            'code' => '1120.10',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create();

        $receipt = CustomerReceipt::withoutEvents(fn () => CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'status' => 'draft',
            'coa_id' => $cashCoa->id,
        ]));

        $item = CustomerReceiptItem::withoutEvents(fn () => CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => null,
            'method' => 'cash',
            'amount' => 250000,
            'coa_id' => $cashCoa->id,
            'payment_date' => now()->toDateString(),
        ]));

        app(CustomerReceiptItemObserver::class)->created($item);

        $creditEntry = JournalEntry::query()
            ->where('source_type', CustomerReceiptItem::class)
            ->where('source_id', $item->id)
            ->where('credit', '>', 0)
            ->first();

        $this->assertNotNull($creditEntry);
        $this->assertSame($receivableCoa->id, $creditEntry->coa_id);
        $this->assertSame('250000.00', $creditEntry->credit);
    }
}