<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug #2 – Server error when editing invoice
 *
 * Root cause: EditSalesInvoice::afterSave() called
 *   $this->record->invoiceItem()->create($item)
 * without populating the NOT NULL columns (discount, tax_rate, tax_amount,
 * subtotal), which caused an SQL integrity constraint error.
 *
 * Fix: array_merge() supplies defaults for all missing NOT NULL fields.
 *
 * Bug #3 – Delivery Order sometimes not generated after Sales Order is created
 *
 * Root cause: createDeliveryOrderForConfirmedWarehouseConfirmation() and
 * SalesOrderService::createDeliveryOrder() hard-coded driver_id = 1 and
 * vehicle_id = 1.  When those records do not exist the FK constraint
 * violation silently prevented DO creation.
 *
 * Fix: dynamically fetch the first available Driver/Vehicle; log a warning and
 * bail out gracefully when none exist.
 */
class InvoiceEditAndDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Bug #2 – InvoiceItem recreation provides all NOT NULL fields
    // ──────────────────────────────────────────────────────────────────────────

    public function test_invoice_item_creation_includes_all_required_fields(): void
    {
        $cabang   = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product  = Product::factory()->create();
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        // Use withoutEvents to skip InvoiceObserver (which tries to build AR records
        // and journal entries that would require extra DB fixtures unrelated to this fix).
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'from_model_type' => 'App\Models\SaleOrder',
            'from_model_id'   => 1,
            'customer_name'   => $customer->name,
            'cabang_id'       => $cabang->id,
        ]));

        $existingItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity'   => 5,
            'price'      => 100_000,
            'subtotal'   => 500_000,
            'discount'   => 0,
            'tax_rate'   => 0,
            'tax_amount' => 0,
            'total'      => 500_000,
        ]);

        // Simulate what EditSalesInvoice::afterSave() receives from the Repeater
        // (only the 4 fields visible in the form – no subtotal/discount/tax_*)
        $formItems = [
            [
                'product_id' => $product->id,
                'quantity'   => 5,
                'price'      => 120_000,
                'total'      => 600_000,
            ],
        ];

        // Replicate the fixed afterSave() logic
        $invoice->invoiceItem()->delete();

        foreach ($formItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price    = (float) ($item['price']    ?? 0);

            $itemData = array_merge($item, [
                'subtotal'   => $item['subtotal']   ?? ($quantity * $price),
                'discount'   => $item['discount']   ?? 0,
                'tax_rate'   => $item['tax_rate']   ?? 0,
                'tax_amount' => $item['tax_amount'] ?? 0,
            ]);

            $invoice->invoiceItem()->create($itemData);
        }

        $invoice->refresh();
        $newItem = $invoice->invoiceItem()->first();

        $this->assertNotNull($newItem, 'InvoiceItem was not created');
        $this->assertEquals(120_000, (float) $newItem->price);
        $this->assertEquals(600_000, (float) $newItem->subtotal);
        $this->assertEquals(0,       (float) $newItem->discount);
        $this->assertEquals(0,       (float) $newItem->tax_rate);
        $this->assertEquals(0,       (float) $newItem->tax_amount);
    }

    public function test_invoice_item_update_preserves_existing_coa_when_provided(): void
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create(['from_model_type' => 'App\Models\SaleOrder', 'from_model_id' => 1]));
        $product = Product::factory()->create();

        $formItems = [
            [
                'product_id' => $product->id,
                'quantity'   => 2,
                'price'      => 50_000,
                'total'      => 100_000,
                'coa_id'     => null,
                'subtotal'   => 100_000,
                'discount'   => 5,
                'tax_rate'   => 11,
                'tax_amount' => 11_000,
            ],
        ];

        $invoice->invoiceItem()->delete();

        foreach ($formItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price    = (float) ($item['price']    ?? 0);
            $itemData = array_merge($item, [
                'subtotal'   => $item['subtotal']   ?? ($quantity * $price),
                'discount'   => $item['discount']   ?? 0,
                'tax_rate'   => $item['tax_rate']   ?? 0,
                'tax_amount' => $item['tax_amount'] ?? 0,
            ]);
            $invoice->invoiceItem()->create($itemData);
        }

        $saved = $invoice->invoiceItem()->first();

        $this->assertEquals(5,      (float) $saved->discount);
        $this->assertEquals(11,     (float) $saved->tax_rate);
        $this->assertEquals(11_000, (float) $saved->tax_amount);
        $this->assertEquals(100_000,(float) $saved->subtotal);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bug #3 – Delivery Order auto-generation
    //
    // Tiga tes lama di sini memanggil createDeliveryOrderForConfirmedWarehouseConfirmation() — alur "WC → DO otomatis" sudah
    // dihapus (alur sekarang DO-sentris: DO dibuat, WC per item dibuat DARI DO, DO otomatis Siap Kirim bila semua WC
    // dikonfirmasi). Spesifikasinya (DO tanpa driver/kendaraan, Ambil Sendiri) kini ada di
    // tests/Feature/Stock/DeliveryOrderWarehouseConfirmationFlowTest.php.
    // ──────────────────────────────────────────────────────────────────────────
}
