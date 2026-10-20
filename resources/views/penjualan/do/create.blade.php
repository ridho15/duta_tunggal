@extends('layouts.app')

@section('title', 'Buat Perintah Kirim (DO) – ' . $saleOrder->so_number)

@section('content')
<div class="max-w-[1080px] mx-auto w-full flex flex-col gap-5">
    <!-- Header Page -->
    <div class="pb-5 border-b border-gray-200 flex flex-col gap-2">
        <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
            <a href="{{ route('penjualan.so.index') }}" class="hover:text-emerald-700">Pesanan Penjualan</a>
            <span class="text-gray-300">/</span>
            <a href="{{ route('penjualan.so.show', $saleOrder->id) }}" class="hover:text-emerald-700">{{ $saleOrder->so_number }}</a>
            <span class="text-gray-300">/</span>
            <span class="font-bold text-gray-900">Buat Perintah Kirim</span>
        </div>

        <div class="flex flex-col gap-1 mt-1">
            <span class="self-start text-[11px] font-bold tracking-wider uppercase text-emerald-800 bg-emerald-50 border border-emerald-300 rounded-md px-2 py-0.5">
                DARI {{ $saleOrder->so_number }} · {{ $saleOrder->customer?->name }}
            </span>
            <h1 class="text-2xl lg:text-[28px] font-extrabold text-gray-900 m-0">Buat Perintah Kirim</h1>
            <div class="text-sm text-gray-500">Barang diambil otomatis dari SO. Isi kuantitas di gudang yang dipakai.</div>
        </div>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-800 font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.do.store') }}" id="doForm" class="flex flex-wrap lg:flex-nowrap gap-5 items-start">
        @csrf
        <input type="hidden" name="sale_order_id" value="{{ $saleOrder->id }}">

        <!-- Kolom Kiri: Kartu Produk & Alokasi Gudang -->
        <div class="w-full lg:flex-1 flex flex-col gap-4 min-w-0">
            @forelse($pendingItems as $idx => $pItem)
                @php
                    $item = $pItem['item'];
                    $available = (float) $pItem['available'];
                    $ordered = (float) $pItem['ordered'];
                    $warehouses = $pItem['warehouses'];
                @endphp
                <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-3.5 shadow-sm" id="product-card-{{ $idx }}">
                    <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}">
                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">

                    <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 inline-flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h2 class="m-0 text-base font-bold text-gray-900 truncate">{{ $item->product?->name }}</h2>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $item->product?->sku }} · hanya gudang yang punya stok</div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 bg-gray-100 border border-gray-200 rounded-xl px-3 py-1 shrink-0">
                            Sisa SO {{ round($available) }} pcs
                        </span>
                    </div>

                    @if(count($warehouses) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 text-xs border-b border-gray-100">
                                        <th class="pb-2 px-1 font-semibold">Gudang</th>
                                        <th class="pb-2 px-2 font-semibold text-right">Stok bebas</th>
                                        <th class="pb-2 px-1 font-semibold w-36">Qty dikirim</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($warehouses as $wh)
                                        <tr>
                                            <td class="py-2.5 px-1 font-medium text-gray-800">
                                                {{ $wh['warehouse_name'] }}
                                            </td>
                                            <td class="py-2.5 px-2 text-right text-gray-600 font-semibold">
                                                {{ round($wh['free_stock']) }}
                                            </td>
                                            <td class="py-2.5 px-1">
                                                <input type="number"
                                                       name="items[{{ $idx }}][warehouses][{{ $wh['warehouse_id'] }}]"
                                                       value="0"
                                                       min="0"
                                                       max="{{ min($available, $wh['free_stock']) }}"
                                                       data-wh-name="{{ $wh['warehouse_name'] }}"
                                                       data-wh-id="{{ $wh['warehouse_id'] }}"
                                                       data-prod-name="{{ $item->product?->name }}"
                                                       data-target-available="{{ $available }}"
                                                       oninput="updateDoAllocation()"
                                                       class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-bold text-gray-800 focus:outline-none focus:border-emerald-600 bg-white">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="self-end text-xs font-bold rounded-lg px-3 py-1" id="allocation-badge-{{ $idx }}">
                            Terisi 0 dari {{ round($available) }} pcs
                        </div>
                    @else
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900 font-medium">
                            Tidak ada gudang di cabang ini yang memiliki stok bebas untuk produk ini saat ini.
                        </div>
                    @endif
                </section>
            @empty
                <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-gray-500">
                    Seluruh barang pada Sales Order ini sudah selesai dibuatkan Delivery Order.
                </div>
            @endforelse

            <!-- Kartu Barang yang Sudah Lengkap Dibuatkan DO -->
            @foreach($completedItems as $cItem)
                <section class="bg-white border border-dashed border-gray-300 rounded-2xl p-4 sm:p-5 flex flex-wrap justify-between items-center gap-2 text-sm text-gray-500">
                    <span>
                        <b class="text-gray-900">{{ $cItem['item']->product?->name }}</b> · {{ $cItem['item']->product?->sku }}
                    </span>
                    <span class="text-xs text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 rounded-md px-2 py-0.5">
                        Sudah dibuat DO semua ({{ round($cItem['ordered']) }} dari {{ round($cItem['ordered']) }} pcs)
                    </span>
                </section>
            @endforeach
        </div>

        <!-- Kolom Kanan: Preview DO yang Akan Dibuat (Aside) -->
        <aside class="w-full lg:w-[320px] shrink-0 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-3.5 shadow-sm sticky top-[70px]">
            <h2 class="m-0 text-base font-bold text-gray-900 border-b border-gray-100 pb-2.5">Yang akan dibuat</h2>

            <div id="previewDoContainer" class="flex flex-col gap-2.5">
                <div class="text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl p-4 text-center">
                    Isi kuantitas pada gudang di sebelah kiri untuk melihat pembagian DO otomatis.
                </div>
            </div>

            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900">
                DO otomatis dipisah per gudang. Stok langsung dikunci saat DO dibuat.
            </div>

            <button type="submit" id="btnSubmitDo" class="w-full py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm rounded-xl min-h-[46px] shadow-sm transition-colors cursor-pointer mt-1">
                Buat DO
            </button>
            <a href="{{ route('penjualan.so.show', $saleOrder->id) }}" class="text-center text-xs sm:text-sm text-gray-500 hover:text-gray-800 py-1 transition-colors">
                Batal, kembali ke SO
            </a>
        </aside>
    </form>
</div>

<script>
    function updateDoAllocation() {
        const inputs = document.querySelectorAll('input[name*="[warehouses]"]');
        const warehouseSummary = {}; // wh_id -> { name, items: { prod_name: qty } }

        // Kelompokkan per kartu produk untuk verifikasi total sisa
        const productTotals = {};

        inputs.forEach(input => {
            const qty = parseFloat(input.value || 0);
            const whId = input.getAttribute('data-wh-id');
            const whName = input.getAttribute('data-wh-name');
            const prodName = input.getAttribute('data-prod-name');
            const targetAvailable = parseFloat(input.getAttribute('data-target-available') || 0);

            // Ekstrak index produk dari nama input: items[0][warehouses][1]
            const match = input.name.match(/items\[(\d+)\]/);
            const prodIdx = match ? match[1] : '0';

            productTotals[prodIdx] = productTotals[prodIdx] || { filled: 0, available: targetAvailable };
            productTotals[prodIdx].filled += qty;

            if (qty > 0) {
                if (!warehouseSummary[whId]) {
                    warehouseSummary[whId] = { name: whName, items: {} };
                }
                warehouseSummary[whId].items[prodName] = (warehouseSummary[whId].items[prodName] || 0) + qty;
            }
        });

        // Update badge pada tiap produk
        Object.keys(productTotals).forEach(idx => {
            const badge = document.getElementById(`allocation-badge-${idx}`);
            if (badge) {
                const filled = productTotals[idx].filled;
                const avail = productTotals[idx].available;
                if (filled === avail && avail > 0) {
                    badge.className = 'self-end text-xs font-bold rounded-lg px-3 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300';
                    badge.innerText = `Terisi ${filled} dari ${avail} pcs ✓`;
                } else if (filled > 0) {
                    badge.className = 'self-end text-xs font-bold rounded-lg px-3 py-1 bg-blue-50 text-blue-800 border border-blue-200';
                    badge.innerText = `Terisi ${filled} dari ${avail} pcs`;
                } else {
                    badge.className = 'self-end text-xs font-medium rounded-lg px-3 py-1 bg-gray-50 text-gray-500 border border-gray-200';
                    badge.innerText = `Terisi 0 dari ${avail} pcs`;
                }
            }
        });

        // Render preview DO di aside
        const previewContainer = document.getElementById('previewDoContainer');
        const whKeys = Object.keys(warehouseSummary);

        if (whKeys.length === 0) {
            previewContainer.innerHTML = `
                <div class="text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl p-4 text-center">
                    Isi kuantitas pada gudang di sebelah kiri untuk melihat pembagian DO otomatis.
                </div>
            `;
            document.getElementById('btnSubmitDo').innerText = 'Buat DO';
        } else {
            let html = '';
            let doIndex = 1;
            whKeys.forEach(whId => {
                const wh = warehouseSummary[whId];
                const itemLines = Object.keys(wh.items).map(pName => `${pName} · ${wh.items[pName]} pcs`).join('<br>');
                const suffix = whKeys.length > 1 ? `-0${doIndex++}` : '';

                html += `
                    <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/50 flex flex-col gap-1">
                        <div class="font-bold text-xs sm:text-sm text-gray-900 flex justify-between items-center">
                            <span>DO${suffix} · ${wh.name}</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                            ${itemLines}
                        </div>
                    </div>
                `;
            });
            previewContainer.innerHTML = html;
            document.getElementById('btnSubmitDo').innerText = whKeys.length > 1 ? `Buat ${whKeys.length} DO` : 'Buat 1 DO';
        }
    }

    // Jalankan kalkulasi awal jika ada prefill
    window.addEventListener('DOMContentLoaded', updateDoAllocation);
</script>
@endsection
