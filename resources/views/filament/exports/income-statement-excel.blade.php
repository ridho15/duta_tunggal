<table>
    <thead>
        <tr>
            <th colspan="5" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN LABA RUGI (INCOME STATEMENT)</th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center;">{{ $cabang }}</th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($start_date)->format('d F Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d F Y') }}</th>
        </tr>
        <tr><th colspan="5"></th></tr>
        <tr>
            <th style="font-weight: bold; background-color: #f3f4f6;">Kode Akun</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Nama Akun</th>
            <th style="font-weight: bold; background-color: #f3f4f6; text-align: right;">Jumlah Transaksi</th>
            <th style="font-weight: bold; background-color: #f3f4f6; text-align: right;">Jumlah (Rp)</th>
            <th style="font-weight: bold; background-color: #f3f4f6; text-align: right;">% dari Pendapatan</th>
        </tr>
    </thead>
    <tbody>
        {{-- 1. PENDAPATAN USAHA --}}
        <tr style="background-color: #d1fae5; font-weight: bold;">
            <td colspan="5">PENDAPATAN USAHA (SALES REVENUE)</td>
        </tr>
        @forelse($data['sales_revenue']['accounts'] as $account)
            <tr>
                <td>{{ $account['code'] }}</td>
                <td>{{ $account['name'] }}</td>
                <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                <td style="text-align: right;">{{ $account['balance'] }}</td>
                <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">Tidak ada data</td></tr>
        @endforelse
        <tr style="background-color: #f9fafb; font-weight: bold;">
            <td colspan="3">TOTAL PENDAPATAN USAHA</td>
            <td style="text-align: right;">{{ $data['sales_revenue']['total'] }}</td>
            <td style="text-align: right;">100.0%</td>
        </tr>

        {{-- 2. HARGA POKOK PENJUALAN --}}
        <tr style="background-color: #fee2e2; font-weight: bold;">
            <td colspan="5">HARGA POKOK PENJUALAN (COGS)</td>
        </tr>
        @forelse($data['cogs']['accounts'] as $account)
            <tr>
                <td>{{ $account['code'] }}</td>
                <td>{{ $account['name'] }}</td>
                <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                <td style="text-align: right;">{{ $account['balance'] }}</td>
                <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">Tidak ada data</td></tr>
        @endforelse
        <tr style="background-color: #f9fafb; font-weight: bold;">
            <td colspan="3">TOTAL HARGA POKOK PENJUALAN</td>
            <td style="text-align: right;">{{ $data['cogs']['total'] }}</td>
            <td style="text-align: right;">{{ $data['sales_revenue']['total'] > 0 ? round(($data['cogs']['total'] / $data['sales_revenue']['total']) * 100, 1) : 0 }}%</td>
        </tr>

        {{-- LABA KOTOR --}}
        <tr style="background-color: #dbeafe; font-weight: bold;">
            <td colspan="3">LABA KOTOR (GROSS PROFIT)</td>
            <td style="text-align: right;">{{ $data['gross_profit'] }}</td>
            <td style="text-align: right;">{{ round($data['gross_profit_margin'], 1) }}%</td>
        </tr>

        {{-- 3. BEBAN OPERASIONAL --}}
        <tr style="background-color: #fed7aa; font-weight: bold;">
            <td colspan="5">BEBAN OPERASIONAL (OPERATING EXPENSES)</td>
        </tr>
        @forelse($data['operating_expenses']['accounts'] as $account)
            <tr>
                <td>{{ $account['code'] }}</td>
                <td>{{ $account['name'] }}</td>
                <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                <td style="text-align: right;">{{ $account['balance'] }}</td>
                <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">Tidak ada data</td></tr>
        @endforelse
        <tr style="background-color: #f9fafb; font-weight: bold;">
            <td colspan="3">TOTAL BEBAN OPERASIONAL</td>
            <td style="text-align: right;">{{ $data['operating_expenses']['total'] }}</td>
            <td style="text-align: right;">{{ $data['sales_revenue']['total'] > 0 ? round(($data['operating_expenses']['total'] / $data['sales_revenue']['total']) * 100, 1) : 0 }}%</td>
        </tr>

        {{-- LABA OPERASIONAL --}}
        <tr style="background-color: #dbeafe; font-weight: bold;">
            <td colspan="3">LABA OPERASIONAL (OPERATING PROFIT)</td>
            <td style="text-align: right;">{{ $data['operating_profit'] }}</td>
            <td style="text-align: right;">{{ round($data['operating_profit_margin'], 1) }}%</td>
        </tr>

        {{-- 4. PENDAPATAN/BEBAN LAIN-LAIN --}}
        @if($data['other_income']['total'] > 0 || $data['other_expense']['total'] > 0)
            <tr style="background-color: #e9d5ff; font-weight: bold;">
                <td colspan="5">PENDAPATAN LAIN-LAIN</td>
            </tr>
            @foreach($data['other_income']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                    <td style="text-align: right;">{{ $account['balance'] }}</td>
                    <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @endforeach

            <tr style="background-color: #e9d5ff; font-weight: bold;">
                <td colspan="5">BEBAN LAIN-LAIN</td>
            </tr>
            @foreach($data['other_expense']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                    <td style="text-align: right;">{{ $account['balance'] }}</td>
                    <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @endforeach

            <tr style="background-color: #f9fafb; font-weight: bold;">
                <td colspan="3">PENDAPATAN/(BEBAN) LAIN-LAIN BERSIH</td>
                <td style="text-align: right;">{{ $data['net_other_income_expense'] }}</td>
                <td style="text-align: right;">{{ $data['sales_revenue']['total'] > 0 ? round(($data['net_other_income_expense'] / $data['sales_revenue']['total']) * 100, 1) : 0 }}%</td>
            </tr>
        @endif

        {{-- LABA SEBELUM PAJAK --}}
        <tr style="background-color: #dbeafe; font-weight: bold;">
            <td colspan="3">LABA SEBELUM PAJAK (PROFIT BEFORE TAX)</td>
            <td style="text-align: right;">{{ $data['profit_before_tax'] }}</td>
            <td style="text-align: right;">{{ $data['sales_revenue']['total'] > 0 ? round(($data['profit_before_tax'] / $data['sales_revenue']['total']) * 100, 1) : 0 }}%</td>
        </tr>

        {{-- 5. PAJAK PENGHASILAN --}}
        @if($data['tax_expense']['total'] > 0)
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td colspan="5">PAJAK PENGHASILAN (TAX EXPENSE)</td>
            </tr>
            @foreach($data['tax_expense']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td style="text-align: right;">{{ $account['entries_count'] }}</td>
                    <td style="text-align: right;">{{ $account['balance'] }}</td>
                    <td style="text-align: right;">{{ round($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @endforeach
            <tr style="background-color: #f9fafb; font-weight: bold;">
                <td colspan="3">TOTAL PAJAK PENGHASILAN</td>
                <td style="text-align: right;">{{ $data['tax_expense']['total'] }}</td>
                <td style="text-align: right;">{{ $data['sales_revenue']['total'] > 0 ? round(($data['tax_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : 0 }}%</td>
            </tr>
        @endif

        {{-- LABA BERSIH --}}
        <tr style="background-color: #bfdbfe; font-weight: bold; font-size: 12px;">
            <td colspan="3">LABA BERSIH (NET PROFIT)</td>
            <td style="text-align: right;">{{ $data['net_profit'] }}</td>
            <td style="text-align: right;">{{ round($data['net_profit_margin'], 1) }}%</td>
        </tr>

        @if($comparison)
            <tr><td colspan="5"></td></tr>
            <tr><td colspan="5"></td></tr>
            <tr style="font-weight: bold;">
                <td colspan="5">PERBANDINGAN PERIODE</td>
            </tr>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td colspan="3">Metrik</td>
                <td style="text-align: right;">Perubahan (Rp)</td>
                <td style="text-align: right;">Perubahan (%)</td>
            </tr>
            <tr>
                <td colspan="3">Pendapatan Usaha</td>
                <td style="text-align: right;">{{ $comparison['changes']['sales_revenue']['amount'] }}</td>
                <td style="text-align: right;">{{ round($comparison['changes']['sales_revenue']['percentage'], 1) }}%</td>
            </tr>
            <tr>
                <td colspan="3">Laba Kotor</td>
                <td style="text-align: right;">{{ $comparison['changes']['gross_profit']['amount'] }}</td>
                <td style="text-align: right;">{{ round($comparison['changes']['gross_profit']['percentage'], 1) }}%</td>
            </tr>
            <tr>
                <td colspan="3">Laba Operasional</td>
                <td style="text-align: right;">{{ $comparison['changes']['operating_profit']['amount'] }}</td>
                <td style="text-align: right;">{{ round($comparison['changes']['operating_profit']['percentage'], 1) }}%</td>
            </tr>
            <tr>
                <td colspan="3">Laba Sebelum Pajak</td>
                <td style="text-align: right;">{{ $comparison['changes']['profit_before_tax']['amount'] }}</td>
                <td style="text-align: right;">{{ round($comparison['changes']['profit_before_tax']['percentage'], 1) }}%</td>
            </tr>
            <tr style="font-weight: bold;">
                <td colspan="3">Laba Bersih</td>
                <td style="text-align: right;">{{ $comparison['changes']['net_profit']['amount'] }}</td>
                <td style="text-align: right;">{{ round($comparison['changes']['net_profit']['percentage'], 1) }}%</td>
            </tr>
        @endif
    </tbody>
</table>
