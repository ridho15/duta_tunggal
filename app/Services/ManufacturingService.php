<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\WarehouseConfirmation;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class ManufacturingService
{
    public function createWarehouseConfirmation($manufacturingOrder, array $attributes = []): WarehouseConfirmation
    {
        $warehouseConfirmation = WarehouseConfirmation::query()->firstOrNew([
            'confirmable_type' => ManufacturingOrder::class,
            'confirmable_id' => $manufacturingOrder->id,
        ]);

        $status = $attributes['status']
            ?? (strtolower((string) $warehouseConfirmation->status) === 'confirmed' ? 'confirmed' : 'request');

        $payload = array_merge([
            'confirmation_type' => 'manufacturing_order',
            'note' => $warehouseConfirmation->note ?: 'Auto-generated from Manufacturing Order',
            'rejection_reason' => null,
            'status' => $status,
        ], $attributes);

        if ($payload['status'] !== 'confirmed') {
            $payload['confirmed_by'] = null;
            $payload['confirmed_at'] = null;
        }

        $warehouseConfirmation->fill($payload);
        $warehouseConfirmation->save();

        return $warehouseConfirmation;
    }

    public function createWarehouseConfirmationForMaterialIssue(MaterialIssue $materialIssue, array $attributes = []): WarehouseConfirmation
    {
        $materialIssue->loadMissing('items.product');

        $existingConfirmations = WarehouseConfirmation::query()
            ->where('confirmable_type', MaterialIssue::class)
            ->where('confirmable_id', $materialIssue->id)
            ->with('warehouseConfirmationItems')
            ->get();

        $retainedConfirmationIds = [];
        $latestSyncedConfirmation = null;

        foreach ($materialIssue->items as $item) {
            $sourceConfirmation = $existingConfirmations->first(function (WarehouseConfirmation $confirmation) use ($item) {
                return $confirmation->warehouseConfirmationItems
                    ->contains(fn ($confirmationItem) => (int) $confirmationItem->material_issue_item_id === (int) $item->id);
            });

            $sourceConfirmationItem = $sourceConfirmation?->warehouseConfirmationItems
                ->firstWhere('material_issue_item_id', $item->id);

            $targetConfirmation = $existingConfirmations->first(function (WarehouseConfirmation $confirmation) use ($item) {
                if ($confirmation->warehouseConfirmationItems->count() !== 1) {
                    return false;
                }

                return (int) $confirmation->warehouseConfirmationItems->first()?->material_issue_item_id === (int) $item->id;
            });

            if (! $targetConfirmation) {
                $targetConfirmation = new WarehouseConfirmation([
                    'confirmable_type' => MaterialIssue::class,
                    'confirmable_id' => $materialIssue->id,
                ]);
            }

            $itemStatus = strtolower((string) ($sourceConfirmationItem?->status ?? 'request'));
            if (! in_array($itemStatus, ['confirmed', 'partial_confirmed', 'rejected'], true)) {
                $itemStatus = strtolower((string) ($attributes['status'] ?? 'request'));
                if (! in_array($itemStatus, ['confirmed', 'partial_confirmed', 'rejected'], true)) {
                    $itemStatus = 'request';
                }
            }

            $confirmationPayload = array_merge([
                'confirmation_type' => 'material_issue',
                'note' => $targetConfirmation->note ?: 'Auto-generated from Material Issue',
                'rejection_reason' => null,
                'status' => $itemStatus,
            ], $attributes);

            $confirmationPayload['status'] = $itemStatus;

            if (! in_array($itemStatus, ['confirmed', 'partial_confirmed', 'rejected'], true)) {
                $confirmationPayload['confirmed_by'] = null;
                $confirmationPayload['confirmed_at'] = null;
            } else {
                $confirmationPayload['confirmed_by'] = $confirmationPayload['confirmed_by']
                    ?? $sourceConfirmation?->confirmed_by
                    ?? $targetConfirmation->confirmed_by;
                $confirmationPayload['confirmed_at'] = $confirmationPayload['confirmed_at']
                    ?? $sourceConfirmation?->confirmed_at
                    ?? $targetConfirmation->confirmed_at;
            }

            $targetConfirmation->fill($confirmationPayload);
            $targetConfirmation->save();

            $itemPayload = [
                'material_issue_item_id' => $item->id,
                'product_id' => $item->product_id,
                'sale_order_item_id' => null,
                'product_name' => $item->product?->name ?? 'Unknown Product',
                'requested_qty' => $item->quantity,
                'confirmed_qty' => $sourceConfirmationItem?->confirmed_qty ?? $item->quantity,
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id,
                'status' => $itemStatus,
            ];

            $existingItem = $targetConfirmation->warehouseConfirmationItems()
                ->where('material_issue_item_id', $item->id)
                ->first();

            if ($existingItem) {
                $existingItem->fill($itemPayload);
                $existingItem->save();
            } else {
                $existingItem = $targetConfirmation->warehouseConfirmationItems()->create($itemPayload);
            }

            $targetConfirmation->warehouseConfirmationItems()
                ->where('id', '!=', $existingItem->id)
                ->delete();

            $retainedConfirmationIds[] = $targetConfirmation->id;
            $latestSyncedConfirmation = $targetConfirmation;
        }

        $existingConfirmations
            ->whereNotIn('id', array_unique($retainedConfirmationIds))
            ->each
            ->delete();

        if (! $latestSyncedConfirmation) {
            $latestSyncedConfirmation = WarehouseConfirmation::query()->firstOrCreate([
                'confirmable_type' => MaterialIssue::class,
                'confirmable_id' => $materialIssue->id,
            ], [
                'confirmation_type' => 'material_issue',
                'note' => 'Auto-generated from Material Issue',
                'status' => 'request',
            ]);
        }

        return $latestSyncedConfirmation->fresh(['warehouseConfirmationItems']);
    }

    public function checkStockMaterial($manufacturingOrder)
    {
        $plan = $manufacturingOrder->productionPlan;
        if (!$plan || !$plan->billOfMaterial) {
            return false;
        }

        $items = [];
        foreach ($plan->billOfMaterial->items as $bomItem) {
            $items[] = [
                'product_id' => $bomItem->product_id,
                'quantity' => $bomItem->quantity * $plan->quantity,
                'warehouse_id' => null, // Assuming no specific warehouse in BOM, or add if needed
                'rak_id' => null,
            ];
        }

        foreach ($items as $item) {
            $query = InventoryStock::where('product_id', $item['product_id']);
            if (!empty($item['warehouse_id'])) {
                $query->where('warehouse_id', $item['warehouse_id']);
            }
            if (!empty($item['rak_id'])) {
                $query->where('rak_id', $item['rak_id']);
            }
            $inventoryStock = $query->first();

            if (!$inventoryStock) {
                return false;
            }

            // Use quantity as the required amount
            $required = (float) ($item['quantity'] ?? 0);
            $available = (float) $inventoryStock->qty_available - (float) $inventoryStock->qty_reserved;
            if ($available < $required) {
                return false;
            }
        }

        return true;
    }

    public function generateMoNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'MO-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = ManufacturingOrder::where('mo_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public function generateIssueNumber(string $type = 'issue'): string
    {
        $prefix = $type === 'issue' ? 'MI' : 'MR'; // Material Issue / Material Return
        $date = now()->format('Ymd');
        $prefixFull = $prefix . '-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefixFull . $random;
            $exists = \App\Models\MaterialIssue::where('issue_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Create MaterialIssue automatically for a ProductionPlan
     */
    public function createMaterialIssueForProductionPlan(ProductionPlan $productionPlan): ?MaterialIssue
    {
        try {
            // Check if BOM exists
            if (!$productionPlan->billOfMaterial) {
                Log::warning("ProductionPlan {$productionPlan->id} has no BOM, skipping MaterialIssue creation");
                return null;
            }

            // Check if MaterialIssue already exists for this ProductionPlan
            $existingIssue = MaterialIssue::where('production_plan_id', $productionPlan->id)
                ->where('type', 'issue')
                ->first();

            if ($existingIssue) {
                Log::info("MaterialIssue already exists for ProductionPlan {$productionPlan->id}");
                return $existingIssue;
            }

            // Create MaterialIssue
            $materialIssue = MaterialIssue::create([
                'issue_number' => $this->generateIssueNumber('issue'),
                'production_plan_id' => $productionPlan->id,
                'warehouse_id' => $productionPlan->warehouse_id,
                'issue_date' => now()->toDateString(),
                'type' => 'issue',
                'status' => 'draft',
                'total_cost' => 0,
                'notes' => 'Auto-generated from Production Plan scheduling',
                'created_by' => Auth::id() ?? 1, // Default to admin if no auth
            ]);

            // Create MaterialIssueItems from BOM
            $totalCost = 0;
            foreach ($productionPlan->billOfMaterial->items as $bomItem) {
                $quantity = $bomItem->quantity * $productionPlan->quantity;
                $costPerUnit = $bomItem->product->cost_price ?? 0;
                $itemTotalCost = $quantity * $costPerUnit;
                $warehouseId = \App\Filament\Resources\MaterialIssueResource::resolveWarehouseIdForProduct(
                    $bomItem->product_id,
                    $productionPlan->warehouse_id
                );

                $materialIssueItem = MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $bomItem->product_id,
                    'uom_id' => $bomItem->uom_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost' => $itemTotalCost,
                    'status' => 'draft',
                ]);
                $totalCost += $itemTotalCost;
            }

            // Update total cost
            $materialIssue->update(['total_cost' => $totalCost]);

            Log::info("MaterialIssue {$materialIssue->issue_number} created for ProductionPlan {$productionPlan->id}");

            return $materialIssue;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Creating Material Issue')
                ->body('Failed to create Material Issue for Production Plan: ' . $e->getMessage())
                ->danger()
                ->send();
            Log::error('ManufacturingService createMaterialIssueForProductionPlan failed', [
                'production_plan_id' => $productionPlan->id,
                'warehouse_id' => $productionPlan->warehouse_id,
                'quantity' => $productionPlan->quantity,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate stock availability for ProductionPlan BOM items
     */
    protected function validateStockForProductionPlan(ProductionPlan $productionPlan): array
    {
        if (!$productionPlan->billOfMaterial) {
            return ['valid' => true, 'message' => 'No BOM to validate'];
        }

        $insufficientStock = [];
        $outOfStock = [];

        foreach ($productionPlan->billOfMaterial->items as $bomItem) {
            $requiredQty = $bomItem->quantity * $productionPlan->quantity;

            $inventoryStock = InventoryStock::where('product_id', $bomItem->product_id)
                ->where('warehouse_id', $productionPlan->warehouse_id)
                ->first();

            $availableQty = $inventoryStock ? (float) $inventoryStock->qty_available - (float) $inventoryStock->qty_reserved : 0;

            if ($availableQty <= 0) {
                $outOfStock[] = "{$bomItem->product->name} (Stock: 0)";
            } elseif ($availableQty < $requiredQty) {
                $insufficientStock[] = "{$bomItem->product->name} (Dibutuhkan: {$requiredQty}, Tersedia: {$availableQty})";
            }
        }

        if (!empty($outOfStock)) {
            return [
                'valid' => false,
                'message' => 'Stock habis untuk produk berikut: ' . implode(', ', $outOfStock)
            ];
        }

        if (!empty($insufficientStock)) {
            return [
                'valid' => false,
                'message' => 'Stock tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock)
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock tersedia untuk semua item'
        ];
    }
}
