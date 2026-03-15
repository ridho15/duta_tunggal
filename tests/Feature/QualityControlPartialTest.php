<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for partial QC support on PurchaseOrderItem.
 *
 * Verifies:
 *  1. PurchaseOrderItem::qualityControls() morphMany relationship works
 *  2. Remaining qty calculation (= ordered - sum of existing QC inspected)
 *  3. Multiple QC records allowed for same PO item (partial)
 *  4. Items fully inspected are excluded from "needs QC" filter
 *  5. Items partially inspected appear in "needs QC" (remaining > 0) filter
 */
class QualityControlPartialTest extends TestCase
{
    use RefreshDatabase;

    protected User      $user;
    protected Supplier  $supplier;
    protected Warehouse $warehouse;
    protected Product   $product;
    protected Currency  $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);

        $this->user      = User::factory()->create();
        $this->supplier  = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product   = Product::factory()->create();
        $this->currency  = Currency::factory()->create();

        $this->actingAs($this->user);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    protected function makePOItem(int $quantity = 10): PurchaseOrderItem
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status'      => 'approved',
        ]);

        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id'        => $this->product->id,
            'currency_id'       => $this->currency->id,
            'quantity'          => $quantity,
        ]);
    }

    protected function makeQC(PurchaseOrderItem $poItem, float $passed, float $rejected): QualityControl
    {
        static $seq = 0;
        $seq++;
        return QualityControl::create([
            'from_model_type'  => PurchaseOrderItem::class,
            'from_model_id'    => $poItem->id,
            'qc_number'        => 'QC-TEST-' . now()->format('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'product_id'       => $poItem->product_id,
            'warehouse_id'     => $this->warehouse->id,
            'passed_quantity'  => $passed,
            'rejected_quantity'=> $rejected,
            'status'           => 0,
            'inspected_by'     => $this->user->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 1. morphMany relationship
    // -----------------------------------------------------------------------

    public function test_purchase_order_item_has_quality_controls_morph_many(): void
    {
        $poItem = $this->makePOItem(10);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $poItem->qualityControls());
    }

    public function test_quality_controls_returns_empty_collection_when_no_qc(): void
    {
        $poItem = $this->makePOItem(10);
        $this->assertCount(0, $poItem->qualityControls);
    }

    public function test_quality_controls_returns_all_qc_records(): void
    {
        $poItem = $this->makePOItem(20);

        $this->makeQC($poItem, 8, 2);  // first partial QC: 10 inspected
        $this->makeQC($poItem, 5, 5);  // second partial QC: 10 more

        $poItem->refresh();
        $this->assertCount(2, $poItem->qualityControls);
    }

    // -----------------------------------------------------------------------
    // 2. morphOne still works (backward compat)
    // -----------------------------------------------------------------------

    public function test_morphone_still_returns_first_qc(): void
    {
        $poItem = $this->makePOItem(20);

        $first  = $this->makeQC($poItem, 8, 0);
        $this->makeQC($poItem, 5, 0);

        $poItem->refresh();
        // morphOne returns first inserted
        $this->assertNotNull($poItem->qualityControl);
        $this->assertEquals($first->id, $poItem->qualityControl->id);
    }

    // -----------------------------------------------------------------------
    // 3. Remaining qty calculation
    // -----------------------------------------------------------------------

    public function test_remaining_qty_is_full_qty_when_no_qc(): void
    {
        $poItem    = $this->makePOItem(15);
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remaining = max(0, $poItem->quantity - $inspected);

        $this->assertEquals(15, $remaining);
    }

    public function test_remaining_qty_decreases_after_partial_qc(): void
    {
        $poItem = $this->makePOItem(20);
        $this->makeQC($poItem, 8, 2); // 10 inspected

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remaining = max(0, $poItem->quantity - $inspected);

        $this->assertEquals(10, $remaining);
    }

    public function test_remaining_qty_is_zero_after_full_inspection(): void
    {
        $poItem = $this->makePOItem(10);
        $this->makeQC($poItem, 7, 3); // 10 inspected — matches ordered qty

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remaining = max(0, $poItem->quantity - $inspected);

        $this->assertEquals(0, $remaining);
    }

    public function test_remaining_qty_sums_multiple_qc_records(): void
    {
        $poItem = $this->makePOItem(30);
        $this->makeQC($poItem, 5, 5);  // 10 inspected
        $this->makeQC($poItem, 8, 2);  // 10 more

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remaining = max(0, $poItem->quantity - $inspected);

        $this->assertEquals(10, $remaining);
    }

    public function test_remaining_never_goes_negative(): void
    {
        $poItem = $this->makePOItem(5);
        $this->makeQC($poItem, 3, 2); // exactly 5 inspected

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remaining = max(0, $poItem->quantity - $inspected);

        $this->assertEquals(0, $remaining);
        $this->assertGreaterThanOrEqual(0, $remaining);
    }

    // -----------------------------------------------------------------------
    // 4. Multiple QC records for same PO item (partial support)
    // -----------------------------------------------------------------------

    public function test_can_create_multiple_qc_for_same_po_item(): void
    {
        $poItem = $this->makePOItem(20);

        $qc1 = $this->makeQC($poItem, 8, 2);
        $qc2 = $this->makeQC($poItem, 5, 5);

        $this->assertDatabaseHas('quality_controls', ['id' => $qc1->id]);
        $this->assertDatabaseHas('quality_controls', ['id' => $qc2->id]);

        $count = QualityControl::where('from_model_type', PurchaseOrderItem::class)
            ->where('from_model_id', $poItem->id)
            ->count();
        $this->assertEquals(2, $count);
    }

    public function test_second_qc_for_same_item_reduces_remaining(): void
    {
        $poItem = $this->makePOItem(20);

        $this->makeQC($poItem, 8, 2); // 10 inspected

        $poItem->load('qualityControls');
        $remainingAfterFirst = max(0, $poItem->quantity - $poItem->qualityControls->sum(
            fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
        ));
        $this->assertEquals(10, $remainingAfterFirst);

        $this->makeQC($poItem, 5, 5); // 10 more

        $poItem->load('qualityControls');
        $remainingAfterSecond = max(0, $poItem->quantity - $poItem->qualityControls->sum(
            fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
        ));
        $this->assertEquals(0, $remainingAfterSecond);
    }

    // -----------------------------------------------------------------------
    // 5. "Needs QC" filter logic
    // -----------------------------------------------------------------------

    public function test_item_with_no_qc_needs_inspection(): void
    {
        $poItem = $this->makePOItem(10);
        $poItem->load('qualityControls');

        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $needsQc   = ($poItem->quantity - $inspected) > 0;

        $this->assertTrue($needsQc);
    }

    public function test_item_with_partial_qc_still_needs_inspection(): void
    {
        $poItem = $this->makePOItem(10);
        $this->makeQC($poItem, 4, 1); // 5 of 10 inspected

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $needsQc   = ($poItem->quantity - $inspected) > 0;

        $this->assertTrue($needsQc);
    }

    public function test_item_fully_inspected_does_not_need_qc(): void
    {
        $poItem = $this->makePOItem(10);
        $this->makeQC($poItem, 6, 4); // 10 of 10 inspected

        $poItem->load('qualityControls');
        $inspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $needsQc   = ($poItem->quantity - $inspected) > 0;

        $this->assertFalse($needsQc);
    }

    public function test_collection_filter_separates_needs_qc_from_fully_inspected(): void
    {
        $poItemPartial = $this->makePOItem(20);
        $poItemFull    = $this->makePOItem(10);
        $poItemNone    = $this->makePOItem(15);

        $this->makeQC($poItemPartial, 10, 0);  // 10/20 inspected — partial
        $this->makeQC($poItemFull,    6,  4);  // 10/10 inspected — full

        $allItems = PurchaseOrderItem::with('qualityControls')
            ->whereIn('id', [$poItemPartial->id, $poItemFull->id, $poItemNone->id])
            ->get();

        $needsQc = $allItems->filter(function ($item) {
            $inspected = $item->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
            return ($item->quantity - $inspected) > 0;
        });

        // partial and none should need QC; full should not
        $this->assertCount(2, $needsQc);
        $this->assertTrue($needsQc->contains('id', $poItemPartial->id));
        $this->assertTrue($needsQc->contains('id', $poItemNone->id));
        $this->assertFalse($needsQc->contains('id', $poItemFull->id));
    }

    // -----------------------------------------------------------------------
    // 6. Batch create action logic (remaining qty clamping)
    // -----------------------------------------------------------------------

    public function test_batch_create_clamps_passed_qty_to_remaining(): void
    {
        $poItem = $this->makePOItem(10);
        $this->makeQC($poItem, 6, 0); // 6 of 10 inspected, remaining = 4

        $poItem->load('qualityControls');
        $alreadyInspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remainingQty = max(0, $poItem->quantity - $alreadyInspected);

        // Simulate batch action clamping (user entered 8 but only 4 remain)
        $userEnteredPassed   = 8;
        $userEnteredRejected = 0;

        $clampedPassed   = min($userEnteredPassed,   $remainingQty);
        $clampedRejected = min($userEnteredRejected, max(0, $remainingQty - $clampedPassed));

        $this->assertEquals(4, $clampedPassed);
        $this->assertEquals(0, $clampedRejected);
    }

    public function test_batch_create_skips_fully_inspected_items(): void
    {
        $poItem = $this->makePOItem(10);
        $this->makeQC($poItem, 10, 0); // fully inspected

        $poItem->load('qualityControls');
        $alreadyInspected = $poItem->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
        $remainingQty     = max(0, $poItem->quantity - $alreadyInspected);

        $this->assertEquals(0, $remainingQty);

        // Batch action: if remainingQty <= 0, skip creating QC
        $shouldCreate = $remainingQty > 0;
        $this->assertFalse($shouldCreate);
    }
}
