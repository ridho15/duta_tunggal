@php
    $data = $this->getIncomeStatementData();
    $comparison = $this->getComparisonData();
    $cabangOptions = $this->getCabangOptions();
    
    // Helper function to filter accounts based on display options
    $filterAccounts = function($accounts) {
        if ($this->show_only_totals) {
            return collect([]);
        }
        
        return $accounts->filter(function($account) {
            // Filter zero balance
            if (!$this->show_zero_balance && $account['balance'] == 0) {
                return false;
            }
            
            // Filter parent/child accounts
            $hasParent = isset($account['parent_id']) && $account['parent_id'] != null;
            
            if ($hasParent && !$this->show_child_accounts) {
                return false;
            }
            
            if (!$hasParent && !$this->show_parent_accounts) {
                return false;
            }
            
            return true;
        });
    };
@endphp

<div class="space-y-6">
    {{-- Filter Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Filter Periode</h3>
        
        <form wire:submit.prevent="generateReport" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Dari Tanggal</label>
                    <input 
                        type="date" 
                        wire:model="start_date"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Sampai Tanggal</label>
                    <input 
                        type="date" 
                        wire:model="end_date"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Cabang (Opsional)</label>
                    <select 
                        wire:model="cabang_id"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Semua Cabang</option>
                        @foreach($cabangOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button 
                        type="submit"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md"
                    >
                        Tampilkan Laporan
                    </button>
                </div>
            </div>

            {{-- Comparison Toggle --}}
            <div class="flex items-center space-x-2">
                <input 
                    type="checkbox" 
                    wire:model="show_comparison" 
                    id="show_comparison"
                    class="rounded border-gray-300"
                />
                <label for="show_comparison" class="text-sm font-medium">
                    Tampilkan Perbandingan Periode
                </label>
            </div>

            @if($show_comparison)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded">
                    <div>
                        <label class="block text-sm font-medium mb-1">Periode Pembanding - Dari</label>
                        <input 
                            type="date" 
                            wire:model="comparison_start_date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Periode Pembanding - Sampai</label>
                        <input 
                            type="date" 
                            wire:model="comparison_end_date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>
            @endif

            {{-- Display Options --}}
            <div class="border-t pt-4 mt-4">
                <h4 class="text-sm font-semibold mb-3">📊 Opsi Tampilan Laporan</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="flex items-center space-x-2">
                        <input 
                            type="checkbox" 
                            wire:model="show_only_totals" 
                            id="show_only_totals"
                            class="rounded border-gray-300"
                        />
                        <label for="show_only_totals" class="text-xs">Hanya Total</label>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <input 
                            type="checkbox" 
                            wire:model="show_parent_accounts" 
                            id="show_parent_accounts"
                            class="rounded border-gray-300"
                            {{ $show_only_totals ? 'disabled' : '' }}
                        />
                        <label for="show_parent_accounts" class="text-xs {{ $show_only_totals ? 'text-gray-400' : '' }}">
                            Akun Induk
                        </label>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <input 
                            type="checkbox" 
                            wire:model="show_child_accounts" 
                            id="show_child_accounts"
                            class="rounded border-gray-300"
                            {{ $show_only_totals ? 'disabled' : '' }}
                        />
                        <label for="show_child_accounts" class="text-xs {{ $show_only_totals ? 'text-gray-400' : '' }}">
                            Akun Anak
                        </label>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <input 
                            type="checkbox" 
                            wire:model="show_zero_balance" 
                            id="show_zero_balance"
                            class="rounded border-gray-300"
                        />
                        <label for="show_zero_balance" class="text-xs">Saldo Nol</label>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Summary Cards (5 tingkat) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        {{-- 1. Sales Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Pendapatan Usaha</p>
                    <p class="text-lg font-bold text-green-600">
                        Rp {{ number_format($data['sales_revenue']['total'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $data['sales_revenue']['accounts']->count() }} akun</p>
                </div>
                <x-heroicon-o-currency-dollar class="w-8 h-8 text-green-500" />
            </div>
        </div>
        <div class="space-x-2">
            <a
                href="/admin/reports/profit-and-losses"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm"
                title="Buka Profit &amp; Loss (halaman tersembunyi)"
            >
                📈 Profit & Loss
            </a>
        </div>

        {{-- 2. Gross Profit --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Laba Kotor</p>
                    <p class="text-lg font-bold {{ $data['gross_profit'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                        Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">Margin: {{ number_format($data['gross_profit_margin'], 1) }}%</p>
                </div>
                <x-heroicon-o-chart-bar class="w-8 h-8 text-blue-500" />
            </div>
        </div>

        {{-- 3. Operating Profit --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Laba Operasional</p>
                    <p class="text-lg font-bold {{ $data['operating_profit'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                        Rp {{ number_format($data['operating_profit'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">Margin: {{ number_format($data['operating_profit_margin'], 1) }}%</p>
                </div>
                <x-heroicon-o-presentation-chart-line class="w-8 h-8 text-indigo-500" />
            </div>
        </div>

        {{-- 4. Profit Before Tax --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Laba Sebelum Pajak</p>
                    <p class="text-lg font-bold {{ $data['profit_before_tax'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                        Rp {{ number_format($data['profit_before_tax'], 0, ',', '.') }}
                    </p>
                </div>
                <x-heroicon-o-document-text class="w-8 h-8 text-purple-500" />
            </div>
        </div>

        {{-- 5. Net Profit --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Laba Bersih</p>
                    <p class="text-lg font-bold {{ $data['is_profit'] ? 'text-blue-600' : 'text-orange-600' }}">
                        Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs {{ $data['is_profit'] ? 'text-blue-600' : 'text-orange-600' }}">
                        {{ $data['is_profit'] ? 'Laba' : 'Rugi' }} ({{ number_format($data['net_profit_margin'], 1) }}%)
                    </p>
                </div>
                <x-heroicon-o-trophy class="w-8 h-8 {{ $data['is_profit'] ? 'text-blue-500' : 'text-orange-500' }}" />
            </div>
        </div>
    </div>

    {{-- Income Statement Table (Struktur Lengkap 5 Tingkat) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Laporan Laba Rugi / Income Statement</h2>
            <div class="space-x-2">
                <button 
                    onclick="window.print()" 
                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm"
                >
                    🖨️ Print
                </button>
                <button 
                    wire:click="exportPdf" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm"
                >
                    📄 Export PDF
                </button>
                <button 
                    wire:click="exportExcel" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm"
                >
                    📊 Export Excel
                </button>
            </div>
        </div>

        @include('filament.pages.partials.income-statement-table')
    </div>


    {{-- Drill-Down Modal --}}
    @if($show_drill_down && $selected_account_id)
        @php
            $drillDownData = $this->getDrillDownData();
        @endphp
        
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" 
             wire:click="closeDrillDown">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden"
                 wire:click.stop>
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold">Detail Transaksi Akun</h3>
                            @if($drillDownData && $drillDownData['account'])
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $drillDownData['account']['code'] }} - {{ $drillDownData['account']['name'] }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Periode: {{ \Carbon\Carbon::parse($drillDownData['period']['start_date'])->format('d M Y') }} - 
                                    {{ \Carbon\Carbon::parse($drillDownData['period']['end_date'])->format('d M Y') }}
                                </p>
                            @endif
                        </div>
                        <button 
                            wire:click="closeDrillDown"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                    @if($drillDownData && $drillDownData['entries']->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-b-2 border-gray-300 dark:border-gray-600">
                                    <tr>
                                        <th class="text-left py-2 px-4">Tanggal</th>
                                        <th class="text-left py-2 px-4">Referensi</th>
                                        <th class="text-left py-2 px-4">Keterangan</th>
                                        <th class="text-left py-2 px-4">Cabang</th>
                                        <th class="text-right py-2 px-4">Debit</th>
                                        <th class="text-right py-2 px-4">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($drillDownData['entries'] as $entry)
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="py-2 px-4">{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                                            <td class="py-2 px-4">{{ $entry->reference ?? '-' }}</td>
                                            <td class="py-2 px-4">{{ $entry->description ?? '-' }}</td>
                                            <td class="py-2 px-4">{{ $entry->cabang->nama ?? '-' }}</td>
                                            <td class="text-right py-2 px-4">{{ $entry->debit > 0 ? number_format($entry->debit, 0, ',', '.') : '-' }}</td>
                                            <td class="text-right py-2 px-4">{{ $entry->credit > 0 ? number_format($entry->credit, 0, ',', '.') : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                                    <tr>
                                        <td colspan="4" class="py-2 px-4 text-right">TOTAL:</td>
                                        <td class="text-right py-2 px-4">{{ number_format($drillDownData['total_debit'], 0, ',', '.') }}</td>
                                        <td class="text-right py-2 px-4">{{ number_format($drillDownData['total_credit'], 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="bg-blue-50 dark:bg-blue-900/20">
                                        <td colspan="4" class="py-2 px-4 text-right">SALDO ({{ $drillDownData['account']['type'] }}):</td>
                                        <td colspan="2" class="text-right py-2 px-4 text-blue-600">
                                            {{ number_format($drillDownData['balance'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <p class="text-center text-gray-500 py-8">Tidak ada transaksi untuk akun ini dalam periode yang dipilih.</p>
                    @endif
                </div>
                
                <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button 
                        wire:click="closeDrillDown"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Comparison Section --}}
    @if($show_comparison && $comparison)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4">Perbandingan Periode</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach([
                    ['label' => 'Pendapatan Usaha', 'key' => 'sales_revenue'],
                    ['label' => 'Laba Kotor', 'key' => 'gross_profit'],
                    ['label' => 'Laba Operasional', 'key' => 'operating_profit'],
                    ['label' => 'Laba Sebelum Pajak', 'key' => 'profit_before_tax'],
                    ['label' => 'Laba Bersih', 'key' => 'net_profit'],
                ] as $metric)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $metric['label'] }}</p>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-bold">
                                    Rp {{ number_format($comparison['changes'][$metric['key']]['amount'], 0, ',', '.') }}
                                </p>
                                <p class="text-xs text-gray-500">Perubahan</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold {{ $comparison['changes'][$metric['key']]['percentage'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $comparison['changes'][$metric['key']]['percentage'] >= 0 ? '+' : '' }}{{ number_format($comparison['changes'][$metric['key']]['percentage'], 1) }}%
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('styles')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                font-size: 12px;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
@endpush
