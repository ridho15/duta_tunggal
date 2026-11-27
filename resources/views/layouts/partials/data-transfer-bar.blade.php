{{-- Export / Import di setiap halaman daftar data, hanya tampil untuk Owner / Super Admin --}}
@php
    $dtRoute = request()->route()?->getName();
    $dtPage = null;
    if ($dtRoute && str_ends_with($dtRoute, '.index') && \App\Support\Access::canExportImport()) {
        $dtKey = \App\Support\Access::keyForRoute($dtRoute);
        $dtCandidate = $dtKey && $dtKey !== 'export' ? \App\Support\Access::split($dtKey)[0] : null;
        $dtPage = $dtCandidate && isset(\App\Support\DataTransfer::datasets()[$dtCandidate]) ? $dtCandidate : null;
    }
@endphp
@if ($dtPage)
    @php
        $dtHasDate = ! empty(\App\Support\DataTransfer::datasets()[$dtPage]['date']);
        $dtImportable = \App\Support\DataTransfer::importable($dtPage);
    @endphp
    <details class="group rounded-xl border border-gray-200 bg-white text-sm">
        <summary class="cursor-pointer list-none flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 rounded-xl hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
            <span class="text-gray-800">
                <span class="font-bold">Export / Import data</span>
                <span class="text-gray-600">· khusus Owner &amp; Super Admin</span>
            </span>
            <span class="text-xs font-semibold text-[#15803d]">
                <span class="group-open:hidden">Buka</span><span class="hidden group-open:inline">Tutup</span>
            </span>
        </summary>
        <div class="px-4 pb-4 pt-1 flex flex-col lg:flex-row lg:items-end gap-4 border-t border-gray-100">
            <form method="GET" action="{{ route('data-transfer.export', $dtPage) }}" class="flex flex-wrap items-end gap-2" data-no-disable>
                @if ($dtHasDate)
                    <label class="text-xs font-semibold text-gray-700">
                        Dari tanggal
                        <input type="date" name="from" class="mt-1 block rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                    </label>
                    <label class="text-xs font-semibold text-gray-700">
                        Sampai
                        <input type="date" name="to" class="mt-1 block rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                    </label>
                @endif
                <button type="submit" name="format" value="xlsx" class="cursor-pointer min-h-10 px-3 py-2 rounded-lg bg-[#15803d] text-white text-xs font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                    Export Excel
                </button>
                <button type="submit" name="format" value="csv" class="cursor-pointer min-h-10 px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                    Export CSV
                </button>
            </form>
            <p class="text-xs text-gray-600 lg:flex-1">
                {{ $dtHasDate ? 'Kosongkan tanggal untuk semua data.' : 'Semua data halaman ini ikut terunduh.' }}
                Kolom berakhiran _id disertai namanya supaya mudah dibaca.
            </p>
            @if ($dtImportable)
                <a href="{{ route('data-transfer.import', $dtPage) }}" class="min-h-10 inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                    Import dari Excel / CSV
                </a>
            @endif
        </div>
    </details>
@endif
