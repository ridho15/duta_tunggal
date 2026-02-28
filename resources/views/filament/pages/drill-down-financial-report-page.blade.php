<x-filament-panels::page>

<style>
    @media print {
        .no-print { display: none !important; }
        .fi-topbar, .fi-sidebar, .fi-page-header, .filter-section, .fi-header-actions { display: none !important; }
        body { margin: 0; padding: 20px; }
        .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    }
    .print-header { display: none; }

    /* Type Badge Colors */
    .badge-asset     { background-color: #dcfce7; color: #15803d; }
    .badge-liability { background-color: #fef9c3; color: #a16207; }
    .badge-equity    { background-color: #dbeafe; color: #1d4ed8; }
    .badge-revenue   { background-color: #f0fdf4; color: #16a34a; }
    .badge-expense   { background-color: #fee2e2; color: #dc2626; }

    .dark .badge-asset     { background-color: #14532d; color: #86efac; }
    .dark .badge-liability { background-color: #422006; color: #fde68a; }
    .dark .badge-equity    { background-color: #1e3a8a; color: #93c5fd; }
    .dark .badge-revenue   { background-color: #052e16; color: #4ade80; }
    .dark .badge-expense   { background-color: #450a0a; color: #fca5a5; }

    details[open] summary .chevron { transform: rotate(90deg); }
    .chevron { transition: transform 0.2s ease; }

    /* Stripe rows */
    .stripe-row:nth-child(even) { background-color: rgba(0,0,0,0.015); }
    .dark .stripe-row:nth-child(even) { background-color: rgba(255,255,255,0.025); }

    /* Card hover */
    .stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -4px rgba(0,0,0,0.12); }
</style>

<div class="space-y-6">

    {{-- ===== FILTER SECTION ===== --}}
    <div class="filter-section bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden no-print">
        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400">
                <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Filter Laporan</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pilih periode, akun, dan cabang untuk menampilkan data</p>
            </div>
        </div>

        <div class="p-6 space-y-5">
            {{-- Row 1: 4 columns --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                {{-- Tipe Akun --}}
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Tipe Akun</label>
                    <div class="relative">
                        <select wire:model.live="account_type"
                            class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 pl-3 pr-8 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition">
                            <option value="">Semua Tipe</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Expense">Expense</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                {{-- Akun COA --}}
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Akun COA</label>
                    <div class="relative">
                        <select wire:model="coa_id"
                            class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 pl-3 pr-8 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition">
                            <option value="">Semua Akun</option>
                            @foreach($this->coaOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                {{-- Tanggal Mulai --}}
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Tanggal Mulai</label>
                    <input type="date" wire:model="start_date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition">
                </div>

                {{-- Tanggal Akhir --}}
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Tanggal Akhir</label>
                    <input type="date" wire:model="end_date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition">
                </div>
            </div>

            {{-- Row 2: Cabang --}}
            <div class="flex flex-wrap items-center gap-4 pt-1">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Cabang</label>
                    <div class="relative">
                        <select wire:model="cabang_id"
                            class="appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 pl-3 pr-8 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition">
                            <option value="">Semua Cabang</option>
                            @foreach(\App\Models\Cabang::all() as $cabang)
                                <option value="{{ $cabang->id }}">{{ $cabang->nama }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                @if($this->account_type)
                    <div class="mt-5">
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold border
                            @php
                                $typeColors = [
                                    'Asset' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700',
                                    'Liability' => 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
                                    'Equity' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
                                    'Revenue' => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
                                    'Expense' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
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

        {{-- Print Header (only visible when printing) --}}
        <div class="print-header">
            <h1 class="text-2xl font-bold">Drill Down Financial Report</h1>
            <p class="text-sm text-gray-500">Periode: {{ $this->start_date }} s/d {{ $this->end_date }}</p>
        </div>

        {{-- ===== SUMMARY STATS ===== --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print">
            {{-- Total Transaksi --}}
            <div class="stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex-shrink-0">
                        <x-heroicon-o-document-text class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Transaksi</p>
                        <p class="text-3xl font-extrabold text-blue-700 dark:text-blue-300 leading-tight">{{ number_format($data['count'] ?? 0) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">entri jurnal</p>
                    </div>
                </div>
            </div>

            {{-- Total Debit --}}
            <div class="stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                        <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Debit</p>
                        <p class="text-xl font-extrabold text-emerald-700 dark:text-emerald-300 leading-tight">Rp {{ number_format($data['total_debit'] ?? 0, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">sisi debit</p>
                    </div>
                </div>
            </div>

            {{-- Total Kredit --}}
            <div class="stat-card bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex-shrink-0">
                        <x-heroicon-o-arrow-trending-down class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Kredit</p>
                        <p class="text-xl font-extrabold text-rose-700 dark:text-rose-300 leading-tight">Rp {{ number_format($data['total_credit'] ?? 0, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">sisi kredit</p>
                    </div>
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
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Detail per Akun</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ count($data['grouped'] ?? []) }} akun ditemukan
                            @if($this->start_date && $this->end_date)
                                &bull; {{ \Carbon\Carbon::parse($this->start_date)->format('d M Y') }} — {{ \Carbon\Carbon::parse($this->end_date)->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                </div>
                <button onclick="window.print()"
                    class="no-print inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 px-3 py-1.5 text-sm font-medium transition">
                    <x-heroicon-o-printer class="w-4 h-4" />
                    Cetak
                </button>
            </div>

            {{-- Account Groups --}}
            @forelse(($data['grouped'] ?? []) as $group)
                @php
                    $coaType = optional($group['coa'])['type'] ?? '';
                    $typeBadgeClass = match($coaType) {
                        'Asset'     => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                        'Liability' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                        'Equity'    => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                        'Revenue'   => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                        'Expense'   => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        default     => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                    };
                    $balance = $group['balance'] ?? 0;
                    $lineCount = count($group['lines'] ?? []);
                @endphp
                <details class="group border-b border-gray-100 dark:border-gray-800 last:border-0">
                    <summary class="flex flex-col sm:flex-row sm:items-center gap-2 px-6 py-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/60 transition select-none list-none">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            {{-- Chevron --}}
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 flex-shrink-0 chevron group-open:rotate-90 transition-transform duration-200" />

                            {{-- COA Code + Name --}}
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                        {{ optional($group['coa'])['code'] ?? '-' }}
                                    </span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm truncate">
                                        {{ optional($group['coa'])['name'] ?? '-' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $typeBadgeClass }}">
                                        {{ $coaType }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $lineCount }} transaksi</p>
                            </div>
                        </div>

                        {{-- Balance Summary --}}
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
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                                        <th class="px-6 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Tanggal</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Referensi</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Debit</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                    @foreach($group['lines'] as $line)
                                        <tr class="stripe-row hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-colors">
                                            <td class="px-6 py-2.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($line->date)->format('d/m/Y') }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                @if($line->reference)
                                                    <span class="inline-flex items-center font-mono text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded">
                                                        {{ $line->reference }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
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
                                {{-- Subtotal Row --}}
                                <tfoot>
                                    <tr class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
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
            <div class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                <span class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">Grand Total</span>
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
            <div class="w-16 h-16 rounded-2xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center mb-5 text-primary-500 dark:text-primary-400">
                <x-heroicon-o-magnifying-glass-plus class="w-8 h-8" />
            </div>
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">Belum ada laporan ditampilkan</h3>
            <p class="text-sm text-gray-400 dark:text-gray-500 max-w-sm">
                Atur filter di atas sesuai kebutuhan, kemudian klik <span class="font-semibold text-primary-600 dark:text-primary-400">Tampilkan Laporan</span> untuk memuat data drill down.
            </p>
        </div>
    @endif

</div>
</x-filament-panels::page>
