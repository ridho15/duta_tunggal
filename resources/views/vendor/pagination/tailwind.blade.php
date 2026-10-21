@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi Halaman" class="flex items-center justify-between">
        {{-- Tampilan Mobile --}}
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-400 bg-gray-50 border border-gray-200 cursor-not-allowed rounded-lg">
                    &laquo; Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-xs transition">
                    &laquo; Sebelumnya
                </a>
            @endif

            <span class="text-xs text-gray-600 self-center font-medium">
                Hal. {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-xs transition">
                    Selanjutnya &raquo;
                </a>
            @else
                <span class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-400 bg-gray-50 border border-gray-200 cursor-not-allowed rounded-lg">
                    Selanjutnya &raquo;
                </span>
            @endif
        </div>

        {{-- Tampilan Desktop & Tablet --}}
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-gray-600">
                    Menampilkan
                    <span class="font-bold text-gray-900">{{ $paginator->firstItem() ?? 0 }}</span>
                    sampai
                    <span class="font-bold text-gray-900">{{ $paginator->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-gray-900">{{ $paginator->total() }}</span>
                    data
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-xl shadow-xs -space-x-px">
                    {{-- Tombol Sebelumnya --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="Halaman Sebelumnya">
                            <span class="relative inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-300 bg-white border border-gray-200 cursor-not-allowed rounded-l-xl" aria-hidden="true">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Sebelumnya
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-l-xl hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-[#15803d] transition" aria-label="Halaman Sebelumnya">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Sebelumnya
                        </a>
                    @endif

                    {{-- Elemen Nomor Halaman --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-400 bg-white border border-gray-300 cursor-default">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-3.5 py-2 text-xs font-bold text-white bg-[#15803d] border border-[#15803d] z-10">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-3.5 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-[#15803d] transition" aria-label="Ke halaman {{ $page }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Tombol Selanjutnya --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-r-xl hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-[#15803d] transition" aria-label="Halaman Selanjutnya">
                            Selanjutnya
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="Halaman Selanjutnya">
                            <span class="relative inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-300 bg-white border border-gray-200 cursor-not-allowed rounded-r-xl" aria-hidden="true">
                                Selanjutnya
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
