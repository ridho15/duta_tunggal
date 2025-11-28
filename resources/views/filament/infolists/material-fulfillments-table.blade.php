@php
    $fulfillments = $getState();
@endphp

@if($fulfillments->count() > 0)
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3">Bahan Baku</th>
                    <th scope="col" class="px-6 py-3">Dibutuhkan</th>
                    <th scope="col" class="px-6 py-3">Stok Saat Ini</th>
                    <th scope="col" class="px-6 py-3">Sudah Diambil</th>
                    <th scope="col" class="px-6 py-3">Sisa</th>
                    <th scope="col" class="px-6 py-3">Ketersediaan</th>
                    <th scope="col" class="px-6 py-3">Penggunaan</th>
                    <th scope="col" class="px-6 py-3">Terakhir Update</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fulfillments as $fulfillment)
                    <tr class="bg-white border-b">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $fulfillment->material ? $fulfillment->material->name : 'Material tidak ditemukan' }}
                        </td>
                        <td class="px-6 py-4">
                            {{ number_format($fulfillment->required_quantity, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            {{ number_format($fulfillment->current_stock, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            {{ number_format($fulfillment->issued_quantity, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            {{ number_format($fulfillment->remaining_to_issue, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($fulfillment->availability_percentage >= 100) bg-green-100 text-green-800
                                @elseif($fulfillment->availability_percentage > 0) bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ number_format($fulfillment->availability_percentage, 1) }}%
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($fulfillment->usage_percentage >= 100) bg-green-100 text-green-800
                                @elseif($fulfillment->usage_percentage > 0) bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ number_format($fulfillment->usage_percentage, 1) }}%
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            {{ $fulfillment->last_updated_at ? $fulfillment->last_updated_at->format('d/m/Y H:i') : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-gray-500 italic">Belum ada data pemenuhan bahan baku.</p>
@endif