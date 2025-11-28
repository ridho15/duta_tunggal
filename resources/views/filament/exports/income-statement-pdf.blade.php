<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Laba Rugi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }
        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 5px;
        }
        h2 {
            text-align: center;
            font-size: 12px;
            font-weight: normal;
            margin: 0 0 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            border-bottom: 2px solid #000;
            font-weight: bold;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .bg-green {
            background-color: #d1fae5;
        }
        .bg-red {
            background-color: #fee2e2;
        }
        .bg-orange {
            background-color: #fed7aa;
        }
        .bg-purple {
            background-color: #e9d5ff;
        }
        .bg-blue {
            background-color: #dbeafe;
        }
        .bg-gray {
            background-color: #f3f4f6;
        }
        .total-row {
            background-color: #f9fafb;
            font-weight: bold;
        }
        .profit-row {
            background-color: #dbeafe;
            font-weight: bold;
            font-size: 11px;
        }
        .final-row {
            background-color: #bfdbfe;
            font-weight: bold;
            font-size: 12px;
        }
        .section-header {
            font-weight: bold;
            padding-top: 10px;
        }
        .summary-info {
            margin-bottom: 20px;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>LAPORAN LABA RUGI (INCOME STATEMENT)</h1>
    <h2>{{ $cabang }}</h2>
    
    <div class="summary-info">
        <strong>Periode:</strong> {{ \Carbon\Carbon::parse($start_date)->format('d F Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d F Y') }}<br>
        <strong>Dicetak:</strong> {{ now()->format('d F Y H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Kode Akun</th>
                <th style="width: 40%;">Nama Akun</th>
                <th style="width: 10%;" class="text-right">Jumlah Trans.</th>
                <th style="width: 20%;" class="text-right">Jumlah (Rp)</th>
                <th style="width: 15%;" class="text-right">% dari Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            {{-- 1. PENDAPATAN USAHA --}}
            <tr class="section-header bg-green">
                <td colspan="5">PENDAPATAN USAHA (SALES REVENUE)</td>
            </tr>
            @forelse($data['sales_revenue']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="text-right">{{ $account['entries_count'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">TOTAL PENDAPATAN USAHA</td>
                <td class="text-right">{{ number_format($data['sales_revenue']['total'], 0, ',', '.') }}</td>
                <td class="text-right">100.0%</td>
            </tr>

            {{-- 2. HARGA POKOK PENJUALAN --}}
            <tr class="section-header bg-red">
                <td colspan="5">HARGA POKOK PENJUALAN (COGS)</td>
            </tr>
            @forelse($data['cogs']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="text-right">{{ $account['entries_count'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">TOTAL HARGA POKOK PENJUALAN</td>
                <td class="text-right">{{ number_format($data['cogs']['total'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $data['sales_revenue']['total'] > 0 ? number_format(($data['cogs']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%</td>
            </tr>

            {{-- LABA KOTOR --}}
            <tr class="profit-row">
                <td colspan="3">LABA KOTOR (GROSS PROFIT)</td>
                <td class="text-right">{{ number_format($data['gross_profit'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['gross_profit_margin'], 1) }}%</td>
            </tr>

            {{-- 3. BEBAN OPERASIONAL --}}
            <tr class="section-header bg-orange">
                <td colspan="5">BEBAN OPERASIONAL (OPERATING EXPENSES)</td>
            </tr>
            @forelse($data['operating_expenses']['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="text-right">{{ $account['entries_count'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">TOTAL BEBAN OPERASIONAL</td>
                <td class="text-right">{{ number_format($data['operating_expenses']['total'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $data['sales_revenue']['total'] > 0 ? number_format(($data['operating_expenses']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%</td>
            </tr>

            {{-- LABA OPERASIONAL --}}
            <tr class="profit-row">
                <td colspan="3">LABA OPERASIONAL (OPERATING PROFIT)</td>
                <td class="text-right">{{ number_format($data['operating_profit'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['operating_profit_margin'], 1) }}%</td>
            </tr>

            {{-- 4. PENDAPATAN/BEBAN LAIN-LAIN --}}
            @if($data['other_income']['total'] > 0 || $data['other_expense']['total'] > 0)
                <tr class="section-header bg-purple">
                    <td colspan="5">PENDAPATAN LAIN-LAIN</td>
                </tr>
                @foreach($data['other_income']['accounts'] as $account)
                    <tr>
                        <td>{{ $account['code'] }}</td>
                        <td>{{ $account['name'] }}</td>
                        <td class="text-right">{{ $account['entries_count'] }}</td>
                        <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                    </tr>
                @endforeach

                <tr class="section-header bg-purple">
                    <td colspan="5">BEBAN LAIN-LAIN</td>
                </tr>
                @foreach($data['other_expense']['accounts'] as $account)
                    <tr>
                        <td>{{ $account['code'] }}</td>
                        <td>{{ $account['name'] }}</td>
                        <td class="text-right">{{ $account['entries_count'] }}</td>
                        <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                    </tr>
                @endforeach

                <tr class="total-row">
                    <td colspan="3">PENDAPATAN/(BEBAN) LAIN-LAIN BERSIH</td>
                    <td class="text-right">{{ number_format($data['net_other_income_expense'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $data['sales_revenue']['total'] > 0 ? number_format(($data['net_other_income_expense'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%</td>
                </tr>
            @endif

            {{-- LABA SEBELUM PAJAK --}}
            <tr class="profit-row">
                <td colspan="3">LABA SEBELUM PAJAK (PROFIT BEFORE TAX)</td>
                <td class="text-right">{{ number_format($data['profit_before_tax'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $data['sales_revenue']['total'] > 0 ? number_format(($data['profit_before_tax'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%</td>
            </tr>

            {{-- 5. PAJAK PENGHASILAN --}}
            @if($data['tax_expense']['total'] > 0)
                <tr class="section-header bg-gray">
                    <td colspan="5">PAJAK PENGHASILAN (TAX EXPENSE)</td>
                </tr>
                @foreach($data['tax_expense']['accounts'] as $account)
                    <tr>
                        <td>{{ $account['code'] }}</td>
                        <td>{{ $account['name'] }}</td>
                        <td class="text-right">{{ $account['entries_count'] }}</td>
                        <td class="text-right">{{ number_format($account['balance'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($account['percentage_of_revenue'], 1) }}%</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">TOTAL PAJAK PENGHASILAN</td>
                    <td class="text-right">{{ number_format($data['tax_expense']['total'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $data['sales_revenue']['total'] > 0 ? number_format(($data['tax_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%</td>
                </tr>
            @endif

            {{-- LABA BERSIH --}}
            <tr class="final-row">
                <td colspan="3">LABA BERSIH (NET PROFIT)</td>
                <td class="text-right">{{ number_format($data['net_profit'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['net_profit_margin'], 1) }}%</td>
            </tr>
        </tbody>
    </table>

    @if($comparison)
        <h3 style="margin-top: 30px; margin-bottom: 10px;">Perbandingan Periode</h3>
        <table>
            <thead>
                <tr>
                    <th>Metrik</th>
                    <th class="text-right">Perubahan (Rp)</th>
                    <th class="text-right">Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Pendapatan Usaha</td>
                    <td class="text-right">{{ number_format($comparison['changes']['sales_revenue']['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($comparison['changes']['sales_revenue']['percentage'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Laba Kotor</td>
                    <td class="text-right">{{ number_format($comparison['changes']['gross_profit']['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($comparison['changes']['gross_profit']['percentage'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Laba Operasional</td>
                    <td class="text-right">{{ number_format($comparison['changes']['operating_profit']['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($comparison['changes']['operating_profit']['percentage'], 1) }}%</td>
                </tr>
                <tr>
                    <td>Laba Sebelum Pajak</td>
                    <td class="text-right">{{ number_format($comparison['changes']['profit_before_tax']['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($comparison['changes']['profit_before_tax']['percentage'], 1) }}%</td>
                </tr>
                <tr class="font-bold">
                    <td>Laba Bersih</td>
                    <td class="text-right">{{ number_format($comparison['changes']['net_profit']['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($comparison['changes']['net_profit']['percentage'], 1) }}%</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
