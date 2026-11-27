@extends('layouts.app')

@section('title', 'Import ' . $label . ' · Duta Tunggal ERP')

@section('content')
<div class="space-y-5 max-w-4xl mx-auto w-full">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
        @if (Route::has($listRoute))
            <a href="{{ route($listRoute) }}" class="hover:text-gray-900 hover:underline underline-offset-2">{{ $label }}</a>
            <span aria-hidden="true">/</span>
        @endif
        <span class="text-[#15803d] font-bold">Import</span>
    </nav>

    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Import {{ $label }}</h1>
        <p class="text-sm text-gray-700 mt-1">
            Dua langkah: <b>periksa</b> file dulu (belum ada yang tersimpan), lalu <b>proses</b>.
            Baris dengan <b>{{ $key }}</b> yang sudah ada akan diperbarui; yang belum ada dibuat baru. Tidak ada data yang dihapus.
        </p>
    </div>

    @if ($result === null)
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4" aria-labelledby="langkah-1">
            <h2 id="langkah-1" class="text-sm font-bold text-gray-900">1. Siapkan file</h2>
            <ol class="text-sm text-gray-800 list-decimal pl-5 space-y-1">
                <li>
                    <a href="{{ route('data-transfer.import-template', $page) }}" class="font-semibold text-[#15803d] underline underline-offset-2 hover:text-[#14532d]">Unduh template</a>
                    (judul kolom + 3 contoh data yang sudah ada).
                </li>
                <li>Jangan ubah baris judul. Kolom yang tidak perlu boleh dihapus, kecuali <b>{{ $key }}</b>.</li>
                <li>Untuk data <b>baru</b>, kolom wajib: {{ $required ? implode(', ', $required) : 'tidak ada selain ' . $key }}.</li>
                @if ($relations)
                    <li>Kolom relasi boleh diisi ID atau namanya, mis.
                        @foreach (array_slice($relations, 0, 3, true) as $fk => $rel)
                            <b>{{ $fk }}</b> atau <b>{{ substr($fk, 0, -3) . '_' . $rel['display'] }}</b>@if (! $loop->last), @endif
                        @endforeach.
                    </li>
                @endif
            </ol>

            <form method="POST" action="{{ route('data-transfer.import-check', $page) }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row sm:items-end gap-3 pt-2 border-t border-gray-100">
                @csrf
                <div class="flex-1">
                    <label for="import-file" class="block text-xs font-semibold text-gray-700 mb-1">2. Pilih file (.xlsx, .xls, .csv, maks. 10 MB)</label>
                    <input id="import-file" name="file" type="file" required accept=".xlsx,.xls,.csv"
                        class="cursor-pointer block w-full text-sm text-gray-800 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40 rounded-lg">
                    @error('file')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                    Periksa file
                </button>
            </form>
        </section>
    @else
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4" aria-labelledby="hasil-periksa" aria-live="polite">
            <h2 id="hasil-periksa" class="text-sm font-bold text-gray-900">Hasil pemeriksaan</h2>
            <dl class="flex flex-wrap gap-6 text-sm">
                <div><dt class="text-gray-600">Data baru</dt><dd class="text-xl font-bold text-gray-900">{{ $result['created'] }}</dd></div>
                <div><dt class="text-gray-600">Diperbarui</dt><dd class="text-xl font-bold text-gray-900">{{ $result['updated'] }}</dd></div>
                <div><dt class="text-gray-600">Masalah</dt><dd class="text-xl font-bold {{ $result['errors'] ? 'text-red-700' : 'text-gray-900' }}">{{ count($result['errors']) }}</dd></div>
            </dl>

            @if ($result['unknown'])
                <p class="text-xs text-amber-900 bg-amber-50 border border-amber-300 rounded-lg p-3">
                    Kolom ini tidak dikenal dan diabaikan: <b>{{ implode(', ', $result['unknown']) }}</b>.
                </p>
            @endif

            @if ($result['errors'])
                <div class="rounded-xl border border-red-300 bg-red-50 p-4" role="alert">
                    <p class="text-sm font-bold text-red-800">Belum bisa diproses. Perbaiki file lalu periksa ulang; tidak ada data yang berubah.</p>
                    <ul class="mt-2 space-y-1 text-xs text-red-900 list-disc pl-5 max-h-72 overflow-y-auto">
                        @foreach (array_slice($result['errors'], 0, 100) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                    @if (count($result['errors']) > 100)
                        <p class="mt-2 text-xs text-red-900">Dan {{ count($result['errors']) - 100 }} masalah lain.</p>
                    @endif
                </div>
                <a href="{{ route('data-transfer.import', $page) }}" class="min-h-11 inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                    Unggah file lain
                </a>
            @elseif ($result['created'] + $result['updated'] === 0)
                <p class="text-sm text-gray-800">File tidak berisi data. Isi mulai baris ke-2 lalu unggah lagi.</p>
                <a href="{{ route('data-transfer.import', $page) }}" class="min-h-11 inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Unggah file lain</a>
            @else
                <form method="POST" action="{{ route('data-transfer.import-store', $page) }}" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-100">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <a href="{{ route('data-transfer.import', $page) }}" class="min-h-11 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Batal</a>
                    <button type="submit" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                        Proses import ({{ $result['created'] }} baru, {{ $result['updated'] }} diperbarui)
                    </button>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
