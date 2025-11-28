<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditMaterialIssue extends EditRecord
{
    protected static string $resource = MaterialIssueResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $warehouseId = $item['warehouse_id'] ?? null;
                $quantity = (float) ($item['quantity'] ?? 0);

                if ($productId && $warehouseId) {
                    $stock = \App\Models\InventoryStock::where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->sum('qty_available');

                    if ($stock < $quantity) {
                        $product = \App\Models\Product::find($productId);
                        $productName = $product ? $product->name : 'Produk';
                        throw ValidationException::withMessages([
                            'items.' . $index . '.quantity' => 'Stock tidak mencukupi untuk ' . $productName . '. Tersedia: ' . $stock,
                        ]);
                    }
                }
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
