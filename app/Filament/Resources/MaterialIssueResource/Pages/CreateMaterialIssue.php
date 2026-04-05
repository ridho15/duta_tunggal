<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use App\Models\MaterialIssue;
use App\Services\ManufacturingJournalService;
use App\Services\ManufacturingService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        // 2) Auto journal if created directly as completed
        $mi = $this->record;
        if ($this->record->status === 'completed') {
            try {
                $journalService = app(ManufacturingJournalService::class);
                if ($mi->type === 'issue') {
                    $journalService->generateJournalForMaterialIssue($mi);
                } else {
                    $journalService->generateJournalForMaterialReturn($mi);
                }
                // And ensure MO qty_used aggregation is up to date
                $this->updateMoQtyUsed($mi);
            } catch (Throwable $exception) {
                ProcurementFailureNotifier::warning(
                    'Peringatan: Jurnal Otomatis Gagal',
                    $exception,
                    'Material Issue berhasil dibuat, namun jurnal otomatis belum dapat dibuat.'
                );
            }
        }
    }

    /**
     * Mirror the observer's MO qty_used aggregation for the case when a record
     * is created directly in 'completed' status (no status transition happens).
     */
    private function updateMoQtyUsed(MaterialIssue $materialIssue): void
    {
        // Legacy manufacturing_order_materials table has been removed.
        // Material fulfillment is now derived directly from MaterialIssue + BOM data.
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate stock availability before creating Material Issue
        $this->validateStockAvailability($data);

        if (isset($data['items']) && is_array($data['items'])) {
            $totalCost = 0;
            foreach ($data['items'] as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $warehouseId = $item['warehouse_id'] ?? null;

                // Normalize quantity (handle formatted strings like "1.000,00")
                $quantity = (float) \App\Helpers\MoneyHelper::parse($item['quantity'] ?? 0);

                if (!$productId || !$warehouseId) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.product_id' => 'Pilih produk dan gudang untuk item ' . ($index + 1),
                    ]);
                }

                $stockMetrics = MaterialIssueResource::getStockMetrics($productId, $warehouseId);
                $effectiveStock = (float) $stockMetrics['effective'];

                if ($effectiveStock < $quantity) {
                    $product = \App\Models\Product::find($productId);
                    $productName = $product ? $product->name : 'Produk';
                    throw ValidationException::withMessages([
                        'items.' . $index . '.quantity' => "Stok efektif {$productName} di gudang ini tidak mencukupi untuk item " . ($index + 1) . ". Fisik: " . number_format($stockMetrics['physical'], 2, ',', '.') . ", Reserved: " . number_format($stockMetrics['reserved'], 2, ',', '.') . ", Efektif: " . number_format($effectiveStock, 2, ',', '.') . ", diminta: " . number_format($quantity, 2, ',', '.'),
                    ]);
                }

                // Normalize cost_per_unit and total_cost (handle formatted strings)
                $costPerUnit = (float) \App\Helpers\MoneyHelper::parse($item['cost_per_unit'] ?? 0);

                $itemTotalCost = (float) \App\Helpers\MoneyHelper::parse($item['total_cost'] ?? ($quantity * $costPerUnit));

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

    protected function validateStockAvailability(array $data): void
    {
        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return; // No items to validate
        }

        $insufficientStock = [];
        $outOfStock = [];
        $warehouseId = $data['warehouse_id'] ?? null;

        foreach ($data['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                continue;
            }

            $stockMetrics = MaterialIssueResource::getStockMetrics(
                $item['product_id'],
                $item['warehouse_id'] ?? $warehouseId
            );
            $effectiveQty = (float) $stockMetrics['effective'];
            $requiredQty = (float) \App\Helpers\MoneyHelper::parse($item['quantity'] ?? 0);

            $product = \App\Models\Product::find($item['product_id']);

            if ($effectiveQty <= 0) {
                $outOfStock[] = "{$product->name} (Fisik: {$stockMetrics['physical']}, Reserved: {$stockMetrics['reserved']}, Efektif: {$stockMetrics['effective']})";
            } elseif ($effectiveQty < $requiredQty) {
                $insufficientStock[] = "{$product->name} (Dibutuhkan: {$requiredQty}, Fisik: {$stockMetrics['physical']}, Reserved: {$stockMetrics['reserved']}, Efektif: {$stockMetrics['effective']})";
            }
        }

        if (!empty($outOfStock)) {
            throw ValidationException::withMessages([
                'items' => 'Stok efektif habis untuk produk berikut: ' . implode(', ', $outOfStock)
            ]);
        }

        if (!empty($insufficientStock)) {
            throw ValidationException::withMessages([
                'items' => 'Stok efektif tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock)
            ]);
        }
    }
}
