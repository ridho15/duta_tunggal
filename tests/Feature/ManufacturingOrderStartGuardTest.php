<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Policies\ManufacturingOrderPolicy;
use App\Services\ManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();

    Permission::firstOrCreate(['name' => 'request manufacturing order', 'guard_name' => 'web']);
});

function buildManufacturingStartGuardFixture(): array
{
    $branch = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();

    $finishedProduct = Product::factory()->create([
        'is_manufacture' => true,
        'uom_id' => $uom->id,
        'cabang_id' => $branch->id,
    ]);

    $rawMaterial = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
        'cabang_id' => $branch->id,
    ]);

    $bom = BillOfMaterial::factory()->create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'is_active' => true,
    ]);

    BillOfMaterialItem::factory()->create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'bill_of_material_id' => $bom->id,
        'quantity' => 4,
        'status' => 'scheduled',
    ]);

    $manufacturingOrder = ManufacturingOrder::factory()->create([
        'cabang_id' => $branch->id,
        'production_plan_id' => $productionPlan->id,
        'status' => 'draft',
    ]);

    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $user->givePermissionTo('request manufacturing order');

    return compact('uom', 'finishedProduct', 'rawMaterial', 'bom', 'productionPlan', 'manufacturingOrder', 'user');
}

test('manufacturing order start is blocked until material issue and warehouse confirmation are complete', function () {
    $fixture = buildManufacturingStartGuardFixture();
    $mo = $fixture['manufacturingOrder'];

    expect($mo->productionStartBlockingMessage())
        ->toContain('Material Issue belum dibuat');

    $policy = app(ManufacturingOrderPolicy::class);

    expect($policy->updateStatus($fixture['user'], $mo, 'in_progress'))->toBeFalse();
    expect($mo->canStartProduction())->toBeFalse();
});

test('manufacturing order start is allowed after completed material issue confirmation', function () {
    $fixture = buildManufacturingStartGuardFixture();
    $mo = $fixture['manufacturingOrder'];

    $materialIssue = MaterialIssue::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'production_plan_id' => $fixture['productionPlan']->id,
        'issue_number' => 'MI-GUARD-001',
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_DRAFT,
    ]);

    MaterialIssueItem::factory()->create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $fixture['rawMaterial']->id,
        'quantity' => 8,
        'cost_per_unit' => 1000,
        'total_cost' => 8000,
    ]);

    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($materialIssue);

    $materialIssue->warehouseConfirmations()
        ->with('warehouseConfirmationItems')
        ->get()
        ->each(function ($confirmation) {
            $confirmation->warehouseConfirmationItems->each(function ($item) {
                $item->update([
                    'status' => 'confirmed',
                    'confirmed_qty' => $item->requested_qty,
                ]);
            });
        });

    $materialIssue->refresh();

    expect($materialIssue->status)->toBe(MaterialIssue::STATUS_COMPLETED);

    if ($materialIssue->status !== MaterialIssue::STATUS_COMPLETED) {
        $materialIssue->update([
            'status' => MaterialIssue::STATUS_COMPLETED,
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);
    }

    $mo->refresh();

    expect($mo->productionStartBlockingMessage())->toBeNull();
    expect($mo->canStartProduction())->toBeTrue();

    $policy = app(ManufacturingOrderPolicy::class);
    expect($policy->updateStatus($fixture['user'], $mo, 'in_progress'))->toBeTrue();
});
