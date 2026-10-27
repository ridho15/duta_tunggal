@extends('layouts.app', ['title' => 'Checker – ' . $deliveryOrder->do_number])

@section('content')
@php
    $primarySO = $deliveryOrder->salesOrders->first();
    $customer = $primarySO?->customer;
    $totalCount = $deliveryOrder->deliveryOrderItem->count();
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
                    $isItemChecked = in_array($item->status, ['confirmed', 'sent', 'received'], true);
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
