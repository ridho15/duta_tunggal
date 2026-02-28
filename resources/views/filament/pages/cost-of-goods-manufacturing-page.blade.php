<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Section --}}
        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Laporan Harga Pokok Produksi</h2>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cabang</label>
                    <select wire:model="cabang_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="">-- Semua Cabang --</option>
                        @foreach(\App\Models\Cabang::all() as $cabang)
                            <option value="{{ $cabang->id }}">{{ $cabang->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Produk</label>
                    <select wire:model="product_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                        <option value="">-- Semua Produk --</option>
                        @foreach($this->productOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($this->showPreview)
            @php $data = $this->getCogmData(); @endphp

            {{-- COGM Statement --}}
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="px-6 py-4 bg-purple-600 dark:bg-purple-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">Laporan Harga Pokok Produksi (COGM)</h3>
                    <span class="text-sm text-purple-100">{{ $data['period'] ?? '' }}</span>
                </div>

                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300">Saldo Awal WIP (Barang Dalam Proses)</td>
                            <td class="px-6 py-3 text-right text-blue-700 dark:text-blue-400">Rp {{ number_format($data['opening_wip'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <td class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Biaya Produksi Periode Ini:</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 pl-10 text-gray-600 dark:text-gray-400">Bahan Baku Terpakai (Raw Material Used)</td>
                            <td class="px-6 py-3 text-right">Rp {{ number_format($data['raw_material_used'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 pl-10 text-gray-600 dark:text-gray-400">Biaya Tenaga Kerja Langsung (Direct Labor)</td>
                            <td class="px-6 py-3 text-right">Rp {{ number_format($data['labor_cost'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 pl-10 text-gray-600 dark:text-gray-400">Biaya Overhead Pabrik (Manufacturing Overhead)</td>
                            <td class="px-6 py-3 text-right">Rp {{ number_format($data['overhead'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @php $totalCostAdded = ($data['raw_material_used'] ?? 0) + ($data['labor_cost'] ?? 0) + ($data['overhead'] ?? 0); @endphp
                        <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300">Total Biaya Produksi Ditambahkan</td>
                            <td class="px-6 py-3 text-right text-purple-700 dark:text-purple-400">Rp {{ number_format($totalCostAdded, 0, ',', '.') }}</td>
                        </tr>
                        @php $totalWipAvailable = ($data['opening_wip'] ?? 0) + $totalCostAdded; @endphp
                        <tr>
                            <td class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Total WIP Tersedia</td>
                            <td class="px-6 py-3 text-right font-semibold">Rp {{ number_format($totalWipAvailable, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 pl-10 text-gray-600 dark:text-gray-400">Dikurangi: Saldo Akhir WIP</td>
                            <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">(Rp {{ number_format($data['closing_wip'] ?? 0, 0, ',', '.') }})</td>
                        </tr>
                        <tr class="bg-purple-50 dark:bg-purple-900/20 font-bold text-base border-t-2 border-purple-200 dark:border-purple-700">
                            <td class="px-6 py-4 text-purple-800 dark:text-purple-200">💰 Harga Pokok Produksi (COGM)</td>
                            <td class="px-6 py-4 text-right text-purple-700 dark:text-purple-300">Rp {{ number_format($data['cogm'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Manufacturing Orders Detail --}}
            @if(($data['mo_count'] ?? 0) > 0)
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Manufacturing Orders ({{ $data['mo_count'] }} MO)</h3>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">MO Number</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Produk</th>
                            <th class="px-4 py-2 text-right text-xs text-gray-500">Qty</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach(($data['orders'] ?? []) as $mo)
                        <tr>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $mo->mo_number ?? $mo->id }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ optional(optional($mo->productionPlan)->product)->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($mo->quantity ?? 0) }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    {{ $mo->status === 'completed' ? 'bg-green-100 text-green-800' : ($mo->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst(str_replace('_', ' ', $mo->status ?? 'draft')) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
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
