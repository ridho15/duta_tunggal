<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingQcWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_production_creates_qc_without_completing_manufacturing_order(): void
    {
        $branch = Cabang::factory()->create();
        $user = User::factory()->create(['cabang_id' => $branch->id]);
        $this->actingAs($user);

        $uom = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'uom_id' => $uom->id,
            'cabang_id' => $branch->id,
        ]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        $plan = ProductionPlan::factory()->create([
            'source_type' => 'manual',
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $branch->id,
            'quantity' => 10,
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $manufacturingOrder = ManufacturingOrder::factory()->create([
            'production_plan_id' => $plan->id,
            'cabang_id' => $branch->id,
            'status' => 'in_progress',
        ]);

        $production = Production::create([
            'production_number' => 'PROD-QC-001',
            'manufacturing_order_id' => $manufacturingOrder->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $production->update(['status' => 'finished']);

        $this->assertDatabaseHas('quality_controls', [
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
        ]);

        $this->assertSame('in_progress', $manufacturingOrder->fresh()->status);
    }

    public function test_quality_control_completion_uses_updated_quantities_and_completes_mo_after_full_inspection(): void
    {
        $branch = Cabang::factory()->create();
        $user = User::factory()->create(['cabang_id' => $branch->id]);
        $this->actingAs($user);

        $uom = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'uom_id' => $uom->id,
            'cabang_id' => $branch->id,
        ]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        $plan = ProductionPlan::factory()->create([
            'source_type' => 'manual',
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $branch->id,
            'quantity' => 10,
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $manufacturingOrder = ManufacturingOrder::factory()->create([
            'production_plan_id' => $plan->id,
            'cabang_id' => $branch->id,
            'status' => 'in_progress',
        ]);

        $production = Production::create([
            'production_number' => 'PROD-QC-002',
            'manufacturing_order_id' => $manufacturingOrder->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'finished',
        ]);

        $qualityControl = QualityControl::create([
            'qc_number' => 'QC-M-REG-001',
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $user->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
            'cabang_id' => $branch->id,
        ]);

        app(QualityControlService::class)->completeQualityControl($qualityControl, [
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'reason_reject' => 'Retak pada sebagian hasil produksi',
            'warehouse_id' => $warehouse->id,
        ]);

        $qualityControl->refresh();

        $this->assertSame(8.0, (float) $qualityControl->passed_quantity);
        $this->assertSame(2.0, (float) $qualityControl->rejected_quantity);
        $this->assertSame('Retak pada sebagian hasil produksi', $qualityControl->reason_reject);
        $this->assertSame('completed', $manufacturingOrder->fresh()->status);
    }
}