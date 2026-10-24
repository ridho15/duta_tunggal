@extends('layouts.app')

@section('title', 'Ubah Bukti Hasil Produksi – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.production.index') }}" class="hover:text-gray-700">Hasil Produksi</a>
                <span class="text-gray-300">/</span>
                <span class="text-amber-700 font-bold">Ubah Draft</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Hasil Produksi: {{ $production->production_number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui kuantitas dan tanggal pencatatan hasil produksi sebelum diselesaikan.</p>
        </div>
        <a
            href="{{ route('manufaktur.production.show', $production) }}"
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
                Terdapat kesalahan input:
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('manufaktur.production.update', $production) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @php
            $spk = $production->manufacturingOrder;
            $plan = $spk?->productionPlan;
            $product = $plan?->product;
        @endphp

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Informasi Dokumen
            </h3>

            {{-- SPK Reference Info (Readonly) --}}
            <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/70 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-gray-400 block mb-0.5">Surat Perintah Kerja (SPK):</span>
                    <span class="font-bold text-gray-900 font-mono">{{ $spk?->mo_number }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block mb-0.5">Produk Jadi:</span>
                    <span class="font-bold text-gray-900">{{ $product?->name }} ({{ $product?->sku }})</span>
                </div>
                <div>
                    <span class="text-gray-400 block mb-0.5">Target Rencana SPK:</span>
                    <span class="font-bold text-gray-900 font-mono">{{ number_format((float)($plan?->quantity ?? 0), 2) }} {{ $product?->uom?->name ?? 'Pcs' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block mb-0.5">Gudang Alokasi:</span>
                    <span class="font-bold text-gray-900">{{ $plan?->warehouse?->name ?? '-' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Bukti Produksi <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="production_number"
                        value="{{ old('production_number', $production->production_number) }}"
                        required
                        class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-mono font-bold text-gray-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Produksi <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="production_date"
                        value="{{ old('production_date', $production->production_date ? $production->production_date->toDateString() : now()->toDateString()) }}"
                        required
                        class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kuantitas Hasil Produksi <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative max-w-xs">
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="quantity_produced"
                            value="{{ old('quantity_produced', $production->quantity_produced) }}"
                            required
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-mono font-bold text-gray-900 focus:border-[#15803d] focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 transition"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-gray-400 font-bold">
                            {{ $product?->uom?->name ?? 'Pcs' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a
                    href="{{ route('manufaktur.production.show', $production) }}"
                    class="px-4 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
