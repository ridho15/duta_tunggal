@php
    $user = auth()->user();
    $roleName = $user ? ($user->roles->first()?->name ?? $user->posisi ?? 'Staff') : 'Pengguna';
    $cabangName = $user && $user->cabang ? $user->cabang->nama_cabang : 'Pusat';
    $initials = $user ? $user->initials() : 'DT';
    if (empty($initials)) {
        $initials = strtoupper(substr($user->name ?? 'User', 0, 2));
    }
@endphp

<header class="h-[50px] bg-white border-b border-gray-200 sticky top-0 z-20 flex items-center justify-between px-4 md:px-7 text-sm select-none">
    {{-- Left: Mobile Toggle + Breadcrumbs --}}
    <div class="flex items-center gap-3 min-w-0">
        <button
            type="button"
            onclick="toggleSidebar()"
            class="lg:hidden p-1.5 -ml-1 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
            aria-label="Buka menu"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"></path>
            </svg>
        </button>

        <nav aria-label="Breadcrumb" class="truncate">
            @hasSection('breadcrumbs')
                @yield('breadcrumbs')
            @else
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">Duta Tunggal</span>
                    <span class="text-gray-300">/</span>
                    <span class="font-bold text-gray-900">{{ $title ?? 'Beranda' }}</span>
                </div>
            @endif
        </nav>
    </div>

    {{-- Right: User badge & Logout Dropdown --}}
    <div class="flex items-center gap-3">
        <div class="relative" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-2.5 text-gray-700 hover:text-gray-900 text-sm font-medium focus:outline-none"
            >
                <span class="hidden md:inline">{{ $roleName }} · <span class="font-bold">{{ $cabangName }}</span></span>
                <span class="w-[30px] h-[30px] rounded-full bg-[#111827] text-white text-xs font-bold inline-flex items-center justify-center tracking-tight shadow-sm">
                    {{ $initials }}
                </span>
                <svg class="w-3.5 h-3.5 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>

            {{-- Dropdown Menu --}}
            <div
                x-show="open"
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1.5 z-50 divide-y divide-gray-100"
                style="display: none;"
            >
                <div class="px-4 py-2.5">
                    <div class="font-bold text-gray-900 text-sm truncate">{{ $user->name ?? 'User' }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $user->email ?? '' }}</div>
                    <div class="mt-1.5">
                        <span class="inline-block text-[10px] font-bold text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded px-1.5 py-0.2">
                            {{ $roleName }}
                        </span>
                    </div>
                </div>

                <div class="py-1">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button
                            type="submit"
                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-semibold flex items-center gap-2 transition"
                        >
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                            <span>Keluar (Logout)</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
