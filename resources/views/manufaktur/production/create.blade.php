@extends('layouts.app')

@section('title', 'Catat Hasil Produksi Baru – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.production.index') }}" class="hover:text-gray-700">Hasil Produksi</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Catat Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Catat Hasil Produksi (Production Output)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Mencatat output barang jadi yang telah selesai dirakit dari Surat Perintah Kerja (SPK).</p>
        </div>
        <a
            href="{{ route('manufaktur.production.index') }}"
            class="px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
        >
            Kembali
        </a>
    </div>

    {{-- Error validation alerts --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat kesalahan pengisian data:
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('manufaktur.production.store') }}" id="productionForm" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Main Form Inputs --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Document Identity Card --}}
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        1. Identitas Dokumen & Referensi SPK
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Nomor Bukti Produksi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="production_number"
                                id="production_number"
                                value="{{ old('production_number', $defaultNumber) }}"
                                required
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-bold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                            <p class="text-[11px] text-gray-400 mt-1">Dihasilkan otomatis berformat PRO-YYYYMMDD-XXXX</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Tanggal Produksi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="production_date"
                                id="production_date"
                                value="{{ old('production_date', now()->toDateString()) }}"
                                required
                                class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs text-gray-800 focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Pilih Surat Perintah Kerja (SPK) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="manufacturing_order_id"
                            id="manufacturing_order_id"
                            required
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs text-gray-800 focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                            <option value="">-- Pilih SPK yang Sedang Berjalan --</option>
                            @foreach ($availableSpk as $spk)
                                @php
                                    $plan = $spk->productionPlan;
                                    $prod = $plan?->product;
                                    $selected = (old('manufacturing_order_id', $selectedSpkId) == $spk->id);
                                @endphp
                                <option
                                    value="{{ $spk->id }}"
                                    data-product-name="{{ $prod?->name }}"
                                    data-product-sku="{{ $prod?->sku }}"
                                    data-product-uom="{{ $prod?->uom?->name ?? 'Pcs' }}"
                                    data-planned-qty="{{ (float) ($plan?->quantity ?? 0) }}"
                                    data-warehouse-name="{{ $plan?->warehouse?->name ?? 'Gudang Utama' }}"
                                    {{ $selected ? 'selected' : '' }}
                                >
                                    {{ $spk->mo_number }} — {{ $prod?->name }} (Rencana: {{ number_format((float)($plan?->quantity ?? 0), 2) }} {{ $prod?->uom?->name ?? 'Pcs' }}) [{{ strtoupper($spk->status) }}]
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Hanya SPK dengan status In Progress yang dapat dicatat hasil akhirnya.</p>
                    </div>
                </div>

                {{-- Output Detail Card --}}
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        2. Rincian Output Produk Jadi
                    </h3>

                    <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-100 space-y-2" id="spkInfoBox">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-emerald-800 font-bold uppercase tracking-wider">Produk Jadi:</span>
                            <span class="text-xs font-mono font-bold text-emerald-900" id="infoSku">-</span>
                        </div>
                        <div class="text-sm font-bold text-gray-900" id="infoProductName">
                            Silahkan pilih SPK terlebih dahulu
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-emerald-900/80 pt-1 border-t border-emerald-200/50">
                            <div>Target Rencana: <span class="font-bold font-mono" id="infoPlannedQty">0</span> <span id="infoUom">Pcs</span></div>
                            <div>Gudang Produksi: <span class="font-bold" id="infoWarehouse">-</span></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Kuantitas Hasil Produksi Selesai <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="quantity_produced"
                                    id="quantity_produced"
                                    value="{{ old('quantity_produced') }}"
                                    required
                                    placeholder="0.00"
                                    class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-mono font-bold text-gray-900 focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                                >
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-gray-400 font-bold" id="inputUom">
                                    Pcs
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Masukkan jumlah fisik barang jadi yang telah selesai dirakit.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Status Saat Disimpan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="status"
                                id="status"
                                required
                                class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-800 focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                                <option value="finished" {{ old('status', 'finished') === 'finished' ? 'selected' : '' }}>
                                    Selesai (Finished) — Otomatis Terbitkan QC
                                </option>
                                <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>
                                    Draft — Simpan Sementara
                                </option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Status Selesai akan langsung membuat dokumen QC untuk inspeksi tim mutu.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Guidance & Confirmation --}}
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Alur Setelah Pencatatan
                    </h3>

                    <div class="space-y-3 text-xs text-gray-600">
                        <div class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center flex-shrink-0 text-[11px]">1</span>
                            <p><strong>Hasil Produksi Selesai:</strong> Menandakan proses perakitan di lantai pabrik telah rampung.</p>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center flex-shrink-0 text-[11px]">2</span>
                            <p><strong>Pemeriksaan Mutu (QC):</strong> Sistem otomatis membuat lembar QC. Petugas mutu menginspeksi barang lolos vs reject.</p>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 font-bold flex items-center justify-center flex-shrink-0 text-[11px]">3</span>
                            <p><strong>Penjurnalan Barang Jadi:</strong> Saat QC disetujui, stok fisik produk jadi bertambah dan nilai BDP dialihkan ke Persediaan Barang Jadi.</p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button
                            type="submit"
                            class="w-full py-3 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan Hasil Produksi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const spkSelect = document.getElementById('manufacturing_order_id');
    const infoProductName = document.getElementById('infoProductName');
    const infoSku = document.getElementById('infoSku');
    const infoPlannedQty = document.getElementById('infoPlannedQty');
    const infoUom = document.getElementById('infoUom');
    const inputUom = document.getElementById('inputUom');
    const infoWarehouse = document.getElementById('infoWarehouse');
    const qtyInput = document.getElementById('quantity_produced');

    function updateSpkDisplay() {
        const selected = spkSelect.options[spkSelect.selectedIndex];
        if (!selected || !selected.value) {
            infoProductName.textContent = 'Silahkan pilih SPK terlebih dahulu';
            infoSku.textContent = '-';
            infoPlannedQty.textContent = '0';
            infoUom.textContent = 'Pcs';
            inputUom.textContent = 'Pcs';
            infoWarehouse.textContent = '-';
            return;
        }

        const name = selected.getAttribute('data-product-name') || '-';
        const sku = selected.getAttribute('data-product-sku') || '-';
        const uom = selected.getAttribute('data-product-uom') || 'Pcs';
        const planned = parseFloat(selected.getAttribute('data-planned-qty') || '0');
        const warehouse = selected.getAttribute('data-warehouse-name') || '-';

        infoProductName.textContent = name;
        infoSku.textContent = 'SKU: ' + sku;
        infoPlannedQty.textContent = planned.toLocaleString('id-ID');
        infoUom.textContent = uom;
        inputUom.textContent = uom;
        infoWarehouse.textContent = warehouse;

        if (!qtyInput.value || parseFloat(qtyInput.value) <= 0) {
            qtyInput.value = planned;
        }
    }

    spkSelect.addEventListener('change', updateSpkDisplay);
    if (spkSelect.value) {
        updateSpkDisplay();
    }
});
</script>
@endsection
