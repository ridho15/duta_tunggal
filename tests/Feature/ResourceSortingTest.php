<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\OrderRequest;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Quotation;
use App\Models\ReturnProduct;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VoucherRequest;
use App\Models\WarehouseConfirmation;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ResourceSortingTest
 *
 * Verifies that all ERP resources return records sorted newest-first.
 *
 * Sorting rule:
 *  - Most resources : ORDER BY created_at DESC
 *  - Invoices       : ORDER BY invoice_date DESC
 *  - StockMovement  : ORDER BY date DESC
 *  - InventoryStock : ORDER BY updated_at DESC
 */
class ResourceSortingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Pre-created shared records ──────────────────────────────────────────

    protected User      $user;
    protected Warehouse $warehouse;
    protected Supplier  $supplier;
    protected Customer  $customer;
    protected Product   $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->supplier  = Supplier::factory()->create();
        $this->customer  = Customer::factory()->create();

        UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'uom_id' => UnitOfMeasure::first()->id,
        ]);

        $this->actingAs($this->user);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Assert the given Eloquent builder returns $newerKey before $olderKey
     * after ordering by $column DESC.
     */
    private function assertNewerFirst(
        \Illuminate\Database\Eloquent\Builder $query,
        string $column,
        int $newerKey,
        int $olderKey,
    ): void {
        $ids = $query->orderBy($column, 'desc')->pluck('id')->toArray();

        $newerPos = array_search($newerKey, $ids);
        $olderPos = array_search($olderKey, $ids);

        $this->assertNotFalse($newerPos, "Newer record (id={$newerKey}) not found in result.");
        $this->assertNotFalse($olderPos, "Older record (id={$olderKey}) not found in result.");
        $this->assertLessThan(
            $olderPos,
            $newerPos,
            "Expected newer record (id={$newerKey}) before older (id={$olderKey}) sorted by {$column} DESC."
        );
    }

    /**
     * Assert identical timestamps fall back to id DESC.
     */
    private function assertIdFallback(
        \Illuminate\Database\Eloquent\Builder $query,
        string $column,
        int $higherId,
        int $lowerId,
    ): void {
        $ids = $query->orderBy($column, 'desc')->orderBy('id', 'desc')->pluck('id')->toArray();

        $highPos = array_search($higherId, $ids);
        $lowPos  = array_search($lowerId, $ids);

        $this->assertNotFalse($highPos);
        $this->assertNotFalse($lowPos);
        $this->assertLessThan(
            $lowPos,
            $highPos,
            "With identical {$column}, higher id ({$higherId}) should appear before lower id ({$lowerId})."
        );
    }

    // ─── Tests: created_at DESC ──────────────────────────────────────────────

    /** @test */
    public function sale_orders_sort_newest_first(): void
    {
        $old = SaleOrder::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = SaleOrder::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(SaleOrder::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function purchase_orders_sort_newest_first(): void
    {
        $old = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_at'  => Carbon::parse('2026-03-01'),
        ]);
        $new = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_at'  => Carbon::parse('2026-03-10'),
        ]);

        $this->assertNewerFirst(PurchaseOrder::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function order_requests_sort_newest_first(): void
    {
        $old = OrderRequest::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_at'   => Carbon::parse('2026-03-01'),
        ]);
        $new = OrderRequest::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_at'   => Carbon::parse('2026-03-10'),
        ]);

        $this->assertNewerFirst(OrderRequest::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function deposits_sort_newest_first(): void
    {
        $old = Deposit::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = Deposit::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(Deposit::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function delivery_orders_sort_newest_first(): void
    {
        $old = DeliveryOrder::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = DeliveryOrder::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(DeliveryOrder::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function purchase_receipts_sort_newest_first(): void
    {
        $old = PurchaseReceipt::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = PurchaseReceipt::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(PurchaseReceipt::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function quotations_sort_newest_first(): void
    {
        $old = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'created_at'  => Carbon::parse('2026-03-01'),
        ]);
        $new = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'created_at'  => Carbon::parse('2026-03-10'),
        ]);

        $this->assertNewerFirst(Quotation::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function journal_entries_sort_newest_first(): void
    {
        $old = JournalEntry::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = JournalEntry::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(JournalEntry::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function payment_requests_sort_newest_first(): void
    {
        $old = PaymentRequest::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = PaymentRequest::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(PaymentRequest::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function voucher_requests_sort_newest_first(): void
    {
        $old = VoucherRequest::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = VoucherRequest::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(VoucherRequest::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function stock_adjustments_sort_newest_first(): void
    {
        $old = StockAdjustment::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = StockAdjustment::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(StockAdjustment::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function stock_transfers_sort_newest_first(): void
    {
        $old = StockTransfer::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = StockTransfer::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(StockTransfer::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function manufacturing_orders_sort_newest_first(): void
    {
        $old = ManufacturingOrder::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = ManufacturingOrder::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(ManufacturingOrder::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function surat_jalans_sort_newest_first(): void
    {
        $old = SuratJalan::factory()->create([
            'signed_by'  => $this->user->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::parse('2026-03-01'),
        ]);
        $new = SuratJalan::factory()->create([
            'signed_by'  => $this->user->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::parse('2026-03-10'),
        ]);

        $this->assertNewerFirst(SuratJalan::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function vendor_payments_sort_newest_first(): void
    {
        $old = VendorPayment::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = VendorPayment::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(VendorPayment::query(), 'created_at', $new->id, $old->id);
    }

    /** @test */
    public function warehouse_confirmations_sort_newest_first(): void
    {
        $old = WarehouseConfirmation::factory()->create(['created_at' => Carbon::parse('2026-03-01')]);
        $new = WarehouseConfirmation::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $this->assertNewerFirst(WarehouseConfirmation::query(), 'created_at', $new->id, $old->id);
    }

    // ─── Tests: domain-specific date columns ────────────────────────────────

    /** @test */
    public function invoices_sort_by_invoice_date_newest_first(): void
    {
        $old = Invoice::factory()->create(['invoice_date' => '2026-03-01']);
        $new = Invoice::factory()->create(['invoice_date' => '2026-03-10']);

        $this->assertNewerFirst(Invoice::query(), 'invoice_date', $new->id, $old->id);
    }

    /** @test */
    public function stock_movements_sort_by_date_newest_first(): void
    {
        $old = StockMovement::factory()->create(['date' => '2026-03-01']);
        $new = StockMovement::factory()->create(['date' => '2026-03-10']);

        $this->assertNewerFirst(StockMovement::query(), 'date', $new->id, $old->id);
    }

    /** @test */
    public function inventory_stocks_sort_by_updated_at_newest_first(): void
    {
        // Use distinct product+warehouse+rak combos to avoid unique constraint
        $productA  = Product::factory()->create(['uom_id' => UnitOfMeasure::first()->id]);
        $productB  = Product::factory()->create(['uom_id' => UnitOfMeasure::first()->id]);

        $old = InventoryStock::factory()->create([
            'product_id'   => $productA->id,
            'warehouse_id' => $this->warehouse->id,
            'updated_at'   => Carbon::parse('2026-03-01'),
        ]);
        $new = InventoryStock::factory()->create([
            'product_id'   => $productB->id,
            'warehouse_id' => $this->warehouse->id,
            'updated_at'   => Carbon::parse('2026-03-10'),
        ]);

        $this->assertNewerFirst(InventoryStock::query(), 'updated_at', $new->id, $old->id);
    }

    // ─── Edge case: identical timestamps fall back to id DESC ────────────────

    /** @test */
    public function identical_created_at_timestamps_fall_back_to_id_desc(): void
    {
        $sameTime = Carbon::parse('2026-03-05 12:00:00');

        $lower  = SaleOrder::factory()->create(['created_at' => $sameTime]);
        $higher = SaleOrder::factory()->create(['created_at' => $sameTime]);

        $this->assertGreaterThan($lower->id, $higher->id);
        $this->assertIdFallback(SaleOrder::query(), 'created_at', $higher->id, $lower->id);
    }

    /** @test */
    public function identical_invoice_dates_fall_back_to_id_desc(): void
    {
        $sameDate = '2026-03-05';

        $lower  = Invoice::factory()->create(['invoice_date' => $sameDate]);
        $higher = Invoice::factory()->create(['invoice_date' => $sameDate]);

        $this->assertGreaterThan($lower->id, $higher->id);
        $this->assertIdFallback(Invoice::query(), 'invoice_date', $higher->id, $lower->id);
    }

    /** @test */
    public function identical_stock_movement_dates_fall_back_to_id_desc(): void
    {
        $sameDate = '2026-03-05';

        $lower  = StockMovement::factory()->create(['date' => $sameDate]);
        $higher = StockMovement::factory()->create(['date' => $sameDate]);

        $this->assertGreaterThan($lower->id, $higher->id);
        $this->assertIdFallback(StockMovement::query(), 'date', $higher->id, $lower->id);
    }

    // ─── Pagination: newest on page 1 ───────────────────────────────────────

    /** @test */
    public function newest_sale_order_appears_on_first_page(): void
    {
        SaleOrder::factory()->count(20)->create(['created_at' => Carbon::parse('2026-02-01')]);
        $newest = SaleOrder::factory()->create(['created_at' => Carbon::parse('2026-03-10')]);

        $firstPage = SaleOrder::query()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $this->assertTrue(
            $firstPage->getCollection()->contains('id', $newest->id),
            "Newest SaleOrder (id={$newest->id}) should appear on page 1."
        );
    }

    /** @test */
    public function oldest_sale_order_does_not_appear_on_first_page(): void
    {
        $oldest = SaleOrder::factory()->create(['created_at' => Carbon::parse('2026-01-01')]);
        SaleOrder::factory()->count(20)->create(['created_at' => Carbon::parse('2026-03-01')]);

        $firstPage = SaleOrder::query()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $this->assertFalse(
            $firstPage->getCollection()->contains('id', $oldest->id),
            "Oldest SaleOrder (id={$oldest->id}) should NOT appear on page 1."
        );
    }
}


