@extends('layouts.app')

@section('title', 'Proses Inspeksi Mutu QC ' . $qc->qc_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.qc.index') }}" class="hover:text-gray-700">QC Pabrik</a>
                <span class="text-gray-300">/</span>
                <span class="text-blue-700 font-bold font-mono">{{ $qc->qc_number }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Proses Inspeksi Mutu: {{ $qc->qc_number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Verifikasi hasil fisik produksi barang jadi. Unit yang lolos akan masuk ke stok gudang dan memicu penjurnalan barang jadi.</p>
        </div>
        <a
            href="{{ route('manufaktur.qc.show', $qc) }}"
            class="px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
        >
            Batal
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat kesalahan dalam pengisian:
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $prod = $qc->fromModel;
        $spk = $prod?->manufacturingOrder;
        $plan = $spk?->productionPlan;
        $product = $qc->product ?? $plan?->product;
        $targetQty = (float) ($prod?->quantity_produced ?? $plan?->quantity ?? 0);
    @endphp

    <form method="POST" action="{{ route('manufaktur.qc.process', $qc) }}" id="processQcForm" class="space-y-6">
        @csrf

        {{-- Production Reference Banner --}}
        <div class="bg-blue-50/70 p-5 rounded-2xl border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-[11px] font-bold text-blue-800 uppercase tracking-wider block mb-0.5">Produk Hasil Perakitan</span>
                <h3 class="text-base font-bold text-gray-900">{{ $product?->name }}</h3>
                <p class="text-xs text-gray-500 font-mono mt-0.5">SKU: {{ $product?->sku ?? '-' }} • No. Produksi: {{ $prod?->production_number }} • SPK: {{ $spk?->mo_number }}</p>
            </div>
            <div class="text-left sm:text-right bg-white/80 px-4 py-2 rounded-xl border border-blue-200/60">
                <span class="text-[10.5px] text-gray-500 block">Total Produksi Diinspeksi</span>
                <span class="text-xl font-bold font-mono text-blue-900" id="targetQtyDisplay">
                    {{ number_format($targetQty, 2, ',', '.') }}
                </span>
                <span class="text-xs font-medium text-gray-600">{{ $product?->uom?->name ?? 'Pcs' }}</span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                1. Hasil Pemeriksaan Mutu (Passed vs Rejected)
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-emerald-800 mb-1">
                        Kuantitas Lolos Standar Mutu (Passed) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="passed_quantity"
                            id="passed_quantity"
                            value="{{ old('passed_quantity', (float)($qc->passed_quantity ?? $targetQty)) }}"
                            required
                            class="w-full px-3 py-2.5 bg-emerald-50/40 border border-emerald-300 rounded-lg text-base font-mono font-bold text-emerald-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-emerald-700 font-bold">
                            {{ $product?->uom?->name ?? 'Pcs' }}
                        </div>
                    </div>
                    <p class="text-[11px] text-emerald-700 mt-1">Unit ini otomatis menambah persediaan barang jadi di gudang.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-rose-800 mb-1">
                        Kuantitas Ditolak / Rusak (Rejected) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="rejected_quantity"
                            id="rejected_quantity"
                            value="{{ old('rejected_quantity', (float)($qc->rejected_quantity ?? 0)) }}"
                            required
                            class="w-full px-3 py-2.5 bg-rose-50/40 border border-rose-300 rounded-lg text-base font-mono font-bold text-rose-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-rose-700 font-bold">
                            {{ $product?->uom?->name ?? 'Pcs' }}
                        </div>
                    </div>
                    <p class="text-[11px] text-rose-700 mt-1">Unit cacat tidak menambah stok persediaan siap jual.</p>
                </div>

                <div class="sm:col-span-2" id="rejectReasonBox">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alasan Penolakan / Kerusakan (Reason Reject) <span class="text-rose-500" id="rejectAsterisk">*</span>
                    </label>
                    <textarea
                        name="reason_reject"
                        id="reason_reject"
                        rows="2"
                        placeholder="Contoh: Terjadi keretakan fitting bodi, toleransi ukuran melenceng, dll..."
                        class="w-full border border-gray-300 py-2.5 px-3 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >{{ old('reason_reject', $qc->reason_reject) }}</textarea>
                </div>
            </div>

            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2 pt-2">
                2. Lokasi Gudang Masuk & Petugas
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Gudang Penyimpanan Barang Jadi <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select
                        name="warehouse_id"
                        id="warehouse_id"
                        :options="$warehouses->map(fn($w) => ['id' => $w->id, 'label' => '('.$w->kode.') '.$w->name])->values()"
                        :value="old('warehouse_id', $qc->warehouse_id ?? $plan?->warehouse_id)"
                        placeholder="Pilih Gudang Penyimpanan"
                        :required="true"
                        size="sm"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Rak Penyimpanan (Opsional)
                    </label>
                    <select data-searchable
                        name="rak_id"
                        id="rak_id"
                        class="w-full border border-gray-300 py-2.5 px-3 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                        <option value="">-- Pilih Rak (Bila Ada) --</option>
                        @foreach ($raks as $rak)
                            <option value="{{ $rak->id }}" {{ old('rak_id', $qc->rak_id) == $rak->id ? 'selected' : '' }}>
                                ({{ $rak->code }}) {{ $rak->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Petugas Inspeksi (Inspected By) <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="inspected_by" :options="collect($inspectors)->map(fn ($inspector) => ['id' => $inspector->id, 'label' => $inspector->name . ' (' . $inspector->email . ')'])->values()" :value="(old('inspected_by', $qc->inspected_by ?? auth()->id())) ?: collect($inspectors)->first()?->id" :required="true" size="sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Catatan Tambahan (Notes)
                    </label>
                    <input
                        type="text"
                        name="notes"
                        value="{{ old('notes', $qc->notes) }}"
                        placeholder="Catatan inspeksi QC..."
                        class="w-full border border-gray-300 py-2.5 px-3 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a
                    href="{{ route('manufaktur.qc.show', $qc) }}"
                    class="px-4 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Selesaikan & Simpan QC
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passedInput = document.getElementById('passed_quantity');
    const rejectedInput = document.getElementById('rejected_quantity');
    const whSelect = document.getElementById('warehouse_id');
    const rakSelect = document.getElementById('rak_id');
    const targetQty = {{ $targetQty }};

    function validateQuantities() {
        const passed = parseFloat(passedInput.value) || 0;
        const rejected = parseFloat(rejectedInput.value) || 0;

        if (targetQty > 0 && (passed + rejected) > targetQty) {
            // Adjust rejected to stay within bounds
            if (passed <= targetQty) {
                rejectedInput.value = (targetQty - passed).toFixed(2);
            }
        }
    }

    passedInput.addEventListener('input', validateQuantities);
    rejectedInput.addEventListener('input', validateQuantities);

    whSelect.addEventListener('change', function () {
        const whId = whSelect.value;
        if (!whId) {
            rakSelect.innerHTML = '<option value="">-- Pilih Rak (Bila Ada) --</option>';
            return;
        }

        fetch(`{{ route('manufaktur.qc.raks') }}?warehouse_id=${whId}`)
            .then(res => res.json())
            .then(data => {
                rakSelect.innerHTML = '<option value="">-- Pilih Rak (Bila Ada) --</option>';
                data.forEach(r => {
                    const option = document.createElement('option');
                    option.value = r.id;
                    option.textContent = r.label;
                    rakSelect.appendChild(option);
                });
            })
            .catch(() => {});
    });
});
</script>
@endsection
