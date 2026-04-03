<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Section --}}
        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Laporan Keuangan</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Laporan</label>
                    <select wire:model="statement_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="all">Semua (P&L + Balance Sheet)</option>
                        <option value="pl">Laba Rugi (P&L)</option>
                        <option value="bs">Neraca (Balance Sheet)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                    <input type="date" wire:model="start_date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Akhir</label>
                    <input type="date" wire:model="end_date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cabang</label>
                    <select wire:model="cabang_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="">-- Semua Cabang --</option>
                        @foreach(\App\Models\Cabang::all() as $cabang)
                            <option value="{{ $cabang->id }}">{{ $cabang->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($this->showPreview)
            @php $data = $this->getStatementData(); @endphp

            {{-- P&L Section --}}
            @if(isset($data['pl']))
            @php $pl = $data['pl']; @endphp
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="px-6 py-4 bg-blue-600 dark:bg-blue-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">Laporan Laba Rugi (Income Statement)</h3>
                    <span class="text-sm text-blue-100">{{ $pl['period'] }}</span>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <td class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Pendapatan (Revenue)</td>
                            <td class="px-6 py-3 text-right font-semibold text-green-700 dark:text-green-400">Rp {{ number_format($pl['revenue'], 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-gray-600 dark:text-gray-400 pl-10">Harga Pokok Penjualan (HPP / COGS)</td>
                            <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">(Rp {{ number_format($pl['cogs'], 0, ',', '.') }})</td>
                        </tr>
                        <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300">Laba Kotor (Gross Profit)</td>
                            <td class="px-6 py-3 text-right {{ $pl['gross_profit'] >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">Rp {{ number_format($pl['gross_profit'], 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-gray-600 dark:text-gray-400 pl-10">Beban Operasional (OPEX)</td>
                            <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">(Rp {{ number_format($pl['opex'], 0, ',', '.') }})</td>
                        </tr>
                        <tr class="bg-blue-50 dark:bg-blue-900/20 font-bold text-base border-t-2 border-blue-200 dark:border-blue-700">
                            <td class="px-6 py-4 text-blue-800 dark:text-blue-200">Laba / Rugi Bersih (Net Profit)</td>
                            <td class="px-6 py-4 text-right {{ $pl['net_profit'] >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-red-700 dark:text-red-400' }}">Rp {{ number_format($pl['net_profit'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Balance Sheet Section --}}
            @if(isset($data['bs']))
            @php $bs = $data['bs']; @endphp
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="px-6 py-4 bg-emerald-600 dark:bg-emerald-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">Neraca (Balance Sheet)</h3>
                    <span class="text-sm text-emerald-100">Per {{ \Carbon\Carbon::parse($this->end_date)->format('d M Y') }}</span>
                </div>

                {{-- Two-column layout: Assets | Liabilities & Equity --}}
                <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">

                    {{-- ===== LEFT: ASET ===== --}}
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-6 py-3 text-left font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide text-xs" colspan="2">ASET</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            {{-- Current Assets --}}
                            @if(!empty($bs['current_assets']['accounts']))
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Aset Lancar</td>
                            </tr>
                            @foreach($bs['current_assets']['accounts'] as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                            <tr class="bg-emerald-50 dark:bg-emerald-900/10 font-semibold">
                                <td class="px-6 py-2 text-emerald-700 dark:text-emerald-400 pl-6">Total Aset Lancar</td>
                                <td class="px-6 py-2 text-right text-emerald-700 dark:text-emerald-400">Rp {{ number_format($bs['current_assets']['total'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @endif

                            {{-- Fixed Assets --}}
                            @if(!empty($bs['fixed_assets']['accounts']))
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Aset Tetap</td>
                            </tr>
                            @foreach($bs['fixed_assets']['accounts'] as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                            <tr class="bg-emerald-50 dark:bg-emerald-900/10 font-semibold">
                                <td class="px-6 py-2 text-emerald-700 dark:text-emerald-400 pl-6">Total Aset Tetap</td>
                                <td class="px-6 py-2 text-right text-emerald-700 dark:text-emerald-400">Rp {{ number_format($bs['fixed_assets']['total'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @endif

                            {{-- Contra Assets --}}
                            @if(!empty($bs['contra_assets']['accounts']))
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Akumulasi Penyusutan / Contra</td>
                            </tr>
                            @foreach($bs['contra_assets']['accounts'] as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-red-600 dark:text-red-400 whitespace-nowrap">(Rp {{ number_format($acc->balance, 0, ',', '.') }})</td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="bg-emerald-600 dark:bg-emerald-800 font-bold">
                                <td class="px-6 py-3 text-white">TOTAL ASET</td>
                                <td class="px-6 py-3 text-right text-white">Rp {{ number_format($bs['total_assets'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- ===== RIGHT: LIABILITAS + EKUITAS ===== --}}
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-6 py-3 text-left font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide text-xs" colspan="2">LIABILITAS &amp; EKUITAS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            {{-- Current Liabilities --}}
                            @if(!empty($bs['current_liabilities']['accounts']))
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Liabilitas Jangka Pendek</td>
                            </tr>
                            @foreach($bs['current_liabilities']['accounts'] as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                            <tr class="bg-yellow-50 dark:bg-yellow-900/10 font-semibold">
                                <td class="px-6 py-2 text-yellow-700 dark:text-yellow-400 pl-6">Total Liabilitas Jangka Pendek</td>
                                <td class="px-6 py-2 text-right text-yellow-700 dark:text-yellow-400">Rp {{ number_format($bs['current_liabilities']['total'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @endif

                            {{-- Long-term Liabilities --}}
                            @if(!empty($bs['long_term_liabilities']['accounts']))
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Liabilitas Jangka Panjang</td>
                            </tr>
                            @foreach($bs['long_term_liabilities']['accounts'] as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                            <tr class="bg-yellow-50 dark:bg-yellow-900/10 font-semibold">
                                <td class="px-6 py-2 text-yellow-700 dark:text-yellow-400 pl-6">Total Liabilitas Jangka Panjang</td>
                                <td class="px-6 py-2 text-right text-yellow-700 dark:text-yellow-400">Rp {{ number_format($bs['long_term_liabilities']['total'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @endif

                            {{-- Equity --}}
                            <tr class="bg-gray-50 dark:bg-gray-700/40">
                                <td class="px-6 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" colspan="2">Ekuitas</td>
                            </tr>
                            @foreach(($bs['equity']['accounts'] ?? []) as $acc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">
                                    <span class="font-mono text-xs text-gray-400 mr-1">{{ $acc->code }}</span>{{ $acc->name }}
                                </td>
                                <td class="px-6 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                            @if(($bs['retained_earnings'] ?? 0) != 0)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-2 text-gray-600 dark:text-gray-400 pl-10">Laba Ditahan (Retained Earnings)</td>
                                <td class="px-6 py-2 text-right {{ ($bs['retained_earnings'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }} whitespace-nowrap">
                                    Rp {{ number_format($bs['retained_earnings'], 0, ',', '.') }}
                                </td>
                            </tr>
                            @endif
                            <tr class="bg-blue-50 dark:bg-blue-900/10 font-semibold">
                                <td class="px-6 py-2 text-blue-700 dark:text-blue-400 pl-6">Total Ekuitas</td>
                                <td class="px-6 py-2 text-right text-blue-700 dark:text-blue-400">Rp {{ number_format($bs['total_equity'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-emerald-600 dark:bg-emerald-800 font-bold">
                                <td class="px-6 py-3 text-white">TOTAL LIABILITAS + EKUITAS</td>
                                <td class="px-6 py-3 text-right text-white">Rp {{ number_format(($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Balance check row --}}
                <div class="px-6 py-3 {{ ($bs['is_balanced'] ?? false) ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' }} text-center text-sm font-medium">
                    @if($bs['is_balanced'] ?? false)
                        ✅ Neraca Seimbang (Balanced)
                    @else
                        ⚠️ Neraca Tidak Seimbang — selisih: Rp {{ number_format(abs($bs['difference'] ?? 0), 0, ',', '.') }}
                    @endif
                </div>
                <div class="px-6 py-3 text-center">
                    <a href="/admin/reports/balance-sheets" class="text-sm text-blue-600 hover:underline">→ Lihat Balance Sheet Detail</a>
                </div>
            </div>
            @endif

        @else
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-funnel class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
<script>
window.addEventListener('open-report-preview', event => {
    const url = event.detail?.url ?? event.detail?.[0]?.url;

    if (url) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>
