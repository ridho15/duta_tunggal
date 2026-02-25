<x-filament::page>
    <div>
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        @if($this->showPreview)

        @php($data = $this->getReportData())
        @php($asOfDate = $this->as_of_date ?? now()->format('Y-m-d'))

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="p-4 border rounded">
                <h2 class="font-semibold mb-3">A. Aset</h2>
                @foreach ($data['assets'] as $group)
                    <div class="mb-2">
                        <div class="text-sm font-medium text-gray-600">{{ $group['parent'] }}</div>
                        <div class="mt-1 space-y-1">
                            @foreach ($group['items'] as $row)
                                <div class="flex justify-between">
                                    <a class="text-primary-600 hover:underline" href="{{ route('filament.admin.resources.chart-of-accounts.view', ['record' => $row['coa']->id]) }}?start={{ now()->startOfYear()->format('Y-m-d') }}&end={{ $asOfDate ?? now()->format('Y-m-d') }}" target="_blank">
                                        {{ $row['coa']->code }} - {{ $row['coa']->name }}
                                    </a>
                                    <span>Rp {{ number_format($row['balance']) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between font-medium">
                                <span>Subtotal</span>
                                <span>Rp {{ number_format($group['subtotal']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-between font-bold border-t pt-2">
                    <span>Total Aset</span>
                    <span>Rp {{ number_format($data['asset_total']) }}</span>
                </div>
            </div>

            <div class="p-4 border rounded">
                <h2 class="font-semibold mb-3">B. Kewajiban</h2>
                @foreach ($data['liabilities'] as $group)
                    <div class="mb-2">
                        <div class="text-sm font-medium text-gray-600">{{ $group['parent'] }}</div>
                        <div class="mt-1 space-y-1">
                            @foreach ($group['items'] as $row)
                                <div class="flex justify-between">
                                    <a class="text-primary-600 hover:underline" href="{{ route('filament.admin.resources.chart-of-accounts.view', ['record' => $row['coa']->id]) }}?start={{ now()->startOfYear()->format('Y-m-d') }}&end={{ $asOfDate ?? now()->format('Y-m-d') }}" target="_blank">
                                        {{ $row['coa']->code }} - {{ $row['coa']->name }}
                                    </a>
                                    <span>Rp {{ number_format($row['balance']) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between font-medium">
                                <span>Subtotal</span>
                                <span>Rp {{ number_format($group['subtotal']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-between font-bold border-t pt-2">
                    <span>Total Kewajiban</span>
                    <span>Rp {{ number_format($data['liab_total']) }}</span>
                </div>

                <h2 class="font-semibold mt-6 mb-3">C. Modal</h2>
                @foreach ($data['equity'] as $group)
                    <div class="mb-2">
                        <div class="text-sm font-medium text-gray-600">{{ $group['parent'] }}</div>
                        <div class="mt-1 space-y-1">
                            @foreach ($group['items'] as $row)
                                <div class="flex justify-between">
                                    <a class="text-primary-600 hover:underline" href="{{ route('filament.admin.resources.chart-of-accounts.view', ['record' => $row['coa']->id]) }}?start={{ now()->startOfYear()->format('Y-m-d') }}&end={{ $asOfDate ?? now()->format('Y-m-d') }}" target="_blank">
                                        {{ $row['coa']->code }} - {{ $row['coa']->name }}
                                    </a>
                                    <span>Rp {{ number_format($row['balance']) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between font-medium">
                                <span>Subtotal</span>
                                <span>Rp {{ number_format($group['subtotal']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($data['retained_earnings'] != 0)
                <div class="mb-2">
                    <div class="text-sm font-medium text-gray-600">Laba Ditahan</div>
                    <div class="mt-1 space-y-1">
                        <div class="flex justify-between">
                            <span>3400 - Laba Ditahan</span>
                            <span>Rp {{ number_format($data['retained_earnings']) }}</span>
                        </div>
                    </div>
                </div>
                @endif
                <div class="flex justify-between font-bold border-t pt-2">
                    <span>Total Modal</span>
                    <span>Rp {{ number_format($data['equity_total']) }}</span>
                </div>
            </div>
        </div>

        <div class="mt-6 p-4 border rounded flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-600">Auto Balance</div>
                <div class="font-semibold {{ $data['balanced'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $data['balanced'] ? 'Balanced' : 'Tidak Seimbang' }}
                </div>
            </div>
            <div class="text-right">
                <div>Total Aset: <span class="font-semibold">Rp {{ number_format($data['asset_total']) }}</span></div>
                <div>Total Kewajiban + Modal: <span class="font-semibold">Rp {{ number_format($data['liab_total'] + $data['equity_total']) }}</span></div>
            </div>
        </div>

        @if($data['has_unbalanced_entries'])
        <div class="mt-6 p-4 border rounded bg-red-50 border-red-200">
            <div class="font-semibold text-red-800 mb-3">⚠️ Journal Entries Tidak Seimbang</div>
            <div class="text-sm text-red-700 mb-3">
                Ditemukan {{ count($data['unbalanced_entries']) }} transaksi journal yang tidak seimbang. Hal ini dapat menyebabkan balance sheet tidak balance.
            </div>
            <div class="space-y-3">
                @foreach($data['unbalanced_entries'] as $unbalanced)
                <div class="bg-white p-3 rounded border">
                    <div class="font-medium text-gray-800">Transaction ID: {{ $unbalanced['transaction_id'] }}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        Total Debit: Rp {{ number_format($unbalanced['total_debit']) }} |
                        Total Credit: Rp {{ number_format($unbalanced['total_credit']) }} |
                        Selisih: Rp {{ number_format($unbalanced['difference']) }}
                    </div>
                    <div class="mt-2 space-y-1">
                        @foreach($unbalanced['entries'] as $entry)
                        <div class="text-xs bg-gray-50 p-2 rounded">
                            <span class="font-medium">{{ $entry->coa->code }} - {{ $entry->coa->name }}</span><br>
                            <span>{{ $entry->description }}</span><br>
                            <span>Debit: Rp {{ number_format($entry->debit) }} | Credit: Rp {{ number_format($entry->credit) }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex gap-2">
                        <x-filament::button
                            wire:click="fixUnbalancedEntry('{{ $unbalanced['transaction_id'] }}', 'delete')"
                            color="danger"
                            size="sm"
                            wire:confirm="Are you sure you want to delete this unbalanced transaction?">
                            Delete Transaction
                        </x-filament::button>
                        <x-filament::button
                            wire:click="fixUnbalancedEntry('{{ $unbalanced['transaction_id'] }}', 'correct')"
                            color="warning"
                            size="sm"
                            wire:confirm="Are you sure you want to create a correcting entry for this transaction?">
                            Create Correcting Entry
                        </x-filament::button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @php($compareData = $this->getCompareData())
        @if($compareData)
        <div class="mt-4 p-4 border rounded bg-gray-50">
            <div class="font-semibold mb-2">Perbandingan Periode</div>
            <div class="flex items-center justify-between">
                <div>
                    <div>Total Aset (Banding)</div>
                    <div>Total Kewajiban + Modal (Banding)</div>
                    <div>Status</div>
                </div>
                <div class="text-right">
                    <div>Rp {{ number_format($compareData['asset_total']) }}</div>
                    <div>Rp {{ number_format($compareData['liab_total'] + $compareData['equity_total']) }}</div>
                    <div class="font-semibold {{ $compareData['balanced'] ? 'text-green-600' : 'text-red-600' }}">{{ $compareData['balanced'] ? 'Balanced' : 'Tidak Seimbang' }}</div>
                </div>
            </div>
        </div>
        @endif

        <div class="mt-6 flex gap-2">
            <x-filament::button wire:click="$refresh">Refresh</x-filament::button>
            <x-filament::button color="gray" wire:click="exportXlsx">Export Excel</x-filament::button>
            <x-filament::button color="gray" wire:click="exportCsv">Export CSV</x-filament::button>
            <x-filament::button color="gray" wire:click="printPdf">Print PDF</x-filament::button>
        </div>

        @else

        <div class="mt-6 p-8 border rounded bg-gray-50 text-center text-gray-500">
            <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto mb-3 text-gray-400" style="width: 100px; height: 100px;"/>
            <p class="text-lg font-medium" style="font-size: 11pt">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
        </div>

        @endif
    </div>
</x-filament::page>
