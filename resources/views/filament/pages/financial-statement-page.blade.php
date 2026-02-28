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
                <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">
                    <div>
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300">ASET</div>
                        <div class="px-6 py-3 text-right font-bold text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format($bs['total_assets'] ?? 0, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300">LIABILITAS + EKUITAS</div>
                        <div class="px-6 py-3 text-right font-bold text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format(($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0), 0, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-center text-sm text-emerald-700 dark:text-emerald-300">
                    @if(abs(($bs['total_assets'] ?? 0) - (($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0))) < 1)
                        ✅ Neraca Seimbang (Balanced)
                    @else
                        ⚠️ Neraca Tidak Seimbang — selisih: Rp {{ number_format(abs(($bs['total_assets'] ?? 0) - (($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0))), 0, ',', '.') }}
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
