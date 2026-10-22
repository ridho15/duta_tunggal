@extends('layouts.app')

@section('title', 'Tambah Produk Baru – Duta Tunggal ERP')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.products.index') }}" class="hover:text-gray-700">Master Produk</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Produk Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Lengkapi spesifikasi SKU, harga jual, aturan diskon batas, dan parameter akuntansi.</p>
        </div>
        <a
            href="{{ route('master.products.index') }}"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
        >
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- Alert Errors --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mohon periksa kembali formulir Anda:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-6 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('master.products.store') }}" class="space-y-6">
        @csrf

        {{-- Seksi 1: Informasi Utama Produk --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">1. Informasi Utama Produk</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Identitas dasar produk, kode SKU, dan penempatan cabang.</p>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-[#15803d] rounded border-gray-300 focus:ring-[#15803d]">
                        <span>Status Aktif</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- SKU dengan Auto-Generate --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        SKU / Kode Produk <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            name="sku"
                            id="product_sku"
                            value="{{ old('sku', $generatedSku) }}"
                            required
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono uppercase"
                        >
                        <button
                            type="button"
                            onclick="reloadSku()"
                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
                            title="Generate SKU Baru"
                        >
                            <svg id="sku_spinner" class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Generate</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Kode unik identifikasi stok barang.</p>
                </div>

                {{-- Nama Produk --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Produk <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        placeholder="Contoh: Baut M8x25 Baja Hitam"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Kategori Produk --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kategori Produk <span class="text-rose-500">*</span>
                    </label>
                    <select name="product_category_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Pilih Kategori Produk</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('product_category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Satuan Utama (UOM) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Satuan Dasar (UOM) <span class="text-rose-500">*</span>
                    </label>
                    <select name="uom_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Pilih Satuan</option>
                        @foreach ($uoms as $uom)
                            <option value="{{ $uom->id }}" {{ old('uom_id') == $uom->id ? 'selected' : '' }}>
                                {{ $uom->name }} ({{ $uom->abbreviation }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kode Merk --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kode Merk / Brand <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="kode_merk"
                        value="{{ old('kode_merk', '-') }}"
                        required
                        placeholder="Contoh: BOSCH / YAMAHA / STANDAR"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Kepemilikan
                    </label>
                    <select name="cabang_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Semua Cabang (Global)</option>
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ old('cabang_id') == $cb->id ? 'selected' : '' }}>
                                ({{ $cb->kode }}) {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">Kosongkan jika produk dapat diperdagangkan di seluruh cabang.</p>
                </div>
            </div>

            {{-- Karakteristik Produksi --}}
            <div class="pt-2 flex flex-wrap gap-6 border-t border-gray-100">
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_manufacture" value="1" {{ old('is_manufacture') ? 'checked' : '' }} class="w-4 h-4 text-purple-600 rounded border-gray-300 focus:ring-purple-500">
                    <div>
                        <span class="font-bold text-purple-800">Barang Hasil Produksi (Barang Jadi)</span>
                        <p class="text-[11px] text-gray-500">Produk ini diproduksi internal dari perakitan/BOM</p>
                    </div>
                </label>

                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_raw_material" value="1" {{ old('is_raw_material') ? 'checked' : '' }} class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                    <div>
                        <span class="font-bold text-amber-800">Bahan Baku (Raw Material)</span>
                        <p class="text-[11px] text-gray-500">Digunakan sebagai material produksi / work-in-progress</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Seksi 2: Struktur Harga & Pajak --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">2. Penetapan Harga & Pajak</h2>
                <p class="text-xs text-gray-500 mt-0.5">Atur harga beli standar, harga jual resmi, batas toleransi diskon kasir, dan PPN.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Harga Beli --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Harga Beli Asli (Modal) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                        <input
                            type="number"
                            step="0.01"
                            name="cost_price"
                            value="{{ old('cost_price', 0) }}"
                            required
                            min="0"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-semibold text-gray-800"
                        >
                    </div>
                </div>

                {{-- Harga Jual --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Harga Jual Standar <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                        <input
                            type="number"
                            step="0.01"
                            name="sell_price"
                            value="{{ old('sell_price', 0) }}"
                            required
                            min="0"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-bold text-[#15803d]"
                        >
                    </div>
                </div>

                {{-- Biaya Tambahan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Biaya Tambahan
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                        <input
                            type="number"
                            step="0.01"
                            name="biaya"
                            value="{{ old('biaya', 0) }}"
                            min="0"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                    </div>
                </div>

                {{-- Batas Harga Jual (Approval Rule) --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Harga Batas (Aturan Diskon Kasir)
                    </label>
                    <input
                        type="number"
                        name="harga_batas"
                        value="{{ old('harga_batas', 0) }}"
                        min="0"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <div class="mt-1 p-2.5 bg-amber-50/70 border border-amber-200 rounded-lg text-xs text-amber-800">
                        💡 <strong>Ketentuan Batas Harga:</strong> Isi <code>0–100</code> sebagai persentase diskon maksimal dari harga jual (misal 15 = diskon maks 15%). Isi <code>&gt; 100</code> sebagai nominal rupiah batas bawah harga jual (misal 50.000 = tidak boleh dijual di bawah Rp 50.000).
                    </div>
                </div>

                {{-- Kelipatan Gudang Besar --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kelipatan Gudang Besar
                    </label>
                    <input
                        type="number"
                        name="jumlah_kelipatan_gudang_besar"
                        value="{{ old('jumlah_kelipatan_gudang_besar', 0) }}"
                        min="0"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                {{-- Tipe Pajak --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipe Pajak Produk <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['Inklusif', 'Eksklusif', 'Non Pajak'] as $tp)
                            <label class="flex items-center justify-center p-2 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe_pajak', 'Inklusif') === $tp ? 'bg-emerald-50 border-[#15803d] text-[#15803d] font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                                <input type="radio" name="tipe_pajak" value="{{ $tp }}" {{ old('tipe_pajak', 'Inklusif') === $tp ? 'checked' : '' }} class="sr-only">
                                <span>{{ $tp }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Persentase Pajak --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Persentase Pajak (%)
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            step="0.01"
                            name="pajak"
                            value="{{ old('pajak', $defaultTax) }}"
                            min="0"
                            max="100"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Seksi 3: Konversi Satuan Alternatif --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">3. Konversi Multi-Satuan (Opsional)</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Konversi satuan turunan ke satuan dasar (misal: 1 Box = 24 PCS).</p>
                </div>
                <button
                    type="button"
                    onclick="addConversionRow()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-[#15803d] bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Konversi
                </button>
            </div>

            <div id="conversion_container" class="space-y-3">
                {{-- Baris konversi akan diisi secara dinamis melalui JavaScript --}}
            </div>
            <p id="no_conversion_text" class="text-xs text-gray-400 italic text-center py-2">Belum ada konversi multi-satuan yang ditambahkan.</p>
        </div>

        {{-- Seksi 4: Pemetaan Akun Akuntansi (COA) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">4. Parameter Akuntansi (COA Mapping)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Jika dikosongkan, sistem akan otomatis menetapkan akun perkiraan default dari pengaturan ERP.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Akun Persediaan (Inventory)</label>
                    <select name="inventory_coa_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Gunakan Akun Default Persediaan</option>
                        @foreach ($coas as $coa)
                            <option value="{{ $coa->id }}" {{ old('inventory_coa_id') == $coa->id ? 'selected' : '' }}>
                                {{ $coa->code }} - {{ $coa->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Akun Penjualan (Revenue)</label>
                    <select name="sales_coa_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Gunakan Akun Default Penjualan</option>
                        @foreach ($coas as $coa)
                            <option value="{{ $coa->id }}" {{ old('sales_coa_id') == $coa->id ? 'selected' : '' }}>
                                {{ $coa->code }} - {{ $coa->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Akun Harga Pokok Penjualan (HPP / COGS)</label>
                    <select name="cogs_coa_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Gunakan Akun Default HPP</option>
                        @foreach ($coas as $coa)
                            <option value="{{ $coa->id }}" {{ old('cogs_coa_id') == $coa->id ? 'selected' : '' }}>
                                {{ $coa->code }} - {{ $coa->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Akun Retur Penjualan</label>
                    <select name="sales_return_coa_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Gunakan Akun Default Retur</option>
                        @foreach ($coas as $coa)
                            <option value="{{ $coa->id }}" {{ old('sales_return_coa_id') == $coa->id ? 'selected' : '' }}>
                                {{ $coa->code }} - {{ $coa->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Seksi 5: Deskripsi --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
            <h2 class="text-base font-bold text-gray-900">5. Keterangan / Deskripsi Produk</h2>
            <textarea
                name="description"
                rows="3"
                placeholder="Catatan spesifikasi teknis, dimensi, atau instruksi penanganan barang..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
            >{{ old('description') }}</textarea>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('master.products.index') }}"
                class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Simpan Produk Baru
            </button>
        </div>
    </form>
</div>

{{-- Script Dinamis SKU & Konversi Satuan --}}
<script>
    function reloadSku() {
        const spinner = document.getElementById('sku_spinner');
        spinner.classList.add('animate-spin');

        fetch("{{ route('master.products.generate-sku') }}", {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.sku) {
                document.getElementById('product_sku').value = data.sku;
            }
        })
        .catch(err => console.error('Gagal generate SKU:', err))
        .finally(() => {
            setTimeout(() => spinner.classList.remove('animate-spin'), 300);
        });
    }

    let convIndex = 0;
    const uomOptions = @json($uoms);

    function addConversionRow(uomId = '', nilai = '') {
        const container = document.getElementById('conversion_container');
        document.getElementById('no_conversion_text').style.display = 'none';

        let optionsHtml = '<option value="">Pilih Satuan Konversi</option>';
        uomOptions.forEach(u => {
            const selected = (String(u.id) === String(uomId)) ? 'selected' : '';
            optionsHtml += `<option value="${u.id}" ${selected}>${u.name} (${u.abbreviation})</option>`;
        });

        const row = document.createElement('div');
        row.id = `conv_row_${convIndex}`;
        row.className = 'grid grid-cols-1 sm:grid-cols-12 gap-3 items-center p-3 bg-gray-50 border border-gray-200 rounded-lg';
        row.innerHTML = `
            <div class="sm:col-span-6">
                <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Satuan Konversi</label>
                <select name="unit_conversions[${convIndex}][uom_id]" required class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    ${optionsHtml}
                </select>
            </div>
            <div class="sm:col-span-5">
                <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Nilai Konversi (x Satuan Utama)</label>
                <input type="number" step="0.0001" min="0.0001" name="unit_conversions[${convIndex}][nilai_konversi]" value="${nilai}" placeholder="Contoh: 12" required class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
            </div>
            <div class="sm:col-span-1 flex justify-end sm:pt-4">
                <button type="button" onclick="removeConversionRow(${convIndex})" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="Hapus Baris">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        `;
        container.appendChild(row);
        convIndex++;
    }

    function removeConversionRow(index) {
        const row = document.getElementById(`conv_row_${index}`);
        if (row) {
            row.remove();
        }
        const container = document.getElementById('conversion_container');
        if (container.children.length === 0) {
            document.getElementById('no_conversion_text').style.display = 'block';
        }
    }
</script>
@endsection
