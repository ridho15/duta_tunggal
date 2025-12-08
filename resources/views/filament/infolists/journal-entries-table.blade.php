@php
    $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
        ->where('source_id', $getRecord()->id)
        ->with('coa')
        ->get();
@endphp

@if($journalEntries->isEmpty())
    <div class="text-gray-500 italic">No journal entries found</div>
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
                        <td class="border border-gray-300 px-3 py-2">{{ Str::limit($entry->coa->name, 40) }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ Str::limit($entry->reference ?? '', 35) }}</td>
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