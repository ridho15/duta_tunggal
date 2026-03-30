<?php

namespace Tests\Browser;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CompleteSalesFlowTest extends DuskTestCase
{
    private function setInputById(Browser $browser, string $id, string $value): void
    {
        $jsId = addslashes($id);
        $jsValue = addslashes($value);

        $browser->script("
            (function() {
                var el = document.querySelector('[id=\"{$jsId}\"]');
                if (!el) return;

                el.focus();
                el.value = '{$jsValue}';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.blur();
            })();
        ");
    }

    /** @test */
    public function complete_sales_flow_from_quotation_to_payment()
    {
        $user = User::where('email', 'ralamzah@gmail.com')->firstOrFail();
        $suffix = (string) now()->timestamp;

        $product = Product::factory()->create([
            'sku' => 'SKU-E2E-' . $suffix,
            'cost_price' => 100.00,
        ]);

        $arCoa = ChartOfAccount::firstWhere('code', '1120') ?? ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Accounts Receivable']);
        $revenueCoa = ChartOfAccount::firstWhere('code', '4000') ?? ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Revenue']);
        $inventoryCoa = ChartOfAccount::firstWhere('code', '1140.01') ?? ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Inventory']);
        $cogsCoa = ChartOfAccount::firstWhere('code', '5000') ?? ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'COGS']);
        $goodsDeliveryCoa = ChartOfAccount::firstWhere('code', '1140.20') ?? ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);
        $bankCoa = ChartOfAccount::firstWhere('code', '1112.01') ?? ChartOfAccount::factory()->create(['code' => '1112.01', 'name' => 'Kas / Bank']);
        $ppnKeluaranCoa = ChartOfAccount::firstWhere('code', '2120.06') ?? ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran']);

        $product->update([
            'inventory_coa_id' => $inventoryCoa->id,
            'sales_coa_id' => $revenueCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        ]);

        InventoryStock::where('product_id', $product->id)->delete();
        $stock = InventoryStock::factory()->create([
            'product_id' => $product->id,
            'qty_available' => 10,
        ]);

        $customer = Customer::factory()->create();
        $quotation = Quotation::factory()->create([
            'quotation_number' => 'QT-E2E-' . $suffix,
            'customer_id' => $customer->id,
            'date' => Carbon::now()->toDateString(),
            'status' => 'approve',
        ]);

        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150.00,
            'discount' => 0,
            'tax' => 11,
            'total_price' => 333.00,
        ]);

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150.00,
            'discount' => 0,
            'tax' => 0,
            'warehouse_id' => $stock->warehouse_id,
            'rak_id' => $stock->rak_id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'warehouse_id' => null,
            'status' => 'completed',
        ]);
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $deliveryService = app(\App\Services\DeliveryOrderService::class);
        $deliveryPosting = $deliveryService->postDeliveryOrder($deliveryOrder);
        expect($deliveryPosting['status'])->toBe('posted');

        InventoryStock::where('product_id', $product->id)->decrement('qty_available', 2);

        $invoice = Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => Carbon::now(),
            'invoice_number' => 'INV-E2E-' . $suffix,
            'subtotal' => 300.00,
            'dpp' => 300.00,
            'tax' => 0,
            'ppn_rate' => 11,
            'tipe_pajak' => 'Exclusive',
            'total' => 333.00,
            'status' => 'Unpaid',
            'delivery_orders' => [$deliveryOrder->id],
        ]);

        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'payment_date' => Carbon::now(),
            'total_payment' => 333.00,
            'status' => 'Draft',
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 333.00,
            'coa_id' => $bankCoa->id,
            'payment_date' => Carbon::now(),
        ]);

        $receipt->update(['status' => 'Paid']);

        $expectedPpn = number_format(
            $invoice->subtotal * ($invoice->ppn_rate / 100),
            0,
            ',',
            '.'
        );

        $this->browse(function (Browser $browser) use ($user, $quotation, $saleOrder, $deliveryOrder, $invoice, $receipt, $customer, $product, $expectedPpn) {
            $browser->visit('/admin/login')
                ->waitForText('Masuk ke akun Anda', 10);

            $this->setInputById($browser, 'data.email', $user->email);
            $this->setInputById($browser, 'data.password', 'ridho123');

            $browser->script("(function(){ var btn = document.querySelector('form button[type=\"submit\"]'); if (btn) btn.click(); })();");
            $browser->pause(10000);

            $browser->visit("/admin/quotations/{$quotation->id}")
                ->assertPathIs("/admin/quotations/{$quotation->id}")
                ->assertInputValue('#data\.quotation_number', $quotation->quotation_number)
                ->assertSee('Lihat Quotation');

            $browser->visit("/admin/sale-orders/{$saleOrder->id}")
                ->assertPathIs("/admin/sale-orders/{$saleOrder->id}")
                ->assertSee('Sales Order');

            $browser->visit("/admin/delivery-orders/{$deliveryOrder->id}")
                ->assertPathIs("/admin/delivery-orders/{$deliveryOrder->id}")
                ->assertSee('Delivery Order');

            $browser->visit("/admin/sales-invoices/{$invoice->id}")
                ->assertPathIs("/admin/sales-invoices/{$invoice->id}")
                ->assertSee('Invoice Information')
                ->assertSee('Financial Information');

            $browser->visit("/admin/customer-receipts/{$receipt->id}")
                ->assertPathIs("/admin/customer-receipts/{$receipt->id}")
                ->assertSee('Informasi Customer Receipt')
                ->assertSee($customer->name);
        });

        $deliveryJournals = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();
        $invoiceJournals = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();
        $receiptJournals = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->get();

        expect($deliveryJournals->count() + $invoiceJournals->count() + $receiptJournals->count())->toBeGreaterThan(0);

        expect($invoiceJournals->where('coa_id', $revenueCoa->id)->sum('credit'))->toBe(300.0);
        expect($invoiceJournals->where('coa_id', $ppnKeluaranCoa->id)->sum('credit'))->toBe(33.0);
        expect($deliveryJournals->where('coa_id', $goodsDeliveryCoa->id)->sum('debit'))->toBe(200.0);
        expect($invoiceJournals->where('coa_id', $goodsDeliveryCoa->id)->sum('credit'))->toBe(200.0);
        expect($receiptJournals->where('coa_id', $arCoa->id)->sum('credit'))->toBe(333.0);

        expect(abs($deliveryJournals->sum('debit') - $deliveryJournals->sum('credit')))->toBeLessThan(0.01);
        expect(abs($invoiceJournals->sum('debit') - $invoiceJournals->sum('credit')))->toBeLessThan(0.01);
        expect(abs($receiptJournals->sum('debit') - $receiptJournals->sum('credit')))->toBeLessThan(0.01);
    }
}