@extends('layouts.app')

@section('title', 'Pencatatan Pelepasan Aset Tetap – Duta Tunggal ERP')

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
                <a href="{{ route('keuangan.asset.disposal.index') }}" class="hover:underline">Pelepasan Aset</a>
                <span class="text-gray-300">/</span>
                <span class="text-rose-600 font-bold">Pencatatan Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pencatatan Pelepasan & Disposal Aset</h1>
            <p class="text-sm text-gray-500 mt-0.5">Penghapusan nilai buku aset dari neraca, rekonsiliasi kas hasil penjualan, dan penjurnalan laba/rugi pelepasan.</p>
        </div>
        <a
            href="{{ route('keuangan.asset.disposal.index') }}"
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

    <form action="{{ route('keuangan.asset.disposal.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Kolom Kiri: Pilihan Aset & Parameter Pelepasan --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Identitas Aset Tetap
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
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                    >
                        <option value="">-- Pilih Aset Aktif --</option>
                        @foreach ($assets as $asset)
                            <option
                                value="{{ $asset->id }}"
                                data-code="{{ $asset->code }}"
                                data-cabang="{{ $asset->cabang->nama ?? '-' }}"
                                data-cost="{{ (float) $asset->purchase_cost }}"
                                data-accumulated="{{ (float) $asset->accumulated_depreciation }}"
                                data-book-value="{{ (float) $asset->book_value }}"
                                {{ old('asset_id', $selectedAssetId) == $asset->id ? 'selected' : '' }}
                            >
                                {{ $asset->name }} ({{ $asset->code }}) — NBV: {{ \App\Helpers\MoneyHelper::rupiah($asset->book_value) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Card Preview Finansial Aset Terpilih --}}
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Cabang Lokasi:</span>
                        <span id="previewCabang" class="font-bold text-gray-800">-</span>
                    </div>
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Nilai Perolehan Awal:</span>
                        <span id="previewCost" class="font-mono font-semibold text-gray-700">-</span>
                    </div>
                    <div class="flex items-center justify-between text-gray-500">
                        <span>Akumulasi Penyusutan:</span>
                        <span id="previewAccumulated" class="font-mono font-semibold text-amber-700">-</span>
                    </div>
                    <div class="pt-1.5 border-t border-gray-200 flex items-center justify-between font-bold">
                        <span class="text-gray-900">Nilai Buku Bersih (NBV):</span>
                        <span id="previewBookValue" class="font-mono text-emerald-700 text-sm">-</span>
                    </div>
                </div>

                {{-- Tanggal Pelepasan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Pelepasan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="disposal_date"
                        value="{{ old('disposal_date', now()->toDateString()) }}"
                        required
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                    >
                </div>

                {{-- Jenis Pelepasan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Jenis / Alasan Pelepasan <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="disposalTypeSelect"
                        name="disposal_type"
                        required
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                    >
                        <option value="sale" {{ old('disposal_type') === 'sale' ? 'selected' : '' }}>Penjualan Aset (Sale)</option>
                        <option value="scrap" {{ old('disposal_type', 'scrap') === 'scrap' ? 'selected' : '' }}>Scrap / Rusak Total / Afkir</option>
                        <option value="donation" {{ old('disposal_type') === 'donation' ? 'selected' : '' }}>Donasi / Sumbangan</option>
                        <option value="theft" {{ old('disposal_type') === 'theft' ? 'selected' : '' }}>Hilang / Kasus Pencurian</option>
                        <option value="other" {{ old('disposal_type') === 'other' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
            </div>

            {{-- Kolom Kanan: Rekonsiliasi Kas, Laba/Rugi & Berkas --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Rekonsiliasi Nilai & Laba/Rugi
                </h3>

                {{-- Harga Jual (Conditional for Sale) --}}
                <div id="salePriceContainer">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Harga Jual Kas (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold text-xs pointer-events-none">Rp</span>
                        <input
                            type="text"
                            id="salePriceInput"
                            name="sale_price"
                            value="{{ old('sale_price') }}"
                            placeholder="0"
                            class="w-full text-xs font-mono font-semibold pl-10 rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                        >
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Nominal penerimaan kas masuk dari pihak pembeli aset.</span>
                </div>

                {{-- Live Estimasi Laba/Rugi Pelepasan --}}
                <div id="gainLossCard" class="p-4 rounded-xl border border-gray-200 bg-gray-50 text-xs space-y-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Estimasi Hasil Akuntansi Pelepasan</span>
                    <div id="gainLossLabel" class="text-sm font-bold text-gray-800">
                        Kerugian Penghapusan Buku (Loss on Disposal)
                    </div>
                    <div id="gainLossValue" class="text-lg font-mono font-bold text-rose-700">
                        Rp 0
                    </div>
                    <p id="gainLossNote" class="text-[11px] text-gray-500 pt-1">
                        Seluruh sisa nilai buku bersih akan dicatat sebagai beban kerugian pelepasan aset di Laba Rugi.
                    </p>
                </div>

                {{-- Catatan / Keterangan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Catatan / Berita Acara
                    </label>
                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Contoh: Aset dijual lelang karena peremajaan unit produksi / Aset rusak total akibat insiden korsleting..."
                        class="w-full text-xs rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                    >{{ old('notes') }}</textarea>
                </div>

                {{-- Lampiran Berita Acara --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Dokumen Berita Acara / Invoice Penjualan
                    </label>
                    <input
                        type="file"
                        name="disposal_document"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Format: PDF, JPG, PNG (Maksimal 5 MB).</span>
                </div>

                {{-- Box Info Konsekuensi --}}
                <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-100 text-xs text-amber-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-amber-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Perhatian: Dampak Finansial Pelepasan
                    </div>
                    <p class="text-[11px] text-amber-800 leading-relaxed">
                        Setelah disimpan, status aset langsung berubah menjadi <strong>Disposed</strong>, dihentikan dari penyusutan bulanan berikutnya, dan entri jurnal akuntansi seimbang otomatis diterbitkan ke Buku Besar.
                    </p>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('keuangan.asset.disposal.index') }}"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
            >
                Proses Pelepasan & Posting Jurnal
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const assetSelect = document.getElementById('assetSelect');
        const disposalTypeSelect = document.getElementById('disposalTypeSelect');
        const salePriceContainer = document.getElementById('salePriceContainer');
        const salePriceInput = document.getElementById('salePriceInput');
        const previewCabang = document.getElementById('previewCabang');
        const previewCost = document.getElementById('previewCost');
        const previewAccumulated = document.getElementById('previewAccumulated');
        const previewBookValue = document.getElementById('previewBookValue');

        const gainLossLabel = document.getElementById('gainLossLabel');
        const gainLossValue = document.getElementById('gainLossValue');
        const gainLossNote = document.getElementById('gainLossNote');
        const gainLossCard = document.getElementById('gainLossCard');

        function formatRupiah(num) {
            return 'Rp ' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function calculateDisposal() {
            const selected = assetSelect.options[assetSelect.selectedIndex];
            const isSale = disposalTypeSelect.value === 'sale';

            salePriceContainer.style.display = isSale ? 'block' : 'none';

            if (!selected || !selected.value) {
                previewCabang.textContent = '-';
                previewCost.textContent = '-';
                previewAccumulated.textContent = '-';
                previewBookValue.textContent = '-';
                gainLossValue.textContent = 'Rp 0';
                return;
            }

            const cost = parseFloat(selected.dataset.cost || 0);
            const accumulated = parseFloat(selected.dataset.accumulated || 0);
            const bookValue = parseFloat(selected.dataset.bookValue || 0);

            previewCabang.textContent = selected.dataset.cabang || '-';
            previewCost.textContent = formatRupiah(cost);
            previewAccumulated.textContent = formatRupiah(accumulated);
            previewBookValue.textContent = formatRupiah(bookValue);

            if (isSale) {
                const rawPrice = salePriceInput.value.replace(/[^\d]/g, '');
                const salePrice = rawPrice ? parseFloat(rawPrice) : 0;
                const gainLoss = salePrice - bookValue;

                if (gainLoss >= 0) {
                    gainLossLabel.textContent = 'Keuntungan Pelepasan (Gain on Disposal)';
                    gainLossValue.textContent = '+' + formatRupiah(gainLoss);
                    gainLossValue.className = 'text-lg font-mono font-bold text-[#15803d]';
                    gainLossCard.className = 'p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-xs space-y-1';
                    gainLossNote.textContent = 'Harga jual melampaui nilai buku bersih. Keuntungan akan dicatat ke akun Pendapatan Pelepasan Aset.';
                } else {
                    gainLossLabel.textContent = 'Kerugian Pelepasan (Loss on Disposal)';
                    gainLossValue.textContent = '-' + formatRupiah(Math.abs(gainLoss));
                    gainLossValue.className = 'text-lg font-mono font-bold text-rose-700';
                    gainLossCard.className = 'p-4 rounded-xl border border-rose-200 bg-rose-50 text-xs space-y-1';
                    gainLossNote.textContent = 'Harga jual di bawah nilai buku bersih. Selisih dicatat sebagai beban kerugian pelepasan aset.';
                }
            } else {
                gainLossLabel.textContent = 'Kerugian Penghapusan Buku Penuh (Scrap Loss)';
                gainLossValue.textContent = '-' + formatRupiah(bookValue);
                gainLossValue.className = 'text-lg font-mono font-bold text-rose-700';
                gainLossCard.className = 'p-4 rounded-xl border border-rose-200 bg-rose-50 text-xs space-y-1';
                gainLossNote.textContent = 'Aset dihapuskan tanpa penerimaan kas. Seluruh sisa nilai buku dicatat sebagai Beban Kerugian Pelepasan Aset.';
            }
        }

        assetSelect.addEventListener('change', calculateDisposal);
        disposalTypeSelect.addEventListener('change', calculateDisposal);
        salePriceInput.addEventListener('input', function () {
            let val = this.value.replace(/[^\d]/g, '');
            if (val) {
                this.value = parseInt(val, 10).toLocaleString('id-ID');
            } else {
                this.value = '';
            }
            calculateDisposal();
        });

        calculateDisposal();
    });
</script>
@endsection
