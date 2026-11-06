@extends('layouts.app')

@section('title', 'Tambah Aturan Persetujuan – Duta Tunggal ERP')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('settings.approval-rules.index') }}" class="hover:text-gray-700">Aturan Persetujuan</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Aturan Persetujuan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tentukan rentang nilai dokumen dan peran yang boleh menyetujuinya.</p>
        </div>
        <a href="{{ route('settings.approval-rules.index') }}"
           class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition">Kembali</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="font-bold">Mohon periksa kesalahan input berikut:</div>
            <ul class="list-disc list-inside text-xs space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
        <form method="POST" action="{{ route('settings.approval-rules.store') }}" class="space-y-6">
            @csrf

            @include('pengaturan.approval-rules._form')

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                <a href="{{ route('settings.approval-rules.index') }}"
                   class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition">Batal</a>
                <button type="submit"
                        class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]">Simpan Aturan</button>
            </div>
        </form>
    </div>
</div>
@endsection
