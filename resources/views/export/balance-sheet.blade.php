<table border="1">
    <tr>
        <td colspan="3" style="font-weight: bold; font-size: 16px;">NERACA</td>
    </tr>
    <tr>
        <td colspan="3">Per Tanggal: {{ $asOf->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="3" style="font-weight: bold;">A. ASET</td>
    </tr>
    @foreach ($data['assets'] as $group)
        <tr>
            <td colspan="3" style="font-weight: bold; background-color: #f0f0f0;">{{ $group['parent'] }}</td>
        </tr>
        @foreach ($group['items'] as $row)
            <tr>
                <td>{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td style="text-align: right;">{{ number_format($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="font-weight: bold;">Subtotal {{ $group['parent'] }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($group['subtotal']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 14px;">TOTAL ASET</td>
        <td style="text-align: right; font-weight: bold; font-size: 14px;">{{ number_format($data['asset_total']) }}</td>
    </tr>

    <tr>
        <td colspan="3" style="font-weight: bold;">B. KEWAJIBAN</td>
    </tr>
    @foreach ($data['liabilities'] as $group)
        <tr>
            <td colspan="3" style="font-weight: bold; background-color: #f0f0f0;">{{ $group['parent'] }}</td>
        </tr>
        @foreach ($group['items'] as $row)
            <tr>
                <td>{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td style="text-align: right;">{{ number_format($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="font-weight: bold;">Subtotal {{ $group['parent'] }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($group['subtotal']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 14px;">TOTAL KEWAJIBAN</td>
        <td style="text-align: right; font-weight: bold; font-size: 14px;">{{ number_format($data['liab_total']) }}</td>
    </tr>

    <tr>
        <td colspan="3" style="font-weight: bold;">C. MODAL</td>
    </tr>
    @foreach ($data['equity'] as $group)
        <tr>
            <td colspan="3" style="font-weight: bold; background-color: #f0f0f0;">{{ $group['parent'] }}</td>
        </tr>
        @foreach ($group['items'] as $row)
            <tr>
                <td>{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td style="text-align: right;">{{ number_format($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="font-weight: bold;">Subtotal {{ $group['parent'] }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($group['subtotal']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2">Laba Ditahan (s/d periode)</td>
        <td style="text-align: right;">{{ number_format($data['retained_earnings']) }}</td>
    </tr>
    @if(($data['current_earnings'] ?? 0) != 0)
    <tr>
        <td colspan="2">Laba Tahun Berjalan</td>
        <td style="text-align: right;">{{ number_format($data['current_earnings']) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 14px;">TOTAL MODAL</td>
        <td style="text-align: right; font-weight: bold; font-size: 14px;">{{ number_format($data['equity_total']) }}</td>
    </tr>

    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold; background-color: #e0e0e0;">
            STATUS: {{ $data['balanced'] ? 'BALANCED' : 'TIDAK SEIMBANG' }}
        </td>
    </tr>
</table>
