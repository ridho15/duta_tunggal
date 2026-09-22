<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->infolist }}

        @php
            $journalEntries = $this->record->journalEntries()->with('coa')->orderBy('id')->get();
        @endphp

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Jurnal Produksi In Progress</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Baris jurnal yang dibuat saat Production dibuat pada status in progress.
                </p>
            </div>

            <div class="p-6">
                @if($journalEntries->isEmpty())
                    <div class="text-sm italic text-gray-500">No journal entries found</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300 text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Code</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Name</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Reference</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Debit</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($journalEntries as $entry)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2 font-mono text-xs">{{ $entry->coa->code }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ \Illuminate\Support\Str::limit($entry->coa->name, 40) }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ \Illuminate\Support\Str::limit($entry->reference ?? '', 35) }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono {{ $entry->debit > 0 ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono {{ $entry->credit > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                    @if(!empty($entry->description))
                                        <tr class="bg-gray-25">
                                            <td colspan="5" class="border border-gray-300 px-3 py-1 text-xs text-gray-600 italic">
                                                <strong>Description:</strong> {{ $entry->description }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>