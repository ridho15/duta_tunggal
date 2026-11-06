@extends('layouts.app')

@section('title', 'Tutup Buku – Duta Tunggal ERP')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
            <a href="{{ route('keuangan.period.index') }}" class="hover:text-gray-700">Tutup Buku</a>
            <span class="text-gray-300">/</span>
            <span class="text-rose-600 font-bold">Konfirmasi</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tutup periode {{ $period->period_start->format('d/m/Y') }} s/d {{ $period->period_end->format('d/m/Y') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">Cabang: {{ $period->cabang_id ? $period->cabang->nama : 'Tanpa cabang (pusat)' }}</p>
    </div>

    @if (session('error') || $errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm">{{ session('error') ?? $errors->first() }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-5">
        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm space-y-1.5">
            <p class="font-bold">Yang akan terjadi</p>
            <ul class="list-disc list-inside text-xs space-y-1">
                <li>Semua jurnal bertanggal di dalam rentang ini tidak bisa dibuat, diubah, atau dihapus lagi.</li>
                <li>Bila jurnal penutup dicentang, saldo akun pendapatan dan beban periode ini dipindahkan ke akun Laba Ditahan lewat jurnal bertanggal akhir periode.</li>
                <li>Membuka kembali periode nanti tidak membatalkan jurnal penutup yang sudah dibuat.</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('keuangan.period.close', $period) }}" class="space-y-5">
            @csrf
            <label class="flex items-start gap-2 text-xs sm:text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="generate_closing_entries" value="1" checked class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                <span><span class="font-semibold">Buat jurnal penutup otomatis</span><br><span class="text-gray-500">Menutup saldo pendapatan dan beban ke akun Laba Ditahan.</span></span>
            </label>

            <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('keuangan.period.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]">Tutup Buku</button>
            </div>
        </form>
    </div>
</div>
@endsection
