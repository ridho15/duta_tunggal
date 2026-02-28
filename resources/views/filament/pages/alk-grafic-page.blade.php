<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Section --}}
        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Analisis Laporan Keuangan</h2>
            <div class="grid gap-4 md:grid-cols-3">
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
            @php $data = $this->getAlkData(); @endphp

            {{-- Key Financial Ratios --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @php
                    $ratios = [
                        ['label' => 'Current Ratio', 'value' => $data['current_ratio'], 'unit' => 'x', 'good' => fn($v) => $v >= 1.5, 'hint' => '≥ 1.5 baik'],
                        ['label' => 'Debt to Equity', 'value' => $data['debt_to_equity'], 'unit' => 'x', 'good' => fn($v) => $v <= 1, 'hint' => '≤ 1 baik'],
                        ['label' => 'ROA', 'value' => $data['roa'], 'unit' => '%', 'good' => fn($v) => $v > 0, 'hint' => '> 0% baik'],
                        ['label' => 'ROE', 'value' => $data['roe'], 'unit' => '%', 'good' => fn($v) => $v > 0, 'hint' => '> 0% baik'],
                        ['label' => 'Profit Margin', 'value' => $data['profit_margin'], 'unit' => '%', 'good' => fn($v) => $v > 0, 'hint' => '> 0% baik'],
                    ];
                @endphp
                @foreach($ratios as $ratio)
                <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $ratio['label'] }}</p>
                    @if($ratio['value'] !== null)
                        <p class="text-2xl font-bold {{ $ratio['good']($ratio['value']) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format($ratio['value'], 2) }}{{ $ratio['unit'] }}
                        </p>
                    @else
                        <p class="text-2xl font-bold text-gray-400">N/A</p>
                    @endif
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $ratio['hint'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- Overview Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4">
                    <p class="text-xs text-blue-600 dark:text-blue-400">Total Aset</p>
                    <p class="text-lg font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($data['total_assets'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-4">
                    <p class="text-xs text-red-600 dark:text-red-400">Total Liabilitas</p>
                    <p class="text-lg font-bold text-red-700 dark:text-red-300">Rp {{ number_format($data['total_liabilities'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4">
                    <p class="text-xs text-green-600 dark:text-green-400">Total Ekuitas</p>
                    <p class="text-lg font-bold text-green-700 dark:text-green-300">Rp {{ number_format($data['total_equity'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4">
                    <p class="text-xs text-purple-600 dark:text-purple-400">Laba Bersih</p>
                    <p class="text-lg font-bold {{ $data['net_profit'] >= 0 ? 'text-purple-700 dark:text-purple-300' : 'text-red-700 dark:text-red-300' }}">
                        Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- Monthly Trend Chart --}}
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Tren Pendapatan & Laba (6 Bulan Terakhir)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Bulan</th>
                                <th class="px-4 py-2 text-right text-xs text-green-600">Pendapatan</th>
                                <th class="px-4 py-2 text-right text-xs text-red-600">Pengeluaran</th>
                                <th class="px-4 py-2 text-right text-xs text-blue-600">Laba/Rugi</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Grafik</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $maxRev = max(array_column($data['trend'], 'revenue') ?: [1]); @endphp
                            @foreach($data['trend'] as $row)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300 font-medium">{{ $row['month'] }}</td>
                                <td class="px-4 py-2 text-right text-green-700 dark:text-green-400">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-red-700 dark:text-red-400">Rp {{ number_format($row['expense'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $row['profit'] >= 0 ? 'text-blue-700 dark:text-blue-400' : 'text-red-700 dark:text-red-400' }}">
                                    Rp {{ number_format($row['profit'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 w-48">
                                    @php $pct = $maxRev > 0 ? min(100, ($row['revenue'] / $maxRev) * 100) : 0; @endphp
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    @php $expPct = $maxRev > 0 ? min(100, ($row['expense'] / $maxRev) * 100) : 0; @endphp
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1 mt-1">
                                        <div class="bg-red-400 h-1 rounded-full" style="width: {{ $expPct }}%"></div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-2 bg-green-500 rounded"></span> Pendapatan</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-1 bg-red-400 rounded"></span> Pengeluaran</span>
                </div>
            </div>

        @else
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-funnel class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Analisis</strong> untuk melihat data.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
