<x-filament::page>
    <div class="space-y-6">
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        @if($this->showPreview)

        @php($report = $this->getReportData())
        @php($raw = $report['raw_materials'])
        @php($overhead = $report['overhead'])
        @php($wip = $report['wip'])

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Periode</div>
                <div class="text-lg font-semibold">
                    {{ $report['period']['start'] }} &mdash; {{ $report['period']['end'] }}
                </div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Bahan Baku yang Digunakan</div>
                <div class="text-lg font-semibold">Rp {{ number_format($raw['used'], 2, ',', '.') }}</div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Total Biaya Produksi</div>
                <div class="text-lg font-semibold">Rp {{ number_format($report['production_cost'], 2, ',', '.') }}</div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Harga Pokok Produksi</div>
                <div class="text-lg font-semibold">Rp {{ number_format($report['cogm'], 2, ',', '.') }}</div>
            </div>
            @php($selectedBranches = $this->getSelectedBranchNames())
            @if(!empty($selectedBranches))
                <div class="p-4 border rounded shadow-sm md:col-span-2">
                    <div class="text-sm text-gray-500">Cabang Terpilih</div>
                    <div class="text-lg font-semibold">{{ implode(', ', $selectedBranches) }}</div>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto border rounded-lg shadow">
            <table class="min-w-full divide-y">
                <thead>
                    <tr class="bg-gray-50 text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-sm">
                    <tr>
                        <td class="px-4 py-3">Persediaan Awal Bahan Baku</td>
                        <td class="px-4 py-3 text-right">{{ number_format($raw['opening'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">+ Pembelian Bahan Baku</td>
                        <td class="px-4 py-3 text-right">{{ number_format($raw['purchases'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3">= Total Bahan Baku Tersedia</td>
                        <td class="px-4 py-3 text-right">{{ number_format($raw['available'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">- Persediaan Akhir Bahan Baku</td>
                        <td class="px-4 py-3 text-right">({{ number_format($raw['closing'], 2, ',', '.') }})</td>
                    </tr>
                    <tr class="font-semibold">
                        <td class="px-4 py-3">= Bahan Baku yang Digunakan</td>
                        <td class="px-4 py-3 text-right">{{ number_format($raw['used'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">+ Biaya Tenaga Kerja Langsung</td>
                        <td class="px-4 py-3 text-right">{{ number_format($report['direct_labor'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 align-top">
                            <div>+ Biaya Overhead Pabrik</div>
                            <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                @foreach($overhead['items'] as $item)
                                    <li class="flex justify-between">
                                        <span>{{ $item['label'] }}</span>
                                        <span>Rp {{ number_format($item['amount'], 2, ',', '.') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format($overhead['total'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3">= Total Biaya Produksi</td>
                        <td class="px-4 py-3 text-right">{{ number_format($report['production_cost'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">+ Persediaan Awal Barang Dalam Proses (WIP)</td>
                        <td class="px-4 py-3 text-right">{{ number_format($wip['opening'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">- Persediaan Akhir Barang Dalam Proses (WIP)</td>
                        <td class="px-4 py-3 text-right">({{ number_format($wip['closing'], 2, ',', '.') }})</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td class="px-4 py-4">= Harga Pokok Produksi (Cost of Goods Manufactured)</td>
                        <td class="px-4 py-4 text-right">{{ number_format($report['cogm'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-wrap gap-2 justify-end">
            <x-filament::button wire:click="$refresh">Refresh</x-filament::button>
        </div>

        @else

        <div class="mt-6 p-8 border rounded bg-gray-50 text-center text-gray-500">
            <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto mb-3 text-gray-400" style="width: 100px; height: 100px;"/>
            <p class="text-lg font-medium" style="font-size: 11pt">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
        </div>

        @endif
    </div>
</x-filament::page>
