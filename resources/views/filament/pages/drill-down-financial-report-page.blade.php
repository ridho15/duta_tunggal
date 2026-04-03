<x-filament-panels::page>

{{-- Select2 CSS & JS via CDN --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

@verbatim
<style>
    /* ── Print ── */
    @media print {
        .no-print { display: none !important; }
        .fi-topbar, .fi-sidebar, .fi-page-header, .ddf-filter-card, .fi-header-actions { display: none !important; }
        body { margin: 0; padding: 20px; }
        .ddf-print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    }
    .ddf-print-header { display: none; }

    /* ── Select2 light mode overrides to match Tailwind inputs ── */
    .select2-container--default .select2-selection--single {
        height: 38px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background-color: #ffffff;
        display: flex;
        align-items: center;
        transition: border-color .15s, box-shadow .15s;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px;
        padding-left: 0.75rem;
        padding-right: 2rem;
        color: #111827;
        font-size: 0.875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
        right: 6px;
    }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99,102,241,.15);
        outline: none;
    }
    .select2-dropdown {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,.12);
        font-size: 0.875rem;
        overflow: hidden;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        padding: 0.4rem 0.75rem;
        font-size: 0.875rem;
        outline: none;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #6366f1;
    }
    .select2-results__option { padding: 0.5rem 0.75rem; }

    /* ── Select2 dark mode — uses .dark class on <html> ── */
    .dark .select2-container--default .select2-selection--single {
        background-color: #1f2937;
        border-color: #374151;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #f3f4f6;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #9ca3af transparent transparent transparent;
    }
    .dark .select2-dropdown {
        background-color: #1f2937;
        border-color: #374151;
    }
    .dark .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #111827;
        border-color: #374151;
        color: #f3f4f6;
    }
    .dark .select2-container--default .select2-results__option {
        color: #e5e7eb;
    }
    .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #4f46e5;
        color: #fff;
    }
    .dark .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #312e81;
        color: #c7d2fe;
    }

    /* ── Report header gradient ── */
    .ddf-report-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
        color: #fff;
        border-radius: 16px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        text-align: center;
        box-shadow: 0 8px 24px rgba(37,99,235,.25);
    }
    .ddf-report-header .company { font-size: 1.4rem; font-weight: 800; letter-spacing: .02em; }
    .ddf-report-header .subtitle { font-size: 1.05rem; font-weight: 600; opacity: .9; margin-top: .2rem; }
    .ddf-report-header .period  { font-size: .85rem; opacity: .75; margin-top: .2rem; }

    /* ── Stat cards ── */
    .ddf-stat-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .ddf-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -6px rgba(0,0,0,.14);
    }

    /* ── Type badges ── */
    .ddf-badge-asset     { background-color: #dcfce7; color: #15803d; }
    .ddf-badge-liability { background-color: #fef9c3; color: #a16207; }
    .ddf-badge-equity    { background-color: #dbeafe; color: #1d4ed8; }
    .ddf-badge-revenue   { background-color: #f0fdf4; color: #16a34a; }
    .ddf-badge-expense   { background-color: #fee2e2; color: #dc2626; }

    /* dark mode badges — .dark class on <html>, NOT OS media query */
    .dark .ddf-badge-asset     { background-color: #14532d; color: #86efac; }
    .dark .ddf-badge-liability { background-color: #422006; color: #fde68a; }
    .dark .ddf-badge-equity    { background-color: #1e3a8a; color: #93c5fd; }
    .dark .ddf-badge-revenue   { background-color: #052e16; color: #4ade80; }
    .dark .ddf-badge-expense   { background-color: #450a0a; color: #fca5a5; }

    /* ── Stripe rows ── */
    .ddf-stripe:nth-child(even) { background-color: rgba(0,0,0,.018); }
    .dark .ddf-stripe:nth-child(even) { background-color: rgba(255,255,255,.025); }

    /* ── Details/summary chevron ── */
    details[open] summary .ddf-chevron { transform: rotate(90deg); }
    .ddf-chevron { transition: transform .2s ease; }

    /* ── Scrollable table wrapper ── */
    .ddf-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>
@endverbatim

<div class="space-y-6">

    {{-- ===== FILTER SECTION ===== --}}
    <div class="ddf-filter-card bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden no-print">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex-shrink-0">
                <x-heroicon-o-adjustments-horizontal class="w-5 h-5" />
            </div>
            <div>
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Filter Laporan</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pilih periode, akun, dan cabang untuk menampilkan data</p>
            </div>
        </div>

        <div class="p-6 space-y-5">
            {{-- Row 1: 4 columns --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                {{-- Tipe Akun --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <x-heroicon-m-tag class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />Tipe Akun
                    </label>
                    <select id="select-account-type" wire:model.live="account_type" data-ddf-plain
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                        <option value="">— Semua Tipe —</option>
                        <option value="Asset">Asset</option>
                        <option value="Liability">Liability</option>
                        <option value="Equity">Equity</option>
                        <option value="Revenue">Revenue</option>
                        <option value="Expense">Expense</option>
                    </select>
                </div>

                {{-- Akun COA (Select2) --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <x-heroicon-m-book-open class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />Akun COA
                    </label>
                    <select id="select-coa" wire:model="coa_id" data-ddf-select2 data-placeholder="Cari / pilih akun…">
                        <option value="">— Semua Akun —</option>
                        @foreach($this->coaOptions as $id => $label)
                            <option value="{{ $id }}" {{ $this->coa_id == $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal Mulai --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <x-heroicon-m-calendar-days class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />Tanggal Mulai
                    </label>
                    <input type="date" wire:model="start_date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                </div>

                {{-- Tanggal Akhir --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <x-heroicon-m-calendar-days class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />Tanggal Akhir
                    </label>
                    <input type="date" wire:model="end_date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                </div>
            </div>

            {{-- Row 2: Cabang + active type badge --}}
            <div class="flex flex-wrap items-end gap-4">
                <div class="space-y-1.5 w-64">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <x-heroicon-m-building-office-2 class="inline w-3.5 h-3.5 mr-1 align-text-bottom" />Cabang
                    </label>
                    <select id="select-cabang" wire:model="cabang_id" data-ddf-select2 data-placeholder="Cari / pilih cabang…">
                        <option value="">— Semua Cabang —</option>
                        @foreach(\App\Models\Cabang::orderBy('nama')->get() as $cabang)
                            <option value="{{ $cabang->id }}" {{ $this->cabang_id == $cabang->id ? 'selected' : '' }}>{{ $cabang->nama }}</option>
                        @endforeach
                    </select>
                </div>

                @if($this->account_type)
                    <div class="mb-0.5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border
                            @php
                                $typeColors = [
                                    'Asset'     => 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700',
                                    'Liability' => 'bg-yellow-50 text-yellow-700 border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
                                    'Equity'    => 'bg-blue-50  text-blue-700  border-blue-300  dark:bg-blue-900/30  dark:text-blue-300  dark:border-blue-700',
                                    'Revenue'   => 'bg-green-50 text-green-700 border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
                                    'Expense'   => 'bg-red-50   text-red-700   border-red-300   dark:bg-red-900/30   dark:text-red-300   dark:border-red-700',
                                ];
                                echo $typeColors[$this->account_type] ?? 'bg-gray-100 text-gray-600 border-gray-200';
                            @endphp">
                            <x-heroicon-m-funnel class="w-3 h-3" />
                            Filter aktif: {{ $this->account_type }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($this->showPreview)
        @php $data = $this->getDrillDownData(); @endphp

        {{-- Print Header --}}
        <div class="ddf-print-header">
            <h1 class="text-2xl font-bold">Drill Down Financial Report</h1>
            <p>Periode: {{ $this->start_date }} s/d {{ $this->end_date }}</p>
        </div>

        {{-- ===== REPORT HEADER ===== --}}
        <div class="ddf-report-header no-print">
            <div class="company">{{ config('app.name', 'Duta Tunggal ERP') }}</div>
            <div class="subtitle">DRILL DOWN FINANCIAL REPORT</div>
            <div class="period">
                Periode:
                {{ \Carbon\Carbon::parse($this->start_date)->isoFormat('D MMMM GGGG') }}
                —
                {{ \Carbon\Carbon::parse($this->end_date)->isoFormat('D MMMM GGGG') }}
                @if($this->account_type) &bull; Tipe: {{ $this->account_type }} @endif
            </div>
        </div>

        {{-- ===== SUMMARY STAT CARDS ===== --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print">

            {{-- Total Transaksi --}}
            <div class="ddf-stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex-shrink-0">
                    <x-heroicon-o-document-text class="w-6 h-6" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Transaksi</p>
                    <p class="text-3xl font-extrabold text-indigo-700 dark:text-indigo-300 leading-tight">{{ number_format($data['count'] ?? 0) }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">entri jurnal</p>
                </div>
            </div>

            {{-- Total Debit --}}
            <div class="ddf-stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                    <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Debit</p>
                    <p class="text-xl font-extrabold text-emerald-700 dark:text-emerald-300 leading-tight">Rp {{ number_format($data['total_debit'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">sisi debit</p>
                </div>
            </div>

            {{-- Total Kredit --}}
            <div class="ddf-stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex-shrink-0">
                    <x-heroicon-o-arrow-trending-down class="w-6 h-6" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Kredit</p>
                    <p class="text-xl font-extrabold text-rose-700 dark:text-rose-300 leading-tight">Rp {{ number_format($data['total_credit'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">sisi kredit</p>
                </div>
            </div>
        </div>

        {{-- ===== DETAIL TABLE ===== --}}
        <div class="bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden">

            {{-- Table Header Bar --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400">
                        <x-heroicon-o-table-cells class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Detail per Akun</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ count($data['grouped'] ?? []) }} akun ditemukan
                            @if($this->start_date && $this->end_date)
                                &bull; {{ \Carbon\Carbon::parse($this->start_date)->format('d M Y') }} — {{ \Carbon\Carbon::parse($this->end_date)->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                </div>
                <button onclick="window.print()"
                    class="no-print inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-1.5 text-sm font-medium transition">
                    <x-heroicon-o-printer class="w-4 h-4" />Cetak
                </button>
            </div>

            {{-- Account Groups --}}
            @forelse(($data['grouped'] ?? []) as $group)
                @php
                    $coaType = optional($group['coa'])['type'] ?? '';
                    $typeBadgeClass = match($coaType) {
                        'Asset'     => 'ddf-badge-asset',
                        'Liability' => 'ddf-badge-liability',
                        'Equity'    => 'ddf-badge-equity',
                        'Revenue'   => 'ddf-badge-revenue',
                        'Expense'   => 'ddf-badge-expense',
                        default     => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                    };
                    $balance   = $group['balance'] ?? 0;
                    $lineCount = count($group['lines'] ?? []);
                @endphp
                <details class="group border-b border-gray-100 dark:border-gray-800 last:border-0">
                    <summary class="flex flex-col sm:flex-row sm:items-center gap-2 px-6 py-4 cursor-pointer
                                    hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition select-none list-none">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 flex-shrink-0 ddf-chevron" />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                        {{ optional($group['coa'])['code'] ?? '-' }}
                                    </span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm truncate">
                                        {{ optional($group['coa'])['name'] ?? '-' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $typeBadgeClass }}">
                                        {{ $coaType }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $lineCount }} transaksi</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-6 flex-shrink-0 sm:ml-auto">
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-400 dark:text-gray-500">Debit</p>
                                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($group['total_debit'] ?? 0, 0, ',', '.') }}</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-400 dark:text-gray-500">Kredit</p>
                                <p class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ number_format($group['total_credit'] ?? 0, 0, ',', '.') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400 dark:text-gray-500">Saldo</p>
                                <p class="text-sm font-bold {{ $balance >= 0 ? 'text-gray-800 dark:text-gray-200' : 'text-rose-600 dark:text-rose-400' }}">
                                    Rp {{ number_format(abs($balance), 0, ',', '.') }}
                                    @if($balance < 0)<span class="text-xs font-normal">(K)</span>@endif
                                </p>
                            </div>
                        </div>
                    </summary>

                    {{-- Lines Table --}}
                    <div class="border-t border-gray-100 dark:border-gray-800">
                        <div class="ddf-table-wrap">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                                        <th class="px-6 py-2.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Tanggal</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Referensi</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Debit</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                    @foreach($group['lines'] as $line)
                                        <tr class="ddf-stripe hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-colors">
                                            <td class="px-6 py-2.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($line->date)->format('d/m/Y') }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                @if($line->reference)
                                                    <span class="inline-flex items-center font-mono text-xs bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded">
                                                        {{ $line->reference }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300 max-w-xs">
                                                <span class="block truncate" title="{{ $line->description }}">{{ $line->description ?: '—' }}</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                @if($line->debit > 0)
                                                    <span class="text-emerald-700 dark:text-emerald-400 font-medium">
                                                        Rp {{ number_format($line->debit, 0, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                @if($line->credit > 0)
                                                    <span class="text-rose-700 dark:text-rose-400 font-medium">
                                                        Rp {{ number_format($line->credit, 0, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                        <td colspan="3" class="px-6 py-2.5 text-right text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                            Subtotal — {{ optional($group['coa'])['name'] ?? '' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-bold text-emerald-700 dark:text-emerald-400 whitespace-nowrap">
                                            Rp {{ number_format($group['total_debit'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-sm font-bold text-rose-700 dark:text-rose-400 whitespace-nowrap">
                                            Rp {{ number_format($group['total_credit'] ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </details>
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                    <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                        <x-heroicon-o-inbox class="w-7 h-7 text-gray-400" />
                    </div>
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-400">Tidak ada data</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Tidak ada transaksi yang sesuai dengan filter yang dipilih.</p>
                </div>
            @endforelse

            {{-- Grand Total Footer --}}
            @if(!empty($data['grouped']))
            <div class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 border-t-2 border-gray-300 dark:border-gray-600 px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                <span class="text-xs font-extrabold text-gray-600 dark:text-gray-300 uppercase tracking-widest">Grand Total</span>
                <div class="flex items-center gap-6">
                    <div class="text-right">
                        <p class="text-xs text-gray-400 dark:text-gray-500">Total Debit</p>
                        <p class="text-base font-extrabold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($data['total_debit'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400 dark:text-gray-500">Total Kredit</p>
                        <p class="text-base font-extrabold text-rose-700 dark:text-rose-400">Rp {{ number_format($data['total_credit'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

    @else
        {{-- Empty State --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-2xl flex flex-col items-center justify-center py-20 text-center px-6">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-5 text-indigo-500 dark:text-indigo-400">
                <x-heroicon-o-magnifying-glass-plus class="w-8 h-8" />
            </div>
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">Belum ada laporan ditampilkan</h3>
            <p class="text-sm text-gray-400 dark:text-gray-500 max-w-sm">
                Atur filter di atas sesuai kebutuhan, kemudian klik
                <span class="font-semibold text-indigo-600 dark:text-indigo-400">Tampilkan Laporan</span>
                untuk memuat data drill down.
            </p>
        </div>
    @endif

</div>

{{-- ===== SELECT2 INIT ===== --}}
<script>
(function initSelect2() {
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    function applySelect2Theme() {
        // Theme switching is handled purely by CSS .dark selector, no JS needed here.
        // Dropdown appended to body will inherit the .dark class from <html>
    }

    function buildSelect2(el, opts) {
        $(el).select2(Object.assign({
            width: '100%',
            dropdownParent: $(el).closest('.ddf-filter-card').length
                ? $(el).closest('.ddf-filter-card')
                : $('body'),
        }, opts));

        // Sync back to Livewire on change
        $(el).on('change.select2', function () {
            const wireModel = el.getAttribute('wire:model') || el.getAttribute('wire\\:model');
            if (wireModel && window.Livewire) {
                const component = window.Livewire.find(el.closest('[wire\\:id]')?.getAttribute('wire:id'));
                if (component) {
                    component.set(wireModel, this.value || null);
                }
            }
        });
    }

    function init() {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            setTimeout(init, 150);
            return;
        }

        document.querySelectorAll('[data-ddf-select2]').forEach(function (el) {
            if ($(el).data('select2')) return; // already initialised
            const placeholder = el.getAttribute('data-placeholder') || 'Pilih…';
            buildSelect2(el, {
                placeholder: placeholder,
                allowClear: true,
            });
        });

        applySelect2Theme();
    }

    // Run on first load
    init();

    // Re-init after Livewire navigations
    document.addEventListener('livewire:navigated', init);

    // Re-init after Livewire DOM updates (re-rendered component)
    document.addEventListener('livewire:update', function () {
        setTimeout(init, 80);
    });
})();
</script>

</x-filament-panels::page>
<script>
window.addEventListener('open-report-preview', event => {
    const url = event.detail?.url ?? event.detail?.[0]?.url;

    if (url) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>
