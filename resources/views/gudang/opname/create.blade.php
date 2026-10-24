@extends('layouts.app')

@section('title', 'Buka Sesi Stock Opname – ERP Duta Tunggal')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div>
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
            <span class="text-gray-300">/</span>
            <a href="{{ route('gudang.opname.index') }}" class="hover:text-gray-700">Stock Opname</a>
            <span class="text-gray-300">/</span>
            <span class="text-emerald-700 font-bold">Buka Sesi Baru</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buka Sesi Stock Opname Baru</h1>
        <p class="text-xs text-gray-500 mt-1">
            Tentukan lokasi gudang yang akan diaudit fisik dan tanggal pelaksanaan opname.
        </p>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Terdapat kesalahan pada input:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('gudang.opname.store') }}" method="POST" class="bg-white rounded-xl p-6 border border-gray-200 shadow-2xs space-y-5">
        @csrf

        {{-- No. Opname --}}
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Nomor Sesi Opname
            </label>
            <input type="text"
                   name="opname_number"
                   value="{{ old('opname_number', $suggestedNumber) }}"
                   placeholder="Otomatis (contoh: OPN-...)"
                   class="w-full px-3 py-2 text-xs font-mono font-bold bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800">
            <p class="text-[10px] text-gray-400 mt-1">Dapat menggunakan penomoran otomatis sistem atau manual</p>
        </div>

        {{-- Tanggal Opname --}}
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Tanggal Pelaksanaan Opname <span class="text-rose-500">*</span>
            </label>
            <input type="date"
                   name="opname_date"
                   value="{{ old('opname_date', now()->format('Y-m-d')) }}"
                   required
                   class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800">
        </div>

        {{-- Gudang --}}
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Lokasi Gudang yang Diaudit <span class="text-rose-500">*</span>
            </label>
            <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name . ' (' . ($wh->kode ?? 'WH') . ')'])->values()" :value="old('warehouse_id')" placeholder="-- Pilih Lokasi Gudang --" :required="true" size="sm" />
            <p class="text-[10px] text-gray-400 mt-1">Setiap sesi opname memeriksa ketersediaan stok fisik pada satu gudang spesifik</p>
        </div>

        {{-- Catatan --}}
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Catatan / Keterangan Sesi (Opsional)
            </label>
            <textarea name="notes"
                      rows="3"
                      placeholder="Contoh: Audit stok akhir bulan gudang utama, penyesuaian triwulan..."
                      class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800">{{ old('notes') }}</textarea>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <a href="{{ route('gudang.opname.index') }}"
               class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Batal
            </a>
            <div class="flex items-center gap-2.5">
                <button type="submit"
                        name="action"
                        value="save_draft"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition">
                    Simpan Sesi (Draft)
                </button>
                <button type="submit"
                        name="action"
                        value="start_immediately"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" stroke-width="2"></path>
                        <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path>
                    </svg>
                    <span>Buka & Mulai Hitung</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
