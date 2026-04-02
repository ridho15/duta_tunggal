@php
    $record = $getRecord();
    $requirements = $record?->getMaterialRequirements() ?? collect();
@endphp

@if($requirements->isEmpty())
    <p class="text-sm italic text-gray-500">Belum ada data kebutuhan bahan.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">#</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Material</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Kebutuhan</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Stok Tersedia</th>
                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Terpakai</th>
                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requirements as $index => $requirement)
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-3 py-2">{{ $index + 1 }}</td>
                        <td class="border border-gray-300 px-3 py-2">({{ $requirement['product_sku'] ?? '-' }}) {{ $requirement['product_name'] ?? '-' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format((float) ($requirement['required_quantity'] ?? 0), 2, ',', '.') }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format((float) ($requirement['current_stock'] ?? 0), 2, ',', '.') }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format((float) ($requirement['issued_quantity'] ?? 0), 2, ',', '.') }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ \Illuminate\Support\Str::headline((string) ($requirement['availability_status'] ?? '-')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
