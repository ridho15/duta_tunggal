<div class="space-y-1">
    @if($getRecord()->saleOrderItem->count() > 0)
        @php
            $warehouses = $getRecord()->saleOrderItem->groupBy('warehouse.name')->map(function($items, $warehouseName) {
                return [
                    'name' => $warehouseName,
                    'count' => $items->count(),
                    'total_qty' => $items->sum('quantity')
                ];
            });
        @endphp

        @foreach($warehouses as $warehouse)
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    🏭 {{ $warehouse['name'] ?? 'Unknown' }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ $warehouse['count'] }} items ({{ number_format($warehouse['total_qty'], 2) }} qty)
                </span>
            </div>
        @endforeach

        @if($getRecord()->saleOrderItem->whereNull('warehouse_id')->count() > 0)
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    ⚠️ Unassigned
                </span>
                <span class="text-xs text-gray-500">
                    {{ $getRecord()->saleOrderItem->whereNull('warehouse_id')->count() }} items
                </span>
            </div>
        @endif
    @else
        <span class="text-xs text-gray-400">No items</span>
    @endif
</div>