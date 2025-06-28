<div class="" style="width: 100%">
    <h6 class="font-bold mb-2">Detail Perhitungan</h6>
    <table class="min-w-full text-sm border border-gray-300 overflow-hidden rounded-md" style="width: 100%">
        <thead class="bg-gray-100">
            <tr>
                <th class="text-left border border-gray-300 px-3 py-2 rounded-tl-md">Tanggal Proses</th>
                <th class="text-left border border-gray-300 px-3 py-2">No Pembelian</th>
                <th class="text-left border border-gray-300 px-3 py-2">Tipe</th>
                <th class="text-left border border-gray-300 px-3 py-2">Kalkulasi</th>
                <th class="text-left border border-gray-300 px-3 py-2">Item Value</th>
                <th class="text-left border border-gray-300 px-3 py-2 rounded-tr-md">Diproses Oleh</th>
            </tr>
        </thead>
        <tbody>
            @if (count($listPurchaseOrderItem))
            @foreach ($listPurchaseOrderItem as $item)
            <tr class="hover:bg-gray-50">
                <td class="border border-gray-300 px-3 py-2">{{ $item->purchaseOrder->date_approved }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $item->purchaseOrder->po_number }}</td>
                <td class="border border-gray-300 px-3 py-2">Pembelian</td>
                <td class="border border-gray-300 px-3 py-2">({{ $item->quantity }} * {{ $item->unit_price -
                    $item->discount + $item->tax }}) / {{ $item->quantity }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $item->quantity * ($item->unit_price - $item->discount +
                    $item->tax) / $item->quantity }}</td>
                <td class="border border-gray-300 px-3 py-2">{{ $item->purchaseOrder->createdBy->name }}</td>
            </tr>
            @endforeach
            @else
            <tr class="hover:bg-gray-50">
                <td colspan="6" class="text-center text-sm" style="">
                    <div style="margin-top: 20px; margin-bottom: 20px">
                        Tidak ada data
                    </div>
                </td>
            </tr>
            @endif
            <!-- Tambah baris lainnya -->
        </tbody>
    </table>
</div>