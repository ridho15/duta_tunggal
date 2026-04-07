@php
    $record = $getRecord();
    $items = $record?->items ?? collect();
@endphp

@if($items->isEmpty())
    <p class="text-sm italic text-gray-500">Belum ada detail bahan baku.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">#</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Produk</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Kebutuhan</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Gudang</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Rak</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Stok Tersedia</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Stok Reservasi</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    @php
                        $warehouse = $item->warehouse ?? $record?->warehouse;
                        $metrics = \App\Filament\Resources\MaterialIssueResource::getStockMetrics(
                            (int) $item->product_id,
                            (int) ($item->warehouse_id ?? $record?->warehouse_id),
                            $record,
                        );
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-3 py-2">{{ $index + 1 }}</td>
                        <td class="border border-gray-300 px-3 py-2">({{ $item->product->sku ?? '-' }}) {{ $item->product->name ?? '-' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format((float) ($item->quantity ?? 0), 2, ',', '.') }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $warehouse ? '(' . $warehouse->kode . ') ' . $warehouse->name : '-' }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $item->rak ? '(' . $item->rak->code . ') ' . $item->rak->name : '-' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right font-medium {{ $metrics['available'] >= (float) ($item->quantity ?? 0) ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format((float) $metrics['available'], 2, ',', '.') }}
                        </td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format((float) $metrics['reserved'], 2, ',', '.') }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ \Illuminate\Support\Str::headline((string) ($item->status ?? '-')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
