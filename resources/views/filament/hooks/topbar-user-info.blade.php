@auth
    @php
        $dtUser = auth()->user();
        $dtCabang = $dtUser->cabang?->nama ?? ($dtUser->manage_type === 'all' ? 'Semua cabang' : null);
        $dtRole = $dtUser->getRoleNames()->first();
    @endphp
    {{-- Tahap 3: tombol Bantuan (video tutorial per peran) --}}
    <a href="{{ \App\Filament\Pages\BantuanPage::getUrl() }}" title="Bantuan &amp; video tutorial" aria-label="Bantuan"
       class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary-600 me-1">
        <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-6 w-6" />
    </a>
    {{-- Spesifikasi UI/UX 3.2: kanan header "Nama · Cabang" --}}
    <div class="hidden sm:flex flex-col items-end leading-tight me-2 text-right">
        <span class="text-sm font-semibold text-gray-900">{{ $dtUser->name }}</span>
        <span class="text-xs text-gray-500">{{ collect([$dtRole, $dtCabang])->filter()->join(' · ') }}</span>
    </div>
@endauth
