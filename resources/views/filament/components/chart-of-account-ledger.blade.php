<div class="space-y-4" wire:key="ledger-{{ $record->id }}-{{ $start_date }}-{{ $end_date }}">
    {{-- Filter Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <h3 class="text-lg font-semibold mb-4">Filter Buku Besar</h3>
        
        <form wire:submit="filterLedger" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Tanggal / Periode Dari</label>
                <input 
                    type="date" 
                    wire:model="start_date"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Sampai</label>
                <input 
                    type="date" 
                    wire:model="end_date"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
            </div>
            
            <div class="flex items-end">
                <button 
                    type="submit"
                    class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md"
                >
                    Tampilkan
                </button>
            </div>
        </form>
    </div>

    {{-- Ledger Display Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Kode</p>
                    <p class="text-lg font-bold">{{ $record->code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Nama</p>
                    <p class="text-lg font-bold">{{ $record->name }}</p>
                </div>
            </div>
        </div>

        @php
            // Ensure dates have valid values
            $startDate = $start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $end_date ?? now()->endOfMonth()->format('Y-m-d');
            
            // Validate dates
            if (empty($startDate)) {
                $startDate = now()->startOfMonth()->format('Y-m-d');
            }
            if (empty($endDate)) {
                $endDate = now()->endOfMonth()->format('Y-m-d');
            }
            
            // Get journal entries for this account within date range
            $journalEntries = \App\Models\JournalEntry::where('coa_id', $record->id)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            // Calculate opening balance (transactions before start date)
            $openingDebit = \App\Models\JournalEntry::where('coa_id', $record->id)
                ->where('date', '<', $startDate)
                ->sum('debit');
            
            $openingCredit = \App\Models\JournalEntry::where('coa_id', $record->id)
                ->where('date', '<', $startDate)
                ->sum('credit');
            
            // Calculate opening balance based on account type
            if (in_array($record->type, ['Asset', 'Expense'])) {
                $openingBalance = $record->opening_balance + $openingDebit - $openingCredit;
            } else {
                $openingBalance = $record->opening_balance - $openingDebit + $openingCredit;
            }
            
            $runningBalance = $openingBalance;
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Tanggal
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Tipe Transaksi
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            No Transaksi
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Keterangan
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Debit
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Kredit
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Saldo
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {{-- Opening Balance Row --}}
                    <tr class="bg-yellow-50 dark:bg-yellow-900/20">
                        <td class="px-4 py-3 whitespace-nowrap" colspan="4">
                            <span class="font-semibold">Saldo Awal</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right"></td>
                        <td class="px-4 py-3 whitespace-nowrap text-right"></td>
                        <td class="px-4 py-3 whitespace-nowrap text-right font-semibold">
                            Rp {{ number_format($openingBalance, 2, ',', '.') }}
                        </td>
                    </tr>

                    {{-- Transaction Rows --}}
                    @forelse($journalEntries as $entry)
                        @php
                            // Update running balance based on account type
                            if (in_array($record->type, ['Asset', 'Expense'])) {
                                $runningBalance = $runningBalance + $entry->debit - $entry->credit;
                            } else {
                                $runningBalance = $runningBalance - $entry->debit + $entry->credit;
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $entry->journal_type ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $entry->reference ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $entry->description ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                @if($entry->debit > 0)
                                    Rp {{ number_format($entry->debit, 2, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                @if($entry->credit > 0)
                                    Rp {{ number_format($entry->credit, 2, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                Rp {{ number_format($runningBalance, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada transaksi dalam periode ini
                            </td>
                        </tr>
                    @endforelse

                    {{-- Closing Balance Row --}}
                    @if($journalEntries->count() > 0)
                        <tr class="bg-blue-50 dark:bg-blue-900/20 font-semibold">
                            <td class="px-4 py-3 whitespace-nowrap" colspan="4">
                                <span class="font-bold">Saldo Akhir</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                Rp {{ number_format($journalEntries->sum('debit'), 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                Rp {{ number_format($journalEntries->sum('credit'), 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold">
                                Rp {{ number_format($runningBalance, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Export Actions --}}
    <div class="flex justify-end space-x-2">
        <button 
            onclick="window.print()"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm"
        >
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print
        </button>
        
        <button 
            onclick="exportToExcel()"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm"
        >
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Excel
        </button>
    </div>
</div>

@push('scripts')
<script>
function exportToExcel() {
    // Simple table to Excel export
    alert('Export to Excel functionality - to be implemented with backend');
    // You can implement this with a backend route that generates Excel file
}

// Print styles
window.addEventListener('beforeprint', function() {
    document.body.classList.add('print-mode');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('print-mode');
});
</script>
@endpush

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .overflow-x-auto, .overflow-x-auto * {
        visibility: visible;
    }
    .overflow-x-auto {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    button {
        display: none !important;
    }
}
</style>
