@extends('layouts.app', ['title' => 'Checker – ' . $deliveryOrder->do_number])

@section('content')
@php
    $primarySO = $deliveryOrder->salesOrders->first();
    $customer = $primarySO?->customer;
    $totalCount = $deliveryOrder->deliveryOrderItem->count();
    $canEditQty = in_array($deliveryOrder->status, ['approved', 'confirmed'], true) && ! $deliveryOrder->hasIssuedSuratJalan();
@endphp

<div class="max-w-md mx-auto space-y-4">

    {{-- Top Bar Navigation --}}
    <div class="flex items-center justify-between pb-2 border-b border-gray-200">
        <a href="{{ route('gudang.kiriman.index') }}" class="text-xs font-bold text-gray-700 hover:text-gray-900 flex items-center gap-1">
            <span>‹</span> <span>Kiriman</span>
        </a>
        <span class="text-[9px] font-bold text-[#15803d] border border-[#86efac] bg-[#f0fdf4] rounded px-1.5 py-0.5">
            CHECKER
        </span>
    </div>

    @if(session('error'))
        <div class="p-3.5 bg-amber-50 border border-amber-300 rounded-2xl text-xs text-amber-900 font-semibold flex items-start gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Warehouse Badge & DO Info --}}
    <div class="space-y-1">
        <span class="inline-flex text-[11px] font-bold tracking-wider text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded-md px-2 py-0.5 uppercase">
            {{ $deliveryOrder->warehouse->name ?? 'GUDANG' }}
        </span>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight mt-1">{{ $deliveryOrder->do_number }}</h1>
        <p class="text-xs text-gray-500">
            untuk <span class="font-bold text-gray-800">{{ $customer->name ?? 'Customer' }}</span> · ambil barang, cek, lalu centang
        </p>
    </div>

    {{-- Checklist Form --}}
    <form method="POST" action="{{ route('gudang.kiriman.submitCheck', $deliveryOrder->id) }}" id="checkForm" class="space-y-3">
        @csrf

        <div class="space-y-2.5">
            @foreach($deliveryOrder->deliveryOrderItem as $index => $item)
                @php
                    $isItemChecked = $item->itemCheck !== null;
                @endphp
                <label
                    class="flex items-center gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none {{ $isItemChecked ? 'bg-[#f0fdf4] border-[#86efac]' : 'bg-white border-gray-200' }} hover:border-[#15803d]/40"
                    id="label-item-{{ $item->id }}"
                >
                    <input
                        type="checkbox"
                        name="checked_items[]"
                        value="{{ $item->id }}"
                        {{ $isItemChecked ? 'checked' : '' }}
                        onchange="toggleItemCheck(this, '{{ $item->id }}')"
                        class="checker-box w-6 h-6 rounded-lg text-[#15803d] focus:ring-[#15803d] border-gray-300 transition"
                    >
                    <div class="flex-1 min-w-0">
                        <span class="block text-sm font-bold text-gray-900 truncate">
                            {{ $item->product->name ?? 'Produk' }}
                        </span>
                        <span class="block text-[11px] text-gray-400 font-mono">
                            {{ $item->product->sku ?? '-' }}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-black text-gray-900 leading-none">
                            {{ (int)$item->quantity }}
                        </span>
                        <span class="text-xs font-semibold text-gray-500 block">
                            {{ $item->product->uom->abbreviation ?? 'pcs' }}
                        </span>
                    </div>
                </label>
            @endforeach
        </div>

        {{-- Dynamic Progress Text --}}
        <div class="text-xs text-gray-500 text-center font-medium pt-1" id="checkedStatusText">
            0 dari {{ $totalCount }} barang sudah dicek
        </div>

        {{-- Actions --}}
        <div class="pt-4 space-y-2.5">
            <button
                type="submit"
                id="submitKirimBtn"
                disabled
                class="w-full min-h-[52px] font-bold text-base rounded-2xl transition flex items-center justify-center gap-2 shadow-sm disabled:border-dashed disabled:border-gray-300 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed bg-[#15803d] hover:bg-[#14532d] text-white active:scale-[0.98]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Semua sesuai – KIRIM</span>
            </button>

            <p class="text-[11px] text-gray-400 text-center" id="hintText">
                Centang semua barang dulu untuk mengirim
            </p>

            <a
                href="{{ route('gudang.kiriman.retur', $deliveryOrder->id) }}"
                class="w-full min-h-[48px] rounded-2xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs flex items-center justify-center transition shadow-sm"
            >
                Ada kurang / rusak
            </a>
        </div>
    </form>

    {{-- Checker Edit Qty: form terpisah dari checklist (form tidak boleh bersarang) --}}
    @if($canEditQty)
        <details class="bg-white border border-gray-200 rounded-2xl" @if($errors->any()) open @endif>
            <summary class="min-h-[48px] flex items-center px-4 py-3 text-xs font-bold text-gray-800 rounded-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#15803d]">
                Barang kurang di rak? Ubah qty
            </summary>
            <form method="POST" action="{{ route('gudang.kiriman.editQty', $deliveryOrder->id) }}" class="px-4 pb-4 space-y-3">
                @csrf
                <p class="text-[11px] text-gray-500">Qty hanya bisa diturunkan. Setelah disimpan, centang ulang barangnya.</p>

                @foreach($deliveryOrder->deliveryOrderItem as $item)
                    <div class="flex items-center justify-between gap-3">
                        <label for="qty-{{ $item->id }}" class="text-xs font-semibold text-gray-800 min-w-0 truncate">
                            {{ $item->product->name ?? 'Produk' }}
                            <span class="block text-[11px] font-normal text-gray-400">sekarang {{ (float) $item->quantity + 0 }} {{ $item->product->uom->abbreviation ?? 'pcs' }}</span>
                        </label>
                        <input
                            id="qty-{{ $item->id }}"
                            type="number"
                            inputmode="decimal"
                            name="quantities[{{ $item->id }}]"
                            value="{{ old('quantities.'.$item->id, (float) $item->quantity + 0) }}"
                            min="0"
                            max="{{ (float) $item->quantity + 0 }}"
                            step="any"
                            required
                            class="w-24 h-11 text-right border border-gray-300 rounded-xl px-3 text-sm font-bold text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                    </div>
                @endforeach

                <div>
                    <label for="edit-qty-notes" class="block text-xs font-semibold text-gray-800 mb-1">Alasan perubahan</label>
                    <textarea
                        id="edit-qty-notes"
                        name="notes"
                        rows="2"
                        maxlength="500"
                        required
                        placeholder="Contoh: stok rak tinggal 8, sisanya rusak"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-[11px] font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('quantities.*') <p class="mt-1 text-[11px] font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full min-h-[48px] rounded-2xl bg-gray-900 hover:bg-black text-white font-bold text-xs transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900">
                    Simpan qty baru
                </button>
            </form>
        </details>
    @endif

</div>

@push('scripts')
<script>
    const totalItems = {{ $totalCount }};

    function toggleItemCheck(cb, itemId) {
        const label = document.getElementById(`label-item-${itemId}`);
        if (cb.checked) {
            label.className = 'flex items-center gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none bg-[#f0fdf4] border-[#86efac]';
        } else {
            label.className = 'flex items-center gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none bg-white border-gray-200';
        }
        recalcProgress();
    }

    function recalcProgress() {
        const checkedCount = document.querySelectorAll('.checker-box:checked').length;
        const statusText = document.getElementById('checkedStatusText');
        const submitBtn = document.getElementById('submitKirimBtn');
        const hintText = document.getElementById('hintText');

        statusText.innerText = `${checkedCount} dari ${totalItems} barang sudah dicek`;

        if (checkedCount === totalItems && totalItems > 0) {
            submitBtn.disabled = false;
            hintText.innerText = 'Semua barang telah terverifikasi sesuai fisik gudang.';
            hintText.className = 'text-[11px] text-[#15803d] text-center font-semibold';
        } else {
            submitBtn.disabled = true;
            hintText.innerText = 'Centang semua barang dulu untuk mengirim';
            hintText.className = 'text-[11px] text-gray-400 text-center';
        }
    }

    document.addEventListener('DOMContentLoaded', recalcProgress);
</script>
@endpush
@endsection
