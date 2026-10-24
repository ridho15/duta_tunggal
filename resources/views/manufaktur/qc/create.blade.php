@extends('layouts.app')

@section('title', 'Terbitkan QC Pabrik Manual – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.qc.index') }}" class="hover:text-gray-700">QC Pabrik</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Terbitkan Manual</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Terbitkan Quality Control Pabrik</h1>
            <p class="text-sm text-gray-500 mt-0.5">Menerbitkan lembar inspeksi mutu untuk hasil produksi yang belum memiliki QC.</p>
        </div>
        <a
            href="{{ route('manufaktur.qc.index') }}"
            class="px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
        >
            Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat kesalahan input:
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $availableProductionsMap = $availableProductions->keyBy('id')->map(function($prod) {
            $spk = $prod->manufacturingOrder;
            $plan = $spk?->productionPlan;
            return [
                'warehouse_id' => $prod->warehouse_id ?? $plan?->warehouse_id,
                'qty' => (float) ($prod->quantity_produced ?? $plan?->quantity ?? 0),
            ];
        })->all();
    @endphp

    <form method="POST" action="{{ route('manufaktur.qc.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Identitas Dokumen Quality Control
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Dokumen QC <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="qc_number"
                        value="{{ old('qc_number', $defaultNumber) }}"
                        required
                        class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm font-mono font-bold text-gray-800 focus:bg-white focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                    <p class="text-[11px] text-gray-400 mt-1">Format otomatis QC-M-YYYYMMDD-XXXX</p>
                </div>

                <div>
                    <x-searchable-select
                        name="inspected_by"
                        label="Petugas Inspeksi (Inspected By)"
                        :options="$inspectors->map(fn($inspector) => ['id' => $inspector->id, 'label' => $inspector->name, 'subtext' => $inspector->email])->all()"
                        :value="old('inspected_by', auth()->id())"
                        placeholder="-- Pilih Petugas --"
                        :required="true"
                    />
                </div>

                <div class="sm:col-span-2">
                    <x-searchable-select
                        name="from_model_id"
                        id="from_model_id"
                        label="Pilih Dokumen Hasil Produksi"
                        :options="$availableProductions->map(function($prod) {
                            $spk = $prod->manufacturingOrder;
                            $plan = $spk?->productionPlan;
                            $product = $plan?->product;
                            $qty = (float)($prod->quantity_produced ?? $plan?->quantity ?? 0);
                            $uom = $product?->uom?->name ?? 'Pcs';
                            return [
                                'id' => $prod->id,
                                'label' => $prod->production_number . ' — ' . ($product?->name ?? 'Produk'),
                                'subtext' => 'Hasil: ' . number_format($qty, 2) . ' ' . $uom . ' • SPK: ' . ($spk?->mo_number ?? '-'),
                            ];
                        })->all()"
                        :value="old('from_model_id')"
                        placeholder="-- Pilih Dokumen Produksi Selesai --"
                        :required="true"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <x-searchable-select
                        name="warehouse_id"
                        id="warehouse_id"
                        label="Gudang Tujuan Masuk Barang Jadi"
                        :options="$warehouses->map(fn($wh) => ['id' => $wh->id, 'label' => $wh->name, 'subtext' => $wh->kode])->all()"
                        :value="old('warehouse_id')"
                        placeholder="-- Pilih Gudang Tujuan --"
                        :required="true"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Rak Penyimpanan (Opsional)
                    </label>
                    <select
                        name="rak_id"
                        id="rak_id"
                        class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                        <option value="">-- Pilih Rak (Bila Ada) --</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-semibold text-emerald-800 mb-1">
                        Kuantitas Lolos (Passed Quantity) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="passed_quantity"
                        id="passed_quantity"
                        value="{{ old('passed_quantity', 0) }}"
                        required
                        class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-mono font-bold text-emerald-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-rose-800 mb-1">
                        Kuantitas Ditolak (Rejected Quantity) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="rejected_quantity"
                        id="rejected_quantity"
                        value="{{ old('rejected_quantity', 0) }}"
                        required
                        class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-mono font-bold text-rose-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alasan Penolakan / Reject (Wajib jika ada reject)
                    </label>
                    <textarea
                        name="reason_reject"
                        rows="2"
                        placeholder="Contoh: Keretakan bodi, cacat toleransi dimensi, dll..."
                        class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >{{ old('reason_reject') }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Catatan Pemeriksaan (Notes)
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Catatan inspeksi mutu..."
                        class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a
                    href="{{ route('manufaktur.qc.index') }}"
                    class="px-4 py-2.5 border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] cursor-pointer"
                >
                    Terbitkan Dokumen QC
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const AVAILABLE_PRODUCTIONS = @json($availableProductionsMap);

    const fromSelect = document.getElementById('from_model_id');
    const whSelect = document.getElementById('warehouse_id');
    const rakSelect = document.getElementById('rak_id');
    const passedInput = document.getElementById('passed_quantity');

    if (fromSelect) {
        fromSelect.addEventListener('change', function () {
            const prodId = fromSelect.value;
            const data = prodId ? AVAILABLE_PRODUCTIONS[prodId] : null;
            if (data) {
                if (data.warehouse_id && whSelect) {
                    whSelect.value = data.warehouse_id;
                    const alpineData = whSelect.closest('[x-data]')?._x_dataStack?.[0];
                    if (alpineData) {
                        alpineData.selected = String(data.warehouse_id);
                    }
                    loadRaks(data.warehouse_id);
                }
                if (data.qty && (!passedInput.value || parseFloat(passedInput.value) <= 0)) {
                    passedInput.value = data.qty;
                }
            }
        });
    }

    if (whSelect) {
        whSelect.addEventListener('change', function () {
            loadRaks(whSelect.value);
        });
    }

    function loadRaks(whId) {
        if (!whId || !rakSelect) {
            if (rakSelect) rakSelect.innerHTML = '<option value="">-- Pilih Rak (Bila Ada) --</option>';
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
    }

    if (whSelect && whSelect.value) {
        loadRaks(whSelect.value);
    }
});
</script>ipt>
@endsection
