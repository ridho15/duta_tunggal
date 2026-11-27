@extends('layouts.app')

@section('title', ($role ? 'Akses Peran ' . $role->name : 'Tambah Peran') . ' · Duta Tunggal ERP')

@section('content')
<div class="space-y-5 max-w-6xl mx-auto">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('settings.roles.index') }}" class="hover:text-gray-900 underline-offset-2 hover:underline">Peran &amp; Hak Akses</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#15803d] font-bold">{{ $role ? $role->name : 'Tambah peran' }}</span>
    </nav>

    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
            {{ $role ? 'Akses peran ' . $role->name : 'Tambah peran baru' }}
        </h1>
        <p class="text-sm text-gray-700 mt-1 max-w-3xl">
            Centang yang boleh dilakukan peran ini. Tanpa centang <b>Lihat (menu)</b>, menu halaman itu hilang dan halamannya tidak bisa dibuka.
            Tombol yang tidak dicentang ditolak sistem walau dicoba lewat alamat langsung.
        </p>
    </div>

    @if ($isSuperRole)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <b>{{ $role->name }}</b> selalu punya akses penuh, termasuk export/import Excel-CSV dan mengatur peran. Centangnya tidak bisa dikurangi.
        </div>
    @endif

    <form method="POST" action="{{ $role ? route('settings.roles.update', $role) : route('settings.roles.store') }}" class="space-y-5">
        @csrf
        @if ($role)
            @method('PUT')
        @endif

        <section class="bg-white rounded-2xl border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-2 gap-4" aria-label="Data peran">
            <div>
                <label for="role-name" class="block text-xs font-semibold text-gray-700 mb-1">Nama peran</label>
                <input id="role-name" name="name" type="text" required maxlength="100"
                    value="{{ old('name', $role?->name ?? request('name')) }}"
                    @disabled($nameLocked || $isSuperRole)
                    placeholder="Contoh: Akuntan 2"
                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed">
                @if ($nameLocked)
                    <input type="hidden" name="name" value="{{ $role->name }}">
                    <p class="mt-1 text-xs text-gray-600">Nama peran bawaan tidak bisa diganti karena dipakai aturan sistem. Duplikat peran ini bila perlu nama lain.</p>
                @endif
                @error('name')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="role-desc" class="block text-xs font-semibold text-gray-700 mb-1">Keterangan (opsional)</label>
                <input id="role-desc" name="description" type="text" maxlength="500"
                    value="{{ old('description', $role?->description) }}"
                    @disabled($isSuperRole)
                    placeholder="Contoh: akuntan cabang, tanpa tutup buku"
                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed">
            </div>

            @unless ($role)
                <div class="md:col-span-2">
                    <label for="role-template" class="block text-xs font-semibold text-gray-700 mb-1">Mulai dari peran (template)</label>
                    <select id="role-template" name="template_id"
                        onchange="window.location = '{{ route('settings.roles.create') }}?template=' + this.value + '&name=' + encodeURIComponent(document.getElementById('role-name').value)"
                        class="cursor-pointer w-full md:w-80 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                        <option value="">Kosong (centang sendiri dari nol)</option>
                        @foreach ($templates as $t)
                            <option value="{{ $t->id }}" @selected($template?->id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-600">
                        @if ($template)
                            Centang di bawah diisi dari peran <b>{{ $template->name }}</b>. Aturan khusus peran itu (mis. batas persetujuan) ikut terbawa.
                        @else
                            Pilih template supaya tidak mencentang dari nol. Centang tetap bisa diubah sebelum disimpan.
                        @endif
                    </p>
                </div>
            @endunless

            @if ($role && isset($usersCount))
                <p class="md:col-span-2 text-xs text-gray-700">
                    Dipakai <b>{{ $usersCount }}</b> user. Perubahan berlaku langsung untuk mereka.
                    @if ($usersCount > 0)
                        <a href="{{ route('settings.users.index', ['role' => $role->name]) }}" class="font-semibold text-[#15803d] underline underline-offset-2 hover:text-[#14532d]">Lihat user</a>
                    @endif
                </p>
            @endif
        </section>

        @include('pengaturan.roles._checklist', [
            'mode' => 'role',
            'readonly' => $isSuperRole,
            'checked' => old('keys', $checked),
        ])

        <div class="sticky bottom-0 z-10 -mx-4 px-4 py-3 bg-white/95 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <a href="{{ route('settings.roles.index') }}" class="cursor-pointer min-h-11 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                Kembali
            </a>
            @unless ($isSuperRole)
                <button type="submit" class="cursor-pointer min-h-11 inline-flex items-center justify-center px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                    {{ $role ? 'Simpan akses peran' : 'Buat peran' }}
                </button>
            @endunless
        </div>
    </form>

    @if ($role)
        @include('pengaturan.roles._history', ['history' => $history])
    @endif
</div>
@endsection
