<?php

namespace Tests\Unit;

use App\Models\ProductionPlan;
use App\Services\ProductionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function generated_plan_number_is_unique_and_matches_pattern()
    {
        $service = new ProductionPlanService();

        // create dummy related records for required foreign keys
        $product = \App\Models\Product::factory()->create();
        $uom = \App\Models\UnitOfMeasure::factory()->create();
        $user = \App\Models\User::factory()->create();

        // create an existing plan to force a collision test
        ProductionPlan::create([
            'plan_number' => 'PP'.now()->format('Ymd').'0001',
            'name' => 'Existing Plan',
            'source_type' => 'manual',
            'product_id' => $product->id,
            'quantity' => 1,
            'uom_id' => $uom->id,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $planNumber = $service->generatePlanNumber();

        $this->assertMatchesRegularExpression('/^PP\d{8}\d{4}$/', $planNumber);
        $this->assertDatabaseMissing('production_plans', ['plan_number' => $planNumber]);
    }
}
