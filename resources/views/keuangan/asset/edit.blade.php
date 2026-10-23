@extends('layouts.app')

@section('title', "Ubah Aset {$asset->code} – Duta Tunggal ERP")

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.show', $asset) }}" class="hover:underline">{{ $asset->code }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Ubah</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Data Aset Tetap</h1>
            <p class="text-sm text-gray-500 mt-0.5">Memperbarui informasi inventaris, umur manfaat, atau parameter akuntansi aset.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.asset.show', $asset) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Detail
            </a>
        </div>
    </div>

    {{-- Alert jika ada jurnal akuntansi --}}
    @if ($hasJournals)
        <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs flex items-center gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <span class="font-bold">Aset ini sudah memiliki Jurnal Akuisisi di Buku Besar.</span>
                <span class="block text-blue-700 mt-0.5">Jika Anda mengubah Biaya Perolehan, entri jurnal buku besar terkait akan disesuaikan secara otomatis.</span>
            </div>
        </div>
    @endif

    {{-- Error Alerts --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mohon periksa kesalahan input berikut:</span>
            </div>
            <ul class="list-disc list-inside pl-5 text-xs text-rose-700 space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Edit --}}
    <form action="{{ route('keuangan.asset.update', $asset) }}" method="POST" id="assetEditForm">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- KOLOM KIRI: Identitas & Informasi Umum --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-bold text-gray-900">1. Identitas Aset</h2>
                    <p class="text-xs text-gray-500">Informasi fisik barang dan penempatan lokasi cabang.</p>
                </div>

                {{-- Kode Aset --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Kode Aset <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        value="{{ old('code', $asset->code) }}"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-mono font-bold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                </div>

                {{-- Nama Barang / Aset --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Nama Barang / Aset <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $asset->name) }}"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Lokasi Cabang <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="cabang_id"
                        id="cabang_id"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ old('cabang_id', $asset->cabang_id) == $cb->id ? 'selected' : '' }}>
                                {{ $cb->kode }} - {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Status Aset <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="status"
                        id="status"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                        <option value="active" {{ old('status', $asset->status) === 'active' || old('status', $asset->status) === 'posted' ? 'selected' : '' }}>Aktif (Dalam Masa Pakai)</option>
                        <option value="fully_depreciated" {{ old('status', $asset->status) === 'fully_depreciated' ? 'selected' : '' }}>Disusutkan Penuh</option>
                        <option value="disposed" {{ old('status', $asset->status) === 'disposed' ? 'selected' : '' }}>Pelepasan / Dihapus</option>
                    </select>
                </div>

                {{-- Tanggal Beli & Tanggal Pakai --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Tanggal Perolehan / Beli <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="purchase_date"
                            id="purchase_date"
                            value="{{ old('purchase_date', $asset->purchase_date?->format('Y-m-d')) }}"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Tanggal Mulai Pakai <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="usage_date"
                            id="usage_date"
                            value="{{ old('usage_date', $asset->usage_date?->format('Y-m-d')) }}"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                    </div>
                </div>

                {{-- Catatan / Keterangan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Spesifikasi / Catatan Tambahan
                    </label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >{{ old('notes', $asset->notes) }}</textarea>
                </div>
            </div>

            {{-- KOLOM KANAN: Parameter Finansial & Akuntansi --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-bold text-gray-900">2. Parameter Nilai & Akuntansi</h2>
                    <p class="text-xs text-gray-500">Biaya perolehan, masa manfaat, metode depresiasi, dan akun COA.</p>
                </div>

                {{-- Biaya Perolehan Aset --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Biaya Perolehan Aset (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-sm font-bold text-gray-500">Rp</span>
                        <input
                            type="number"
                            step="any"
                            name="purchase_cost"
                            id="purchase_cost"
                            value="{{ old('purchase_cost', $asset->purchase_cost) }}"
                            required
                            class="w-full pl-11 pr-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base font-bold text-gray-900 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                    </div>
                </div>

                {{-- Umur Manfaat & Metode Penyusutan --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Umur Manfaat (Tahun) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            name="useful_life_years"
                            id="useful_life_years"
                            value="{{ old('useful_life_years', $asset->useful_life_years) }}"
                            min="1"
                            max="50"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Metode Penyusutan <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="depreciation_method"
                            id="depreciation_method"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                            <option value="straight_line" {{ old('depreciation_method', $asset->depreciation_method) === 'straight_line' ? 'selected' : '' }}>Garis Lurus (Straight Line)</option>
                            <option value="declining_balance" {{ old('depreciation_method', $asset->depreciation_method) === 'declining_balance' ? 'selected' : '' }}>Saldo Menurun Ganda (Declining Balance)</option>
                            <option value="sum_of_years_digits" {{ old('depreciation_method', $asset->depreciation_method) === 'sum_of_years_digits' ? 'selected' : '' }}>Jumlah Digit Tahun (Sum of Years)</option>
                            <option value="units_of_production" {{ old('depreciation_method', $asset->depreciation_method) === 'units_of_production' ? 'selected' : '' }}>Unit Produksi</option>
                        </select>
                    </div>
                </div>

                {{-- Nilai Sisa (Salvage Value) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Nilai Sisa / Residu (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-sm font-bold text-gray-500">Rp</span>
                        <input
                            type="number"
                            step="any"
                            name="salvage_value"
                            id="salvage_value"
                            value="{{ old('salvage_value', $asset->salvage_value) }}"
                            required
                            class="w-full pl-11 pr-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                    </div>
                </div>

                {{-- Pemetaan Chart of Accounts (COA) --}}
                <div class="border-t border-gray-100 pt-4 space-y-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Pemetaan Akun Buku Besar (COA)</h3>

                    {{-- Akun Aset Tetap --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Akun Aset Tetap (Debit) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="asset_coa_id"
                            id="asset_coa_id"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                            @foreach ($assetCoas as $coa)
                                <option value="{{ $coa->id }}" {{ old('asset_coa_id', $asset->asset_coa_id) == $coa->id ? 'selected' : '' }}>
                                    {{ $coa->code }} - {{ $coa->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Akun Akumulasi & Beban Penyusutan --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                                Akumulasi Penyusutan (Kredit) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="accumulated_depreciation_coa_id"
                                id="accumulated_depreciation_coa_id"
                                required
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                                @foreach ($accumulatedCoas as $coa)
                                    <option value="{{ $coa->id }}" {{ old('accumulated_depreciation_coa_id', $asset->accumulated_depreciation_coa_id) == $coa->id ? 'selected' : '' }}>
                                        {{ $coa->code }} - {{ $coa->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                                Beban Penyusutan (Debit) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="depreciation_expense_coa_id"
                                id="depreciation_expense_coa_id"
                                required
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                                @foreach ($expenseCoas as $coa)
                                    <option value="{{ $coa->id }}" {{ old('depreciation_expense_coa_id', $asset->depreciation_expense_coa_id) == $coa->id ? 'selected' : '' }}>
                                        {{ $coa->code }} - {{ $coa->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="mt-6 bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">
                Perubahan pada biaya perolehan atau umur manfaat akan menghitung ulang jadwal penyusutan.
            </span>
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('keuangan.asset.show', $asset) }}"
                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
