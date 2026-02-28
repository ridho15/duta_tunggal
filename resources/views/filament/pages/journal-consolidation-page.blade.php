<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Section --}}
        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Konsolidasi Jurnal</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                    <input type="date" wire:model="start_date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Akhir</label>
                    <input type="date" wire:model="end_date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Jurnal</label>
                    <select wire:model="journal_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="">-- Semua Tipe --</option>
                        @foreach($this->journalTypeOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tampilan</label>
                    <select wire:model="group_by_branch" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="1">Dikelompokkan per Cabang</option>
                        <option value="0">Konsolidasi Semua Cabang</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cabang (kosongkan untuk semua)</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->branchOptions as $id => $name)
                    <label class="flex items-center gap-1 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="branch_ids" value="{{ $id }}" class="rounded border-gray-300">
                        <span class="text-gray-700 dark:text-gray-300">{{ $name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        @if($this->showPreview)
            @php $data = $this->getConsolidationData(); @endphp

            {{-- Header Summary --}}
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-center">
                    <p class="text-xs text-blue-600 dark:text-blue-400">Total Entri</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($data['count'] ?? 0) }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 text-center">
                    <p class="text-xs text-green-600 dark:text-green-400">Total Debit</p>
                    <p class="text-lg font-bold text-green-700 dark:text-green-300">Rp {{ number_format($data['total_debit'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-4 text-center">
                    <p class="text-xs text-red-600 dark:text-red-400">Total Kredit</p>
                    <p class="text-lg font-bold text-red-700 dark:text-red-300">Rp {{ number_format($data['total_credit'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="{{ ($data['balanced'] ?? false) ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }} rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-600 dark:text-gray-400">Status</p>
                    <p class="text-base font-bold {{ ($data['balanced'] ?? false) ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                        {{ ($data['balanced'] ?? false) ? '✅ Seimbang' : '⚠️ Tidak Seimbang' }}
                    </p>
                </div>
            </div>

            {{-- COA Summary Table --}}
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Ringkasan per Akun ({{ $data['period'] ?? '' }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Kode Akun</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Nama Akun</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Tipe</th>
                                <th class="px-4 py-2 text-right text-xs text-gray-500">Total Debit</th>
                                <th class="px-4 py-2 text-right text-xs text-gray-500">Total Kredit</th>
                                <th class="px-4 py-2 text-right text-xs text-gray-500">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach(($data['coa_summary'] ?? []) as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300 font-mono text-xs">{{ optional($row['coa'])['code'] ?? '-' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ optional($row['coa'])['name'] ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        {{ optional($row['coa'])['type'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-green-700 dark:text-green-400">Rp {{ number_format($row['total_debit'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-red-700 dark:text-red-400">Rp {{ number_format($row['total_credit'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $row['balance'] >= 0 ? 'text-gray-800 dark:text-gray-200' : 'text-red-600 dark:text-red-400' }}">
                                    Rp {{ number_format(abs($row['balance']), 0, ',', '.') }}
                                    {{ $row['balance'] < 0 ? '(K)' : '' }}
                                </td>
                            </tr>
                            @endforeach
                            {{-- Total Row --}}
                            <tr class="bg-gray-100 dark:bg-gray-700 font-bold border-t-2 border-gray-300 dark:border-gray-600">
                                <td colspan="3" class="px-4 py-3 text-gray-700 dark:text-gray-300">TOTAL KONSOLIDASI</td>
                                <td class="px-4 py-3 text-right text-green-700 dark:text-green-400">Rp {{ number_format($data['total_debit'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-red-700 dark:text-red-400">Rp {{ number_format($data['total_credit'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format(abs(($data['total_debit'] ?? 0) - ($data['total_credit'] ?? 0)), 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Per Branch Journal Detail --}}
            @foreach(($data['grouped'] ?? []) as $group)
            <details class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden" open>
                <summary class="px-6 py-4 cursor-pointer flex items-center justify-between border-b border-gray-100 dark:border-gray-800">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                        🏢 {{ $group['cabang_name'] }} 
                        <span class="text-sm font-normal text-gray-500">({{ count($group['entries']) }} entri)</span>
                    </h3>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        D: Rp {{ number_format($group['total_debit'], 0, ',', '.') }} |
                        K: Rp {{ number_format($group['total_credit'], 0, ',', '.') }}
                    </span>
                </summary>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Referensi</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Akun</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Keterangan</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Tipe</th>
                                <th class="px-4 py-2 text-right text-xs text-gray-500">Debit</th>
                                <th class="px-4 py-2 text-right text-xs text-gray-500">Kredit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($group['entries'] as $entry)
                            <tr class="{{ $entry->is_reversal ? 'bg-orange-50 dark:bg-orange-900/10' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-1.5 text-gray-700 dark:text-gray-300 font-mono text-xs whitespace-nowrap">
                                    {{ $entry->reference }}
                                    @if($entry->is_reversal)<span class="ml-1 text-orange-600 text-xs">[REV]</span>@endif
                                </td>
                                <td class="px-4 py-1.5 text-gray-600 dark:text-gray-400 text-xs">
                                    {{ optional($entry->coa)->code ?? '' }} {{ optional($entry->coa)->name ?? '-' }}
                                </td>
                                <td class="px-4 py-1.5 text-gray-500 dark:text-gray-400 max-w-xs truncate text-xs">{{ $entry->description }}</td>
                                <td class="px-4 py-1.5 text-xs text-gray-500">{{ $entry->journal_type }}</td>
                                <td class="px-4 py-1.5 text-right text-green-700 dark:text-green-400">{{ $entry->debit > 0 ? 'Rp '.number_format($entry->debit, 0, ',', '.') : '' }}</td>
                                <td class="px-4 py-1.5 text-right text-red-700 dark:text-red-400">{{ $entry->credit > 0 ? 'Rp '.number_format($entry->credit, 0, ',', '.') : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            @endforeach

        @else
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-funnel class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Konsolidasi</strong> untuk melihat data.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
