<table>
    <tr>
        <td colspan="7"><strong>Journal Entries - Grouped by Parent COA</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generated at: {{ now()->format('d M Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="7">
            @php
                $filterLabels = [];
                if (!empty($filters['start_date'])) {
                    $filterLabels[] = 'Start: ' . \Carbon\Carbon::parse($filters['start_date'])->format('d M Y');
                }
                if (!empty($filters['end_date'])) {
                    $filterLabels[] = 'End: ' . \Carbon\Carbon::parse($filters['end_date'])->format('d M Y');
                }
                if (!empty($filters['journal_type'])) {
                    $filterLabels[] = 'Type: ' . ucfirst($filters['journal_type']);
                }
                if (!empty($filters['cabang_id'])) {
                    $filterLabels[] = 'Branch ID: ' . $filters['cabang_id'];
                }
            @endphp
            Filters: {{ $filterLabels ? implode(' | ', $filterLabels) : 'None' }}
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th align="left">Metric</th>
            <th align="right">Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Entries</td>
            <td align="right">{{ number_format($summary['total_entries'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total Debit</td>
            <td align="right">{{ number_format($summary['total_debit'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Total Credit</td>
            <td align="right">{{ number_format($summary['total_credit'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Net Balance</td>
            <td align="right">{{ number_format($summary['net_balance'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td align="right">{{ ($summary['is_balanced'] ?? false) ? 'Balanced' : 'Unbalanced' }}</td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th align="left">Parent Code</th>
            <th align="left">Parent Name</th>
            <th align="left">Type</th>
            <th align="right">Total Debit</th>
            <th align="right">Total Credit</th>
            <th align="right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groupedData as $parent)
            <tr>
                <td>{{ $parent['code'] }}</td>
                <td>{{ $parent['name'] }}</td>
                <td>{{ $parent['type'] }}</td>
                <td align="right">{{ number_format($parent['total_debit'], 2) }}</td>
                <td align="right">{{ number_format($parent['total_credit'], 2) }}</td>
                <td align="right">{{ number_format($parent['balance'], 2) }}</td>
            </tr>

            @if (!empty($parent['children']))
                <tr>
                    <td colspan="6"><strong>Child Accounts</strong></td>
                </tr>
                <tr>
                    <th align="left">&nbsp;&nbsp;Child Code</th>
                    <th align="left">Child Name</th>
                    <th align="left">Type</th>
                    <th align="right">Debit</th>
                    <th align="right">Credit</th>
                    <th align="right">Balance</th>
                </tr>
                @foreach ($parent['children'] as $child)
                    <tr>
                        <td>&nbsp;&nbsp;{{ $child['code'] }}</td>
                        <td>{{ $child['name'] }}</td>
                        <td>{{ $child['type'] }}</td>
                        <td align="right">{{ number_format($child['total_debit'], 2) }}</td>
                        <td align="right">{{ number_format($child['total_credit'], 2) }}</td>
                        <td align="right">{{ number_format($child['balance'], 2) }}</td>
                    </tr>

                    @if (!empty($child['entries']))
                        <tr>
                            <td colspan="6">&nbsp;&nbsp;&nbsp;&nbsp;<strong>Entries</strong></td>
                        </tr>
                        <tr>
                            <th align="left">Date</th>
                            <th align="left">Reference</th>
                            <th align="left">Description</th>
                            <th align="right">Debit</th>
                            <th align="right">Credit</th>
                            <th align="left">Journal Type</th>
                        </tr>
                        @foreach ($child['entries'] as $entry)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</td>
                                <td>{{ $entry['reference'] ?? '-' }}</td>
                                <td>{{ $entry['description'] ?? '-' }}</td>
                                <td align="right">{{ number_format($entry['debit'], 2) }}</td>
                                <td align="right">{{ number_format($entry['credit'], 2) }}</td>
                                <td>{{ $entry['journal_type'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            @endif

            @if (!empty($parent['entries']))
                <tr>
                    <td colspan="6"><strong>Direct Entries</strong></td>
                </tr>
                <tr>
                    <th align="left">Date</th>
                    <th align="left">Reference</th>
                    <th align="left">Description</th>
                    <th align="right">Debit</th>
                    <th align="right">Credit</th>
                    <th align="left">Journal Type</th>
                </tr>
                @foreach ($parent['entries'] as $entry)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</td>
                        <td>{{ $entry['reference'] ?? '-' }}</td>
                        <td>{{ $entry['description'] ?? '-' }}</td>
                        <td align="right">{{ number_format($entry['debit'], 2) }}</td>
                        <td align="right">{{ number_format($entry['credit'], 2) }}</td>
                        <td>{{ $entry['journal_type'] ?? '-' }}</td>
                    </tr>
                @endforeach
            @endif

            <tr><td colspan="6"></td></tr>
        @endforeach
    </tbody>
</table>
