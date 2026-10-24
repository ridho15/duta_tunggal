@extends('layouts.app')

@section('title', 'Ajukan Mutasi Aset Antar Cabang – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.transfer.index') }}" class="hover:underline">Mutasi Antar Cabang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pengajuan Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pengajuan Mutasi Transfer Aset</h1>
            <p class="text-sm text-gray-500 mt-0.5">Ajukan pemindahan lokasi fisik aset ke cabang penerima untuk operasional bisnis.</p>
        </div>
        <a
            href="{{ route('keuangan.asset.transfer.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- Error Flash --}}
    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-800 text-sm">
            <div class="flex items-center gap-2 font-bold mb-1">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat beberapa kesalahan input:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('keuangan.asset.transfer.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Kolom Kiri: Pemilihan Aset & Cabang Tujuan --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Aset & Rute Mutasi
                </h3>

                {{-- Pilih Aset --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Pilih Aset Tetap <span class="text-rose-500">*</span>
                    </label>
                    <select data-searchable
                        id="assetSelect"
                        name="asset_id"
                        required
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200"
                    >
                        <option value="">-- Pilih Aset Aktif --</option>
                        @foreach ($assets as $asset)
                            <option
                                value="{{ $asset->id }}"
                                data-code="{{ $asset->code }}"
                                data-cabang-id="{{ $asset->cabang_id }}"
                                data-cabang-name="{{ $asset->cabang->nama ?? '-' }}"
                                data-cost="{{ \App\Helpers\MoneyHelper::rupiah($asset->purchase_cost) }}"
                                data-book-value="{{ \App\Helpers\MoneyHelper::rupiah($asset->book_value) }}"
                                {{ old('asset_id', $selectedAssetId) == $asset->id ? 'selected' : '' }}
                            >
                                {{ $asset->name }} ({{ $asset->code }}) — {{ $asset->cabang->nama ?? 'Semua Cabang' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Info Preview Aset Terpilih --}}
                <div id="assetPreviewCard" class="bg-gray-50 p-4 rounded-xl border border-gray-100 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Cabang Asal Saat Ini:</span>
                        <span id="previewCabang" class="font-bold text-gray-800">-</span>
                    </div>
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Nilai Perolehan:</span>
                        <span id="previewCost" class="font-mono font-semibold text-gray-700">-</span>
                    </div>
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Nilai Buku Bersih (NBV):</span>
                        <span id="previewBookValue" class="font-mono font-bold text-emerald-700">-</span>
                    </div>
                </div>

                {{-- Cabang Tujuan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Cabang Tujuan Mutasi <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="to_cabang_id" :options="collect($cabangs)->map(fn ($cabang) => ['id' => $cabang->id, 'label' => $cabang->nama . ' (' . $cabang->kode . ')'])->values()" :value="old('to_cabang_id')" placeholder="-- Pilih Cabang Penerima --" :required="true" size="sm" />
                    <span class="text-[11px] text-gray-400 mt-1 block">Pastikan cabang tujuan berbeda dengan cabang asal aset.</span>
                </div>

                {{-- Tanggal Mutasi --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Rencana Mutasi <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="transfer_date"
                        value="{{ old('transfer_date', now()->toDateString()) }}"
                        required
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200"
                    >
                </div>
            </div>

            {{-- Kolom Kanan: Alasan & Lampiran Dokumen --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Keterangan & Dokumen Pendukung
                </h3>

                {{-- Alasan Mutasi --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Alasan / Justifikasi Pemindahan <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        name="reason"
                        rows="4"
                        required
                        placeholder="Contoh: Pemenuhan kebutuhan inventaris mesin di Cabang Surabaya untuk peningkatan kapasitas operasional..."
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200"
                    >{{ old('reason') }}</textarea>
                    <span class="text-[11px] text-gray-400 mt-1 block">Minimal 5 karakter penjelasan.</span>
                </div>

                {{-- Dokumen Lampiran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Lampiran Dokumen (Surat Jalan / Berita Acara / Memo)
                    </label>
                    <input
                        type="file"
                        name="transfer_document"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-[#15803d] hover:file:bg-emerald-100"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Format: PDF, JPG, PNG (Maksimal 5 MB).</span>
                </div>

                {{-- Panduan Alur Mutasi --}}
                <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-100 text-xs text-emerald-900 space-y-1.5">
                    <div class="font-bold flex items-center gap-1.5 text-[#15803d]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Alur Persetujuan Mutasi Aset
                    </div>
                    <ol class="list-decimal list-inside space-y-1 text-emerald-800 text-[11px]">
                        <li>Pengajuan disimpan dengan status <strong>Menunggu Persetujuan (Pending)</strong>.</li>
                        <li>Manajer / Pimpinan Cabang memverifikasi dan menyetujui pengajuan (<strong>Approved</strong>).</li>
                        <li>Setelah fisik aset sampai di cabang tujuan, lakukan <strong>Complete Transfer</strong> untuk memindahkan lokasi aset secara definitif.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('keuangan.asset.transfer.index') }}"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 bg-[#15803d] hover:bg-emerald-800 text-white text-xs font-semibold rounded-xl shadow-sm transition"
            >
                Simpan & Ajukan Mutasi
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('assetSelect');
        const previewCabang = document.getElementById('previewCabang');
        const previewCost = document.getElementById('previewCost');
        const previewBookValue = document.getElementById('previewBookValue');

        function updatePreview() {
            const selected = select.options[select.selectedIndex];
            if (selected && selected.value) {
                previewCabang.textContent = selected.dataset.cabangName || '-';
                previewCost.textContent = selected.dataset.cost || '-';
                previewBookValue.textContent = selected.dataset.bookValue || '-';
            } else {
                previewCabang.textContent = '-';
                previewCost.textContent = '-';
                previewBookValue.textContent = '-';
            }
        }

        select.addEventListener('change', updatePreview);
        updatePreview();
    });
</script>
@endsection
