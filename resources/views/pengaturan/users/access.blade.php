@extends('layouts.app')

@section('title', 'Akses ' . $user->name . ' · Duta Tunggal ERP')

@section('content')
<div class="space-y-5 max-w-6xl mx-auto">
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
        <a href="{{ route('settings.users.index') }}" class="hover:text-gray-900 hover:underline underline-offset-2">Pengguna</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('settings.users.show', $user) }}" class="hover:text-gray-900 hover:underline underline-offset-2">{{ $user->name }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#15803d] font-bold">Akses</span>
    </nav>

    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Akses {{ $user->name }}</h1>
            <p class="text-sm text-gray-700 mt-1 max-w-3xl">
                Peran: <b>{{ $user->roles->pluck('name')->implode(', ') ?: 'belum ada peran' }}</b>.
                Akses mengikuti peran. Centang atau lepas di sini untuk <b>menambah</b> atau <b>mencabut</b> akses khusus user ini saja; peran dan user lain tidak berubah.
            </p>
        </div>
        @unless ($isSuper)
            <form method="POST" action="{{ route('settings.users.access.reset', $user) }}">
                @csrf
                <button type="submit" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                    Hapus semua akses khusus
                </button>
            </form>
        @endunless
    </div>

    @if ($isSuper)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            {{ $user->name }} adalah Owner / Super Admin: aksesnya selalu penuh dan tidak bisa dikurangi.
        </div>
    @else
        <form method="POST" action="{{ route('settings.users.access.copy', $user) }}" class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-col md:flex-row md:items-end gap-3">
            @csrf
            <div class="flex-1">
                <label for="source-user" class="block text-xs font-semibold text-gray-700 mb-1">Salin peran &amp; akses dari user lain</label>
                <select id="source-user" name="source_user_id" required
                    class="cursor-pointer w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                    <option value="">Pilih user sumber</option>
                    @foreach ($sourceUsers as $source)
                        <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->roles->pluck('name')->implode(', ') ?: 'tanpa peran' }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-600">Peran dan akses khusus {{ $user->name }} diganti persis seperti user sumber. Cabang &amp; gudang tidak ikut berubah.</p>
                @error('source_user_id')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="cursor-pointer min-h-11 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                Salin dari user ini
            </button>
        </form>
    @endif

    <form method="POST" action="{{ route('settings.users.access.update', $user) }}" class="space-y-5">
        @csrf
        @method('PUT')

        @include('pengaturan.roles._checklist', [
            'mode' => $isSuper ? 'role' : 'user',
            'readonly' => $isSuper,
            'checked' => $roleKeys,
            'roleKeys' => $roleKeys,
            'overrides' => $overrides,
        ])

        @unless ($isSuper)
            <div class="sticky bottom-0 z-10 -mx-4 px-4 py-3 bg-white/95 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="{{ route('settings.users.show', $user) }}" class="min-h-11 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Kembali</a>
                <button type="submit" class="cursor-pointer min-h-11 px-5 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                    Simpan akses {{ $user->name }}
                </button>
            </div>
        @endunless
    </form>

    @include('pengaturan.roles._history', ['history' => $history])
</div>
@endsection
