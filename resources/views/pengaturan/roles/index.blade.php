@extends('layouts.app')

@section('title', 'Peran & Hak Akses · Duta Tunggal ERP')

@section('content')
<div class="space-y-5 max-w-6xl mx-auto" x-data="{ dup: { open: false, id: null, name: '', action: '' } }">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-gray-600">Pengaturan</p>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Peran &amp; Hak Akses</h1>
            <p class="text-sm text-gray-700 mt-1 max-w-2xl">
                Tiap peran punya centang sendiri per halaman dan per tombol. Akses khusus satu orang diatur dari
                <a href="{{ route('settings.users.index') }}" class="font-semibold text-[#15803d] underline underline-offset-2 hover:text-[#14532d]">daftar pengguna</a>.
            </p>
        </div>
        <a href="{{ route('settings.roles.create') }}" class="cursor-pointer min-h-11 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
            Tambah peran
        </a>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-col sm:flex-row gap-2" role="search">
        <label for="role-search" class="sr-only">Cari nama peran</label>
        <input id="role-search" type="search" name="search" value="{{ $search }}" placeholder="Cari nama peran"
            class="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        <button type="submit" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Cari</button>
        @if ($search !== '')
            <a href="{{ route('settings.roles.index') }}" class="min-h-11 inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100">Hapus pencarian</a>
        @endif
    </form>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($roles->isEmpty())
            <div class="p-10 text-center text-sm text-gray-700">
                @if ($search !== '')
                    Tidak ada peran bernama "<b>{{ $search }}</b>".
                @else
                    Belum ada peran. Mulai dengan <a href="{{ route('settings.roles.create') }}" class="font-semibold text-[#15803d] underline">Tambah peran</a>.
                @endif
            </div>
        @else
            {{-- Batas lebar di HP: kolom konten layout utama tidak min-w-0, tabel lebar akan mendorong halaman. --}}
            <div class="overflow-x-auto max-w-[calc(100vw-2rem)] md:max-w-none">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3">Peran</th>
                            <th scope="col" class="px-4 py-3">Akses</th>
                            <th scope="col" class="px-4 py-3">User</th>
                            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($roles as $role)
                            @php
                                $isSuperRole = in_array($role->name, $superRoles, true);
                                $isBuiltIn = $isSuperRole || in_array($role->name, $builtIn, true);
                                $pct = $totalKeys ? round($counts[$role->id] / $totalKeys * 100) : 0;
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <a href="{{ route('settings.roles.edit', $role) }}" class="font-bold text-gray-900 hover:text-[#15803d] hover:underline underline-offset-2">{{ $role->name }}</a>
                                    <p class="text-xs text-gray-600 mt-0.5">
                                        @if ($isSuperRole)
                                            Akses penuh, tidak bisa dikurangi
                                        @elseif ($role->description)
                                            {{ $role->description }}
                                        @elseif ($isBuiltIn)
                                            Peran bawaan sistem
                                        @elseif ($role->base_role)
                                            Turunan dari {{ $role->base_role }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 min-w-40">
                                    <p class="text-xs text-gray-800"><b>{{ $counts[$role->id] }}</b> dari {{ $totalKeys }}</p>
                                    <div class="mt-1 h-1.5 w-32 rounded-full bg-gray-200" role="img" aria-label="{{ $pct }} persen akses">
                                        <div class="h-1.5 rounded-full bg-[#15803d]" style="width: {{ $pct }}%"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-800">{{ $role->users_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('settings.roles.edit', $role) }}" class="min-h-9 inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                                            {{ $isSuperRole ? 'Lihat' : 'Atur akses' }}
                                        </a>
                                        @unless ($isSuperRole)
                                            <button type="button"
                                                @click="dup = { open: true, id: {{ $role->id }}, name: @js($role->name . ' 2'), from: @js($role->name), action: @js(route('settings.roles.duplicate', $role)) }; $nextTick(() => $refs.dupName.focus())"
                                                class="cursor-pointer min-h-9 inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                                                Duplikat
                                            </button>
                                        @endunless
                                        @if (! $isBuiltIn && $role->users_count === 0)
                                            <button type="button"
                                                @click="$dispatch('open-modal-delete-role', { action: @js(route('settings.roles.destroy', $role)), method: 'DELETE', title: @js('Hapus peran ' . $role->name . '?') })"
                                                class="cursor-pointer min-h-9 inline-flex items-center px-3 py-1.5 rounded-lg border border-red-300 bg-white text-xs font-bold text-red-700 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
                                                Hapus
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($roles->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $roles->links() }}</div>
            @endif
        @endif
    </div>

    {{-- Modal duplikat: minta nama peran baru --}}
    <div x-show="dup.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="dup-title" @keydown.escape.window="dup.open = false">
        <div class="absolute inset-0 bg-gray-900/60" @click="dup.open = false"></div>
        <form method="POST" :action="dup.action" class="relative w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-2xl">
            @csrf
            <div class="p-5 space-y-3">
                <h2 id="dup-title" class="text-base font-bold text-gray-900">Duplikat peran <span x-text="dup.from"></span></h2>
                <p class="text-xs text-gray-700">Peran baru memakai semua centang <span x-text="dup.from"></span>. Setelah dibuat, centangnya bisa diubah tanpa mengganggu peran asal.</p>
                <div>
                    <label for="dup-name" class="block text-xs font-semibold text-gray-700 mb-1">Nama peran baru</label>
                    <input id="dup-name" x-ref="dupName" name="name" type="text" required maxlength="100" x-model="dup.name"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-gray-100 rounded-b-2xl">
                <button type="button" @click="dup.open = false" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Batal</button>
                <button type="submit" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl bg-[#15803d] text-white text-xs font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">Buat duplikat</button>
            </div>
        </form>
    </div>

    <x-confirmation-modal name="delete-role" title="Hapus peran?" variant="danger" confirm-text="Ya, hapus peran" method="DELETE">
        Peran ini tidak dipakai user mana pun. Jejak perubahannya tetap tersimpan di riwayat.
    </x-confirmation-modal>
</div>
@endsection
