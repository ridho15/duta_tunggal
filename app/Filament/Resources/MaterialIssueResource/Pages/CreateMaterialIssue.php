<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use App\Models\MaterialIssue;
use App\Services\ManufacturingJournalService;
use App\Services\ManufacturingService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateMaterialIssue extends CreateRecord
{
    protected static string $resource = MaterialIssueResource::class;

    public function mount(): void
    {
        parent::mount();

        // Initialize form data with proper structure for all fields
        $this->form->fill([
            'data' => [
                'issue_number' => $this->generateIssueNumber('issue'),
                'type' => 'issue',
                'status' => 'draft',
                'items' => [],
                'total_cost' => 0,
                'notes' => null,
            ]
        ]);
    }

    protected function generateIssueNumber(string $type): string
    {
        $service = app(\App\Services\ManufacturingService::class);
        return $service->generateIssueNumber($type);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure items array exists and has proper structure for all items
        if (!isset($data['items'])) {
            $data['items'] = [];
        }

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                // Ensure all required fields are present with default values if missing
                $data['items'][$index] = array_merge([
                    'product_id' => null,
                    'uom_id' => null,
                    'warehouse_id' => null,
                    'rak_id' => null,
                    'quantity' => 0,
                    'cost_per_unit' => 0,
                    'total_cost' => 0,
                    'notes' => null,
                    'status' => 'draft',
                    'inventory_coa_id' => null,
                ], $item);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update total_cost based on saved items
        $this->record->load('items');
        $totalCost = $this->record->items->sum('total_cost');
        $this->record->update(['total_cost' => $totalCost]);

        // 1) Refresh material fulfillment on related plan
        /** @var MaterialIssue $mi */
        $mi = $this->record;
        if ($mi->production_plan_id) {
            try {
                $manufacturingService = app(ManufacturingService::class);
                $manufacturingService->updateMaterialFulfillment($mi->productionPlan);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2) Auto journal if created directly as completed
        /** @var MaterialIssue $mi */
        if ($mi->status === 'completed') {
            try {
                $journalService = app(ManufacturingJournalService::class);
                if ($mi->type === 'issue') {
                    $journalService->generateJournalForMaterialIssue($mi);
                } else {
                    $journalService->generateJournalForMaterialReturn($mi);
                }
                // And ensure MO qty_used aggregation is up to date
                $this->updateMoQtyUsed($mi);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Mirror the observer's MO qty_used aggregation for the case when a record
     * is created directly in 'completed' status (no status transition happens).
     */
    private function updateMoQtyUsed(MaterialIssue $materialIssue): void
    {
        // Resolve target MO: prefer explicit manufacturing_order_id, fallback to production_plan_id
        $mo = null;
        if ($materialIssue->manufacturing_order_id) {
            $mo = \App\Models\ManufacturingOrder::find($materialIssue->manufacturing_order_id);
        }
        if (!$mo && $materialIssue->production_plan_id) {
            $mo = \App\Models\ManufacturingOrder::where('production_plan_id', $materialIssue->production_plan_id)
                ->latest('id')
                ->first();
        }

        if (!$mo) {
            return;
        }

        $mo->loadMissing(['manufacturingOrderMaterial']);

        foreach ($mo->manufacturingOrderMaterial as $mom) {
            // Sum of quantities from completed Material Issues of type 'issue' that relate to this MO
            $issuedQty = \App\Models\MaterialIssueItem::query()
                ->where('product_id', $mom->material_id)
                ->whereHas('materialIssue', function ($q) use ($mo) {
                    $q->where('type', 'issue')
                        ->where('status', 'completed')
                        ->where(function ($q2) use ($mo) {
                            $q2->where('manufacturing_order_id', $mo->id)
                                ->orWhere(function ($q3) use ($mo) {
                                    $q3->whereNull('manufacturing_order_id')
                                        ->where('production_plan_id', $mo->production_plan_id);
                                });
                        });
                })
                ->sum('quantity');

            if ($mom->qty_used != $issuedQty) {
                $mom->qty_used = $issuedQty;
                $mom->save();
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $totalCost = 0;
            foreach ($data['items'] as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $warehouseId = $item['warehouse_id'] ?? null;

                // Normalize quantity (handle formatted strings like "1.000,00")
                $rawQuantity = $item['quantity'] ?? 0;
                if (is_string($rawQuantity)) {
                    $rawQuantity = str_replace('.', '', $rawQuantity);
                    $rawQuantity = str_replace(',', '.', $rawQuantity);
                }
                $quantity = (float) $rawQuantity;

                if (!$productId || !$warehouseId) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.product_id' => 'Pilih produk dan gudang untuk item ' . ($index + 1),
                    ]);
                }

                $stock = \App\Models\InventoryStock::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->sum('qty_available');

                if ($stock < $quantity) {
                    $product = \App\Models\Product::find($productId);
                    $productName = $product ? $product->name : 'Produk';
                    throw ValidationException::withMessages([
                        'items.' . $index . '.quantity' => "Stock {$productName} di gudang ini tidak mencukupi untuk item " . ($index + 1) . ". Tersedia: " . number_format($stock, 2, ',', '.') . ", diminta: " . number_format($quantity, 2, ',', '.'),
                    ]);
                }

                // Normalize cost_per_unit and total_cost (handle formatted strings)
                $rawCostPerUnit = $item['cost_per_unit'] ?? 0;
                if (is_string($rawCostPerUnit)) {
                    $rawCostPerUnit = str_replace('.', '', $rawCostPerUnit);
                    $rawCostPerUnit = str_replace(',', '.', $rawCostPerUnit);
                }
                $costPerUnit = (float) $rawCostPerUnit;

                $rawItemTotal = $item['total_cost'] ?? ($quantity * $costPerUnit);
                if (is_string($rawItemTotal)) {
                    $rawItemTotal = str_replace('.', '', $rawItemTotal);
                    $rawItemTotal = str_replace(',', '.', $rawItemTotal);
                }
                $itemTotalCost = (float) $rawItemTotal;

                // Ensure the item values stored are numeric (so DB receives correct types)
                $data['items'][$index]['quantity'] = $quantity;
                $data['items'][$index]['cost_per_unit'] = $costPerUnit;
                $data['items'][$index]['total_cost'] = $itemTotalCost;

                $totalCost += $itemTotalCost;
            }
            $data['total_cost'] = $totalCost;
        } else {
            $data['total_cost'] = 0;
        }

        return $data;
    }
}
