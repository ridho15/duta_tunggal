@php
    $deliveryOrders = $deliveryOrders ?? collect();
    $hasDifferentCustomers = false;
    $customerNames = collect();

    foreach ($deliveryOrders as $do) {
        foreach ($do->salesOrders as $so) {
            if ($so->customer) {
                $customerNames->push("({$so->customer->code}) {$so->customer->name}");
            }
        }
    }
    $uniqueCustomers = $customerNames->unique()->values();
    if ($uniqueCustomers->count() > 1) {
        $hasDifferentCustomers = true;
    }
@endphp

@if ($deliveryOrders->isEmpty())
    <div class="p-4 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-center">
        Pilih minimal satu Delivery Order di atas untuk melihat rincian barang yang akan dikirim.
    </div>
@else
    @if ($hasDifferentCustomers)
        <div class="mb-3 p-3 bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-xs font-medium flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span><strong>Peringatan Konsolidasi:</strong> Delivery Order yang dipilih berasal dari customer berbeda: <em>{{ $uniqueCustomers->implode(', ') }}</em>. Surat Jalan wajib berasal dari customer yang sama.</span>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300 divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-semibold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-2.5 px-3 text-center w-12">No</th>
                    <th scope="col" class="py-2.5 px-3">Delivery Order</th>
                    <th scope="col" class="py-2.5 px-3">SKU</th>
                    <th scope="col" class="py-2.5 px-3">Nama Barang</th>
                    <th scope="col" class="py-2.5 px-3 text-right">Qty Kirim</th>
                    <th scope="col" class="py-2.5 px-3 text-center">Satuan</th>
                    <th scope="col" class="py-2.5 px-3">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @php $no = 1; $totalQty = 0; @endphp
                @foreach ($deliveryOrders as $do)
                    @foreach ($do->deliveryOrderItem as $item)
                        @php
                            $qty = (float) $item->quantity;
                            $totalQty += $qty;
                            $uomName = $item->product?->uom?->abbreviation ?: ($item->product?->uom?->name ?: '-');
                        @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-2 px-3 text-center text-gray-500 font-medium">{{ $no++ }}</td>
                            <td class="py-2 px-3 whitespace-nowrap font-medium text-primary-600 dark:text-primary-400">
                                {{ $do->do_number }}
                            </td>
                            <td class="py-2 px-3 font-mono text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $item->product?->sku ?: '-' }}</td>
                            <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">{{ $item->product?->name ?: '-' }}</td>
                            <td class="py-2 px-3 text-right font-bold text-gray-900 dark:text-white">
                                {{ number_format($qty, 0, ',', '.') }}
                            </td>
                            <td class="py-2 px-3 text-center text-gray-500">{{ $uomName }}</td>
                            <td class="py-2 px-3 text-gray-500 italic">{{ $item->reason ?: '-' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-800/80 font-semibold border-t-2 border-gray-200 dark:border-gray-700">
                <tr>
                    <td colspan="4" class="py-2.5 px-3 text-right text-gray-700 dark:text-gray-300">Total Kuantitas Kirim:</td>
                    <td class="py-2.5 px-3 text-right text-primary-600 dark:text-primary-400 font-bold text-sm">
                        {{ number_format($totalQty, 0, ',', '.') }}
                    </td>
                    <td colspan="2" class="py-2.5 px-3 text-gray-500 text-[11px]">
                        ({{ $no - 1 }} item barang dari {{ $deliveryOrders->count() }} DO)
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
