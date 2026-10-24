@extends('layouts.app')

@section('title', 'Registrasi Aset Tetap Baru – Duta Tunggal ERP')

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
                <span class="text-[#15803d] font-bold">Registrasi Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Registrasi Aset Tetap Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Mendaftarkan unit aset, menetapkan metode penyusutan, dan menghubungkan akun buku besar.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.asset.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Daftar
            </a>
        </div>
    </div>

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

    {{-- Form Registrasi --}}
    <form action="{{ route('keuangan.asset.store') }}" method="POST" id="assetForm">
        @csrf

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
                        value="{{ old('code', $autoCode) }}"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-mono font-bold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        placeholder="AST-0001"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Kode digenerate otomatis, dapat diubah sesuai standar kode inventaris.</span>
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
                        value="{{ old('name') }}"
                        required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        placeholder="Contoh: Mesin Bor Duduk 16mm / Kendaraan Pick-Up Grand Max"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Lokasi Cabang <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->kode . ' - ' . $cb->nama])->values()" :value="old('cabang_id', Auth::user()?->cabang_id)" placeholder="-- Pilih Cabang Penempatan --" :required="true" id="cabang_id" />
                </div>

                {{-- Link ke Master Produk (Opsional) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        Tautkan Master Produk (Opsional)
                    </label>
                    <select data-searchable
                        name="product_id"
                        id="product_id"
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                        <option value="">-- Tidak Ditautkan ke Katalog Produk --</option>
                        @foreach ($products as $prd)
                            <option value="{{ $prd->id }}" data-cost="{{ $prd->cost_price }}" {{ old('product_id') == $prd->id ? 'selected' : '' }}>
                                {{ $prd->sku }} - {{ $prd->name }} (HPP: {{ \App\Helpers\MoneyHelper::rupiah($prd->cost_price) }})
                            </option>
                        @endforeach
                    </select>
                    <span class="text-[11px] text-gray-400 mt-1 block">Jika barang ini dibeli dari Purchase Order atau tercatat di katalog produk.</span>
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
                            value="{{ old('purchase_date', now()->toDateString()) }}"
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
                            value="{{ old('usage_date', now()->toDateString()) }}"
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
                        placeholder="Nomor seri mesin, plat nomor kendaraan, kondisi barang, dll..."
                    >{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- KOLOM KANAN: Finansial, Penyusutan & Akuntansi --}}
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
                            value="{{ old('purchase_cost') }}"
                            required
                            class="w-full pl-11 pr-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base font-bold text-gray-900 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            placeholder="0"
                        >
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Total harga beli + ongkos angkut + biaya instalasi s/d siap beroperasi.</span>
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
                            value="{{ old('useful_life_years', 5) }}"
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
                            <option value="straight_line" {{ old('depreciation_method') === 'straight_line' ? 'selected' : '' }}>Garis Lurus (Straight Line)</option>
                            <option value="declining_balance" {{ old('depreciation_method') === 'declining_balance' ? 'selected' : '' }}>Saldo Menurun Ganda (Declining Balance)</option>
                            <option value="sum_of_years_digits" {{ old('depreciation_method') === 'sum_of_years_digits' ? 'selected' : '' }}>Jumlah Digit Tahun (Sum of Years)</option>
                            <option value="units_of_production" {{ old('depreciation_method') === 'units_of_production' ? 'selected' : '' }}>Unit Produksi</option>
                        </select>
                    </div>
                </div>

                {{-- Nilai Sisa (Salvage Value) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-gray-700 uppercase">
                            Nilai Sisa / Residu (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <button
                            type="button"
                            id="btnAuto5Percent"
                            class="text-[11px] font-bold text-[#15803d] hover:underline"
                        >
                            Hitung 5% Otomatis
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-sm font-bold text-gray-500">Rp</span>
                        <input
                            type="number"
                            step="any"
                            name="salvage_value"
                            id="salvage_value"
                            value="{{ old('salvage_value', 0) }}"
                            required
                            class="w-full pl-11 pr-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            placeholder="0"
                        >
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Nilai sisa aset di akhir masa manfaat (standar 5% dari biaya perolehan).</span>
                </div>

                {{-- Live Estimation Card --}}
                <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-100 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 block">Estimasi Penyusutan Bulanan</span>
                        <span id="monthlyEstimateText" class="text-lg font-extrabold text-emerald-700 font-mono mt-0.5 block">Rp 0,00</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] font-medium text-emerald-700 block">Penyusutan Tahunan</span>
                        <span id="annualEstimateText" class="text-sm font-bold text-emerald-800 font-mono block">Rp 0,00</span>
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
                        <select data-searchable
                            name="asset_coa_id"
                            id="asset_coa_id"
                            required
                            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                        >
                            <option value="">-- Pilih Kategori Akun Aset Tetap --</option>
                            @foreach ($assetCoas as $coa)
                                <option value="{{ $coa->id }}" data-code="{{ $coa->code }}" {{ old('asset_coa_id') == $coa->id ? 'selected' : '' }}>
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
                            <select data-searchable
                                name="accumulated_depreciation_coa_id"
                                id="accumulated_depreciation_coa_id"
                                required
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                                <option value="">-- Akun Akumulasi --</option>
                                @foreach ($accumulatedCoas as $coa)
                                    <option value="{{ $coa->id }}" data-code="{{ $coa->code }}" {{ old('accumulated_depreciation_coa_id') == $coa->id ? 'selected' : '' }}>
                                        {{ $coa->code }} - {{ $coa->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                                Beban Penyusutan (Debit) <span class="text-rose-500">*</span>
                            </label>
                            <select data-searchable
                                name="depreciation_expense_coa_id"
                                id="depreciation_expense_coa_id"
                                required
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                            >
                                <option value="">-- Akun Beban --</option>
                                @foreach ($expenseCoas as $coa)
                                    <option value="{{ $coa->id }}" data-code="{{ $coa->code }}" {{ old('depreciation_expense_coa_id') == $coa->id ? 'selected' : '' }}>
                                        {{ $coa->code }} - {{ $coa->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Akun Sumber Dana / Kredit Jurnal Akuisisi --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            Sumber Pendanaan Akuisisi (Akun Kredit Jurnal)
                        </label>
                        <x-searchable-select name="credit_coa_id" :options="collect($fundingCoas)->map(fn ($coa) => ['id' => $coa->id, 'label' => $coa->code . ' - ' . $coa->name])->values()" :value="old('credit_coa_id')" placeholder="-- Otomatis: Hutang Usaha (AP 2110.01) --" id="credit_coa_id" />
                        <span class="text-[11px] text-gray-400 mt-1 block">Pilih Kas/Bank jika dibayar tunai saat perolehan, atau biarkan kosong untuk Hutang Usaha (AP).</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="mt-6 bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">
                Setelah disimpan, Jurnal Akuisisi Aset akan langsung diposting ke Buku Besar secara otomatis.
            </span>
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('keuangan.asset.index') }}"
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
                    Simpan & Posting Akuisisi
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Script Interaktif Kalkulasi & Auto-Mapping COA --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const costInput = document.getElementById('purchase_cost');
    const lifeInput = document.getElementById('useful_life_years');
    const salvageInput = document.getElementById('salvage_value');
    const methodSelect = document.getElementById('depreciation_method');
    const assetCoaSelect = document.getElementById('asset_coa_id');
    const accumCoaSelect = document.getElementById('accumulated_depreciation_coa_id');
    const expenseCoaSelect = document.getElementById('depreciation_expense_coa_id');
    const productSelect = document.getElementById('product_id');
    const btnAuto5Percent = document.getElementById('btnAuto5Percent');

    const monthlyEstimateText = document.getElementById('monthlyEstimateText');
    const annualEstimateText = document.getElementById('annualEstimateText');

    // 1. Kamus Pemetaan COA Baku
    const coaMapping = {
        '1210.01': { accum: '1220.01', exp: '6311' }, // Peralatan
        '1210.02': { accum: '1220.02', exp: '6312' }, // Perlengkapan
        '1210.03': { accum: '1220.03', exp: '6313' }, // Kendaraan
        '1210.04': { accum: '1220.04', exp: '6314' }, // Bangunan
    };

    function formatRupiah(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function calculateDepreciationLive() {
        const cost = parseFloat(costInput.value) || 0;
        const salvage = parseFloat(salvageInput.value) || 0;
        const years = parseFloat(lifeInput.value) || 0;
        const method = methodSelect.value;

        if (cost <= 0 || years <= 0) {
            monthlyEstimateText.textContent = formatRupiah(0);
            annualEstimateText.textContent = formatRupiah(0);
            return;
        }

        const depreciable = Math.max(0, cost - salvage);
        let annual = 0;

        if (method === 'declining_balance') {
            const rate = (1 / years) * 2;
            annual = Math.min(cost * rate, depreciable);
        } else if (method === 'sum_of_years_digits') {
            const syd = (years * (years + 1)) / 2;
            annual = depreciable * (years / syd);
        } else {
            // Straight line & default
            annual = depreciable / years;
        }

        const monthly = annual / 12;

        monthlyEstimateText.textContent = formatRupiah(monthly);
        annualEstimateText.textContent = formatRupiah(annual);
    }

    function autoCalculateSalvage() {
        const cost = parseFloat(costInput.value) || 0;
        const val = cost * 0.05;
        salvageInput.value = val.toFixed(2);
        calculateDepreciationLive();
    }

    // Auto-select COA saat kategori aset dipilih
    assetCoaSelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const code = selectedOption.getAttribute('data-code');

        if (code && coaMapping[code]) {
            const target = coaMapping[code];

            // Match accumulated COA
            for (let i = 0; i < accumCoaSelect.options.length; i++) {
                if (accumCoaSelect.options[i].getAttribute('data-code') === target.accum) {
                    accumCoaSelect.selectedIndex = i;
                    break;
                }
            }

            // Match expense COA
            for (let i = 0; i < expenseCoaSelect.options.length; i++) {
                if (expenseCoaSelect.options[i].getAttribute('data-code') === target.exp) {
                    expenseCoaSelect.selectedIndex = i;
                    break;
                }
            }
        }
    });

    // Auto-fill cost jika produk dipilih
    if (productSelect) {
        productSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const cost = opt.getAttribute('data-cost');
            if (cost && (!costInput.value || costInput.value == 0)) {
                costInput.value = parseFloat(cost).toFixed(2);
                autoCalculateSalvage();
            }
        });
    }

    costInput.addEventListener('input', function () {
        if (!salvageInput.value || salvageInput.value == 0) {
            autoCalculateSalvage();
        } else {
            calculateDepreciationLive();
        }
    });

    salvageInput.addEventListener('input', calculateDepreciationLive);
    lifeInput.addEventListener('input', calculateDepreciationLive);
    methodSelect.addEventListener('change', calculateDepreciationLive);
    btnAuto5Percent.addEventListener('click', autoCalculateSalvage);

    // Initial calculation on page load
    calculateDepreciationLive();
});
</script>
@endsection
