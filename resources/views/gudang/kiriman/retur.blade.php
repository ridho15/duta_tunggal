@extends('layouts.app', ['title' => 'Retur DO – ' . $deliveryOrder->do_number])

@section('content')
<div class="max-w-md mx-auto space-y-4">

    {{-- Top Bar --}}
    <div class="flex items-center justify-between pb-2 border-b border-gray-200">
        <a href="{{ route('gudang.kiriman.check', $deliveryOrder->id) }}" class="text-xs font-bold text-gray-700 hover:text-gray-900 flex items-center gap-1">
            <span>‹</span> <span>{{ $deliveryOrder->do_number }}</span>
        </a>
        <span class="text-[9px] font-bold text-[#15803d] border border-[#86efac] bg-[#f0fdf4] rounded px-1.5 py-0.5">
            CHECKER
        </span>
    </div>

    {{-- Retur Badge & Title --}}
    <div class="space-y-1">
        <div class="flex items-center gap-2">
            <span class="inline-flex text-[11px] font-bold tracking-wider text-amber-900 bg-amber-50 border border-amber-300 rounded-md px-2 py-0.5 uppercase">
                RETUR DO · AKAN JADI {{ $deliveryOrder->do_number }}R
            </span>
            <span class="text-xs text-gray-400 font-medium">Proses Retur / Selisih DO</span>
        </div>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight mt-1">Catat barang kurang / rusak</h1>
        <p class="text-xs text-gray-500">
            Isi hanya barang yang tidak sesuai. Sisanya dianggap sesuai dan akan dibuatkan DO pengganti <span class="font-bold text-gray-800">{{ $deliveryOrder->do_number }}C</span>.
        </p>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ route('gudang.kiriman.submitRetur', $deliveryOrder->id) }}" class="space-y-4">
        @csrf

        <div class="space-y-3">
            @foreach($deliveryOrder->deliveryOrderItem as $item)
                @php $maxQty = (int)$item->quantity; @endphp
                <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="font-bold text-sm text-gray-900 block">{{ $item->product->name ?? 'Produk' }}</span>
                            <span class="text-[11px] text-gray-400 font-mono">{{ $item->product->sku ?? '-' }}</span>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-700 rounded-md">
                            DO: {{ $maxQty }} pcs
                        </span>
                    </div>

                    {{-- Kurang & Reject Counters --}}
                    <div class="grid grid-cols-2 gap-3 text-xs font-semibold text-gray-700">
                        <div>
                            <span class="block text-gray-500 mb-1">Kurang</span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onclick="adjustQty('kurang', '{{ $item->id }}', -1, {{ $maxQty }})"
                                    class="w-10 h-10 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 text-lg font-bold flex items-center justify-center hover:bg-gray-100 active:scale-95"
                                >-</button>
                                <input
                                    type="number"
                                    name="items[{{ $item->id }}][kurang]"
                                    id="kurang-{{ $item->id }}"
                                    value="0"
                                    min="0"
                                    max="{{ $maxQty }}"
                                    readonly
                                    class="w-full h-10 border-2 border-amber-500 rounded-xl text-center text-base font-black text-gray-900 bg-white"
                                >
                                <button
                                    type="button"
                                    onclick="adjustQty('kurang', '{{ $item->id }}', 1, {{ $maxQty }})"
                                    class="w-10 h-10 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 text-lg font-bold flex items-center justify-center hover:bg-gray-100 active:scale-95"
                                >+</button>
                            </div>
                        </div>

                        <div>
                            <span class="block text-gray-500 mb-1">Rusak / reject</span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onclick="adjustQty('reject', '{{ $item->id }}', -1, {{ $maxQty }})"
                                    class="w-10 h-10 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 text-lg font-bold flex items-center justify-center hover:bg-gray-100 active:scale-95"
                                >-</button>
                                <input
                                    type="number"
                                    name="items[{{ $item->id }}][reject]"
                                    id="reject-{{ $item->id }}"
                                    value="0"
                                    min="0"
                                    max="{{ $maxQty }}"
                                    readonly
                                    class="w-full h-10 border border-gray-300 rounded-xl text-center text-base font-black text-gray-900 bg-white"
                                >
                                <button
                                    type="button"
                                    onclick="adjustQty('reject', '{{ $item->id }}', 1, {{ $maxQty }})"
                                    class="w-10 h-10 rounded-xl border border-gray-300 bg-gray-50 text-gray-700 text-lg font-bold flex items-center justify-center hover:bg-gray-100 active:scale-95"
                                >+</button>
                            </div>
                        </div>
                    </div>

                    {{-- Diterima Summary Bar --}}
                    <div class="pt-1 flex items-center justify-between text-xs">
                        <span class="text-gray-400">Diterima sesuai fisik:</span>
                        <span id="diterima-{{ $item->id }}" class="font-extrabold text-[#15803d]">
                            {{ $maxQty }} pcs
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Info Box --}}
        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-2xl text-xs text-blue-900 space-y-1">
            <span class="font-bold block">Alur Otomatis ERP:</span>
            <p class="text-[11px] leading-relaxed text-blue-800">
                Setelah disetujui: kuantitas kurang dikembalikan ke stok bebas gudang asal. Barang reject dialokasikan ke lokasi karantina. Dokumen DO pengganti <span class="font-bold">{{ $deliveryOrder->do_number }}C</span> otomatis diterbitkan dan siap dimasukkan ke Surat Jalan.
            </p>
        </div>

        <button
            type="submit"
            class="w-full min-h-[52px] bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-base rounded-2xl shadow-sm transition flex items-center justify-center gap-2"
        >
            <span>Simpan & ajukan retur</span>
        </button>
    </form>

</div>

@push('scripts')
<script>
    function adjustQty(type, itemId, delta, maxQty) {
        const kurangEl = document.getElementById(`kurang-${itemId}`);
        const rejectEl = document.getElementById(`reject-${itemId}`);
        const targetEl = type === 'kurang' ? kurangEl : rejectEl;
        const otherEl = type === 'kurang' ? rejectEl : kurangEl;

        let val = parseInt(targetEl.value) || 0;
        let otherVal = parseInt(otherEl.value) || 0;

        let newVal = val + delta;
        if (newVal < 0) newVal = 0;
        if (newVal + otherVal > maxQty) {
            newVal = maxQty - otherVal;
        }

        targetEl.value = newVal;

        const diterimaEl = document.getElementById(`diterima-${itemId}`);
        const diterimaQty = maxQty - (parseInt(kurangEl.value) || 0) - (parseInt(rejectEl.value) || 0);
        diterimaEl.innerText = `${diterimaQty} pcs`;
    }
</script>
@endpush
@endsection
