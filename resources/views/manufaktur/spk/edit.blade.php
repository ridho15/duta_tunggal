@extends('layouts.app')

@section('title', 'Ubah SPK: ' . $manufacturingOrder->mo_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto pb-12">
    {{-- Header & Navigasi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.spk.index') }}" class="hover:text-gray-700">Perintah Kerja (SPK)</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('manufaktur.spk.show', $manufacturingOrder) }}" class="hover:text-gray-700">{{ $manufacturingOrder->mo_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-amber-600 font-bold">Ubah SPK</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah SPK: {{ $manufacturingOrder->mo_number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Penyesuaian target kuantitas atau jadwal pengerjaan lantai produksi.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.spk.show', $manufacturingOrder) }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                form="spkEditForm"
                class="inline-flex items-center gap-2 px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Perbarui SPK
            </button>
        </div>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat kesalahan pengisian data:
            </div>
            <ul class="list-disc list-inside text-xs pl-7 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="spkEditForm" method="POST" action="{{ route('manufaktur.spk.update', $manufacturingOrder) }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">
                Parameter Perintah Kerja (Draft)
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Nomor SPK / MO <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="mo_number"
                        value="{{ old('mo_number', $manufacturingOrder->mo_number) }}"
                        required
                        class="w-full text-xs rounded-xl border-gray-300 font-mono uppercase focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Cabang Pabrik <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="cabang_id"
                        required
                        class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ old('cabang_id', $manufacturingOrder->cabang_id) == $cb->id ? 'selected' : '' }}>
                                ({{ $cb->kode }}) {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Kuantitas Target Dibuat <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="any"
                        min="0.0001"
                        name="quantity"
                        value="{{ old('quantity', rtrim(rtrim((string)$manufacturingOrder->quantity, '0'), '.')) }}"
                        required
                        class="w-full text-xs rounded-xl border-gray-300 font-bold focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Produk Jadi Target
                    </label>
                    <input
                        type="text"
                        disabled
                        value="{{ $manufacturingOrder->productionPlan?->product?->name }} ({{ $manufacturingOrder->productionPlan?->uom?->name ?? 'Pcs' }})"
                        class="w-full text-xs rounded-xl border-gray-200 bg-gray-50 text-gray-600"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Target Tanggal Mulai
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ old('start_date', $manufacturingOrder->start_date ? $manufacturingOrder->start_date->format('Y-m-d') : '') }}"
                        class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Target Tanggal Selesai
                    </label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ old('end_date', $manufacturingOrder->end_date ? $manufacturingOrder->end_date->format('Y-m-d') : '') }}"
                        class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Instruksi Kerja (Catatan)
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                    >{{ old('notes', $manufacturingOrder->productionPlan?->notes) }}</textarea>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                <a
                    href="{{ route('manufaktur.spk.show', $manufacturingOrder) }}"
                    class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition"
                >
                    Simpan Perubahan SPK
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
