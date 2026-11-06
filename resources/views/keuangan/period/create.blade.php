@extends('layouts.app')

@section('title', 'Buat Periode Akuntansi – Duta Tunggal ERP')

@section('content')
@php $inputClass = 'w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition border-gray-200'; @endphp
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.period.index') }}" class="hover:text-gray-700">Tutup Buku</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Periode Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buat Periode Akuntansi</h1>
            <p class="text-sm text-gray-500 mt-0.5">Periode baru berstatus Terbuka. Penguncian baru berlaku setelah periode ditutup.</p>
        </div>
        <a href="{{ route('keuangan.period.index') }}" class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition">Kembali</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="font-bold">Mohon periksa kesalahan input berikut:</div>
            <ul class="list-disc list-inside text-xs space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
        <form method="POST" action="{{ route('keuangan.period.store') }}" class="space-y-6">
            @csrf
            <div>
                <label for="cabang_id" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Cabang</label>
                <select id="cabang_id" name="cabang_id" class="{{ $inputClass }}">
                    <option value="">Tanpa cabang (pusat)</option>
                    @foreach ($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" @selected((string) old('cabang_id', $defaultCabang) === (string) $cabang->id)>{{ $cabang->nama }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-500 mt-1.5">Periode tanpa cabang hanya mengunci jurnal yang tidak punya cabang. Untuk mengunci jurnal sebuah cabang, pilih cabangnya.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="period_start" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal mulai <span class="text-rose-500">*</span></label>
                    <input id="period_start" type="date" name="period_start" required value="{{ old('period_start') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="period_end" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal selesai <span class="text-rose-500">*</span></label>
                    <input id="period_end" type="date" name="period_end" required value="{{ old('period_end') }}" class="{{ $inputClass }}">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                <a href="{{ route('keuangan.period.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]">Simpan Periode</button>
            </div>
        </form>
    </div>
</div>
@endsection
