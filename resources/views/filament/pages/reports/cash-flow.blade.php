<x-filament::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php($report = $this->getReportData())

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Periode</div>
                <div class="text-lg font-semibold">
                    {{ $report['period']['start'] }} &mdash; {{ $report['period']['end'] }}
                </div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Saldo Awal Kas</div>
                <div class="text-lg font-semibold">Rp {{ number_format($report['opening_balance'], 2, ',', '.') }}</div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Kenaikan (Penurunan) Bersih</div>
                <div class="text-lg font-semibold {{ $report['net_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    Rp {{ number_format($report['net_change'], 2, ',', '.') }}
                </div>
            </div>
            <div class="p-4 border rounded shadow-sm">
                <div class="text-sm text-gray-500">Saldo Akhir Kas</div>
                <div class="text-lg font-semibold">Rp {{ number_format($report['closing_balance'], 2, ',', '.') }}</div>
            </div>
            @php($selectedBranches = $this->getSelectedBranchNames())
            @if(!empty($selectedBranches))
                <div class="p-4 border rounded shadow-sm md:col-span-2">
                    <div class="text-sm text-gray-500">Cabang Terpilih</div>
                    <div class="text-lg font-semibold">{{ implode(', ', $selectedBranches) }}</div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @foreach($report['sections'] ?? [] as $section)
                <div class="border rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-gray-50">
                        <h2 class="text-lg font-semibold">{{ $section['label'] ?? 'Unknown Section' }}</h2>
                    </div>
                    <div class="p-4">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2">Deskripsi</th>
                                    <th class="py-2 text-right">Jumlah (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($section['items'] ?? [] as $item)
                                    <tr>
                                        <td class="py-2 align-top">
                                            <div class="font-medium">{{ $item['label'] ?? 'Unknown Item' }}</div>
                                            @if(!empty($item['metadata']['sources'] ?? []))
                                                <div class="text-xs text-gray-500">Sumber: {{ implode(', ', $item['metadata']['sources']) }}</div>
                                            @endif
                                            @if(!empty($item['metadata']['asset_adjustment'] ?? null))
                                                <div class="text-xs text-blue-600">Penyesuaian aset: Rp {{ number_format($item['metadata']['asset_adjustment'], 2, ',', '.') }}</div>
                                            @endif
                                            @if(!empty($item['metadata']['detail'] ?? []))
                                                <details class="mt-1 text-xs text-gray-500">
                                                    <summary class="cursor-pointer select-none">Detail Penerimaan</summary>
                                                    <div class="mt-1 space-y-1">
                                                        @foreach($item['metadata']['detail'] as $detail)
                                                            <div>
                                                                <div class="font-semibold">{{ $detail['customer'] ?? $detail['customer_name'] ?? '-' }}</div>
                                                                @php($detailTotal = $detail['total'] ?? $detail['amount'] ?? 0)
                                                                <div>Total: Rp {{ number_format($detailTotal, 2, ',', '.') }}</div>
                                                                <ul class="list-disc list-inside">
                                                                    @foreach($detail['transactions'] ?? [] as $txn)
                                                                        <li>Invoice {{ $txn['invoice_id'] ?? '-' }} &middot; {{ $txn['method'] ?? '-' }} &middot; Rp {{ number_format($txn['amount'] ?? 0, 2, ',', '.') }} &middot; {{ $txn['payment_date'] ?? '-' }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                            @php($breakdown = $item['metadata']['breakdown'] ?? [])
                                            @if(!empty(($breakdown['inflow'] ?? [])) || !empty(($breakdown['outflow'] ?? [])))
                                                <details class="mt-1 text-xs text-gray-500">
                                                    <summary class="cursor-pointer select-none">Rincian COA</summary>
                                                    <div class="mt-1 grid grid-cols-1 md:grid-cols-2 gap-2">
                                                        @if(!empty($breakdown['inflow']))
                                                            <div>
                                                                <div class="font-semibold">Masuk</div>
                                                                <ul class="list-disc list-inside">
                                                                    @foreach($breakdown['inflow'] as $coa)
                                                                        <li>{{ $coa['coa_code'] }} &mdash; {{ $coa['coa_name'] }} (Rp {{ number_format($coa['total'], 2, ',', '.') }})</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                                        @if(!empty($breakdown['outflow']))
                                                            <div>
                                                                <div class="font-semibold">Keluar</div>
                                                                <ul class="list-disc list-inside">
                                                                    @foreach($breakdown['outflow'] as $coa)
                                                                        <li>{{ $coa['coa_code'] }} &mdash; {{ $coa['coa_name'] }} (Rp {{ number_format($coa['total'], 2, ',', '.') }})</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </details>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right font-semibold {{ ($item['amount'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($item['amount'] ?? 0, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t">
                                    <th class="py-3 text-right">Total {{ $section['label'] }}</th>
                                    <th class="py-3 text-right">{{ number_format($section['total'] ?? 0, 2, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2 justify-end">
            <x-filament::button wire:click="export('excel')" color="primary" icon="heroicon-m-arrow-down-tray">
                Export Excel
            </x-filament::button>
            <x-filament::button wire:click="export('pdf')" color="gray" icon="heroicon-m-document-text">
                Export PDF
            </x-filament::button>
            <x-filament::button wire:click="$refresh">Refresh</x-filament::button>
        </div>
    </div>
</x-filament::page>
