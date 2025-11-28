<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Neraca - {{ \Carbon\Carbon::parse($as_of_date)->format('d F Y') }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 16pt;
            font-weight: bold;
        }
        
        .header .subtitle {
            margin: 5px 0;
            font-size: 11pt;
        }
        
        .section-header {
            background: #e0e0e0;
            padding: 8px;
            font-weight: bold;
            font-size: 11pt;
            margin-top: 15px;
            margin-bottom: 5px;
            border: 1px solid #000;
        }
        
        .section-header.assets {
            background: #cfe2ff;
        }
        
        .section-header.liabilities {
            background: #fff3cd;
        }
        
        .section-header.equity {
            background: #d1e7dd;
        }
        
        .subsection-header {
            font-weight: bold;
            padding: 5px 0;
            border-bottom: 1px solid #666;
            margin-top: 10px;
            margin-bottom: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        
        .account-row td {
            padding: 4px 0;
            border-bottom: 1px solid #eee;
        }
        
        .account-row .name {
            padding-left: 15px;
        }
        
        .account-row .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .subtotal-row td {
            padding: 6px 0;
            font-weight: bold;
            border-top: 1px solid #333;
            margin-top: 5px;
        }
        
        .total-row td {
            padding: 8px 0;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-size: 11pt;
        }
        
        .grand-total-row td {
            padding: 8px 0;
            font-weight: bold;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
            font-size: 12pt;
        }
        
        .balance-check {
            margin-top: 15px;
            padding: 10px;
            border: 2px solid #000;
            text-align: center;
            font-weight: bold;
        }
        
        .balance-check.balanced {
            background: #d1e7dd;
            border-color: #0f5132;
        }
        
        .balance-check.unbalanced {
            background: #f8d7da;
            border-color: #842029;
        }
        
        .comparison-section {
            margin-top: 25px;
            page-break-before: always;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .comparison-table th,
        .comparison-table td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 9pt;
        }
        
        .comparison-table th {
            background: #e0e0e0;
            font-weight: bold;
        }
        
        .comparison-table .section-row {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .positive {
            color: #0f5132;
        }
        
        .negative {
            color: #842029;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>NERACA (BALANCE SHEET)</h1>
        <div class="subtitle">Per Tanggal: {{ \Carbon\Carbon::parse($as_of_date)->format('d F Y') }}</div>
        <div class="subtitle">Cabang: {{ $cabang }}</div>
    </div>

    <!-- ASSETS Section -->
    <div class="section-header assets">ASET (ASSETS)</div>
    
    <!-- Current Assets -->
    <div class="subsection-header">Aset Lancar (Current Assets)</div>
    <table>
        @foreach($data['current_assets']['accounts'] as $account)
            <tr class="account-row">
                <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="amount" style="width: 150px;">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Aset Lancar</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['current_assets']['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Fixed Assets -->
    <div class="subsection-header">Aset Tetap (Fixed Assets)</div>
    <table>
        @foreach($data['fixed_assets']['accounts'] as $account)
            <tr class="account-row">
                <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="amount" style="width: 150px;">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Aset Tetap</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['fixed_assets']['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Contra Assets -->
    @if($data['contra_assets']['accounts']->count() > 0)
        <div class="subsection-header">Akumulasi Penyusutan (Accumulated Depreciation)</div>
        <table>
            @foreach($data['contra_assets']['accounts'] as $account)
                <tr class="account-row">
                    <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                    <td class="amount" style="width: 150px;">(Rp {{ number_format($account->balance, 0, ',', '.') }})</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td>Total Akumulasi Penyusutan</td>
                <td style="text-align: right; width: 150px;">(Rp {{ number_format($data['contra_assets']['total'], 0, ',', '.') }})</td>
            </tr>
        </table>
    @endif
    
    <!-- Total Assets -->
    <table>
        <tr class="total-row">
            <td>TOTAL ASET</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['total_assets'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <!-- LIABILITIES Section -->
    <div class="section-header liabilities">KEWAJIBAN (LIABILITIES)</div>
    
    <!-- Current Liabilities -->
    <div class="subsection-header">Kewajiban Lancar (Current Liabilities)</div>
    <table>
        @foreach($data['current_liabilities']['accounts'] as $account)
            <tr class="account-row">
                <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="amount" style="width: 150px;">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Kewajiban Lancar</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['current_liabilities']['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Long-term Liabilities -->
    <div class="subsection-header">Kewajiban Jangka Panjang (Long-term Liabilities)</div>
    <table>
        @foreach($data['long_term_liabilities']['accounts'] as $account)
            <tr class="account-row">
                <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="amount" style="width: 150px;">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Kewajiban Jangka Panjang</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['long_term_liabilities']['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Total Liabilities -->
    <table>
        <tr class="total-row">
            <td>TOTAL KEWAJIBAN</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['total_liabilities'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <!-- EQUITY Section -->
    <div class="section-header equity">EKUITAS (EQUITY)</div>
    
    <table>
        @foreach($data['equity']['accounts'] as $account)
            <tr class="account-row">
                <td class="name">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="amount" style="width: 150px;">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="account-row">
            <td class="name">Laba Ditahan (Retained Earnings)</td>
            <td class="amount" style="width: 150px;">Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}</td>
        </tr>
        
        <tr class="total-row">
            <td>TOTAL EKUITAS</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['total_equity'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Total Liabilities + Equity -->
    <table>
        <tr class="grand-total-row">
            <td>TOTAL KEWAJIBAN & EKUITAS</td>
            <td style="text-align: right; width: 150px;">Rp {{ number_format($data['total_liabilities_and_equity'], 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <!-- Balance Check -->
    <div class="balance-check {{ $data['is_balanced'] ? 'balanced' : 'unbalanced' }}">
        @if($data['is_balanced'])
            ✓ NERACA SEIMBANG (BALANCED)
        @else
            ⚠ NERACA TIDAK SEIMBANG! Selisih: Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}
        @endif
    </div>

    <!-- Comparison Section -->
    @if($comparison)
        <div class="comparison-section">
            <h2 style="font-size: 14pt; margin-bottom: 10px;">Perbandingan Tanggal</h2>
            <p style="margin-bottom: 10px; font-size: 9pt;">
                {{ \Carbon\Carbon::parse($as_of_date)->format('d M Y') }} vs 
                {{ \Carbon\Carbon::parse($comparison_date ?? now()->subMonth()->format('Y-m-d'))->format('d M Y') }}
            </p>
            
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Keterangan</th>
                        <th style="width: 18%;">Periode Saat Ini</th>
                        <th style="width: 18%;">Periode Sebelumnya</th>
                        <th style="width: 18%;">Perubahan</th>
                        <th style="width: 16%;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="section-row">
                        <td colspan="5">ASET</td>
                    </tr>
                    <tr>
                        <td>Aset Lancar</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['current_assets']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['current_assets']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['current_assets']['change'] >= 0 ? 'positive' : 'negative' }}">
                            Rp {{ number_format($comparison['current_assets']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['current_assets']['percentage'] >= 0 ? 'positive' : 'negative' }}">
                            {{ number_format($comparison['current_assets']['percentage'], 2) }}%
                        </td>
                    </tr>
                    <tr>
                        <td>Aset Tetap</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['fixed_assets']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['fixed_assets']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['fixed_assets']['change'] >= 0 ? 'positive' : 'negative' }}">
                            Rp {{ number_format($comparison['fixed_assets']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['fixed_assets']['percentage'] >= 0 ? 'positive' : 'negative' }}">
                            {{ number_format($comparison['fixed_assets']['percentage'], 2) }}%
                        </td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td>Total Aset</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_assets']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_assets']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['total_assets']['change'] >= 0 ? 'positive' : 'negative' }}">
                            Rp {{ number_format($comparison['total_assets']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['total_assets']['percentage'] >= 0 ? 'positive' : 'negative' }}">
                            {{ number_format($comparison['total_assets']['percentage'], 2) }}%
                        </td>
                    </tr>
                    
                    <tr class="section-row">
                        <td colspan="5">KEWAJIBAN</td>
                    </tr>
                    <tr>
                        <td>Kewajiban Lancar</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['current_liabilities']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['current_liabilities']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['current_liabilities']['change'] >= 0 ? 'negative' : 'positive' }}">
                            Rp {{ number_format($comparison['current_liabilities']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['current_liabilities']['percentage'] >= 0 ? 'negative' : 'positive' }}">
                            {{ number_format($comparison['current_liabilities']['percentage'], 2) }}%
                        </td>
                    </tr>
                    <tr>
                        <td>Kewajiban Jangka Panjang</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['long_term_liabilities']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['long_term_liabilities']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['long_term_liabilities']['change'] >= 0 ? 'negative' : 'positive' }}">
                            Rp {{ number_format($comparison['long_term_liabilities']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['long_term_liabilities']['percentage'] >= 0 ? 'negative' : 'positive' }}">
                            {{ number_format($comparison['long_term_liabilities']['percentage'], 2) }}%
                        </td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td>Total Kewajiban</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_liabilities']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_liabilities']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['total_liabilities']['change'] >= 0 ? 'negative' : 'positive' }}">
                            Rp {{ number_format($comparison['total_liabilities']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['total_liabilities']['percentage'] >= 0 ? 'negative' : 'positive' }}">
                            {{ number_format($comparison['total_liabilities']['percentage'], 2) }}%
                        </td>
                    </tr>
                    
                    <tr class="section-row">
                        <td colspan="5">EKUITAS</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td>Total Ekuitas</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_equity']['current'], 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format($comparison['total_equity']['previous'], 0, ',', '.') }}</td>
                        <td style="text-align: right;" class="{{ $comparison['total_equity']['change'] >= 0 ? 'positive' : 'negative' }}">
                            Rp {{ number_format($comparison['total_equity']['change'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: right;" class="{{ $comparison['total_equity']['percentage'] >= 0 ? 'positive' : 'negative' }}">
                            {{ number_format($comparison['total_equity']['percentage'], 2) }}%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
