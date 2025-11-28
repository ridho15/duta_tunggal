<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        .header {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            background-color: #f0f0f0;
        }
        
        .section-header {
            background-color: #e0e0e0;
            font-weight: bold;
            padding: 5px;
        }
        
        .section-header.assets {
            background-color: #cfe2ff;
        }
        
        .section-header.liabilities {
            background-color: #fff3cd;
        }
        
        .section-header.equity {
            background-color: #d1e7dd;
        }
        
        .subsection-header {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .account-row {
            padding-left: 10px;
        }
        
        .subtotal-row {
            font-weight: bold;
            background-color: #f9f9f9;
            border-top: 1px solid #000;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #e0e0e0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .grand-total-row {
            font-weight: bold;
            background-color: #d0d0d0;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
        }
        
        .number {
            text-align: right;
            mso-number-format: "#,##0";
        }
    </style>
</head>
<body>
    <table>
        <!-- Header -->
        <tr>
            <td colspan="2" class="header">NERACA (BALANCE SHEET)</td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center;">Per Tanggal: {{ \Carbon\Carbon::parse($as_of_date)->format('d F Y') }}</td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center;">Cabang: {{ $cabang }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- ASSETS Section -->
        <tr>
            <td colspan="2" class="section-header assets">ASET (ASSETS)</td>
        </tr>
        
        <!-- Current Assets -->
        <tr>
            <td colspan="2" class="subsection-header">Aset Lancar (Current Assets)</td>
        </tr>
        @foreach($data['current_assets']['accounts'] as $account)
            <tr class="account-row">
                <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="number">{{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Aset Lancar</td>
            <td class="number">{{ number_format($data['current_assets']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Fixed Assets -->
        <tr>
            <td colspan="2" class="subsection-header">Aset Tetap (Fixed Assets)</td>
        </tr>
        @foreach($data['fixed_assets']['accounts'] as $account)
            <tr class="account-row">
                <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="number">{{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Aset Tetap</td>
            <td class="number">{{ number_format($data['fixed_assets']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Contra Assets -->
        @if($data['contra_assets']['accounts']->count() > 0)
            <tr>
                <td colspan="2" class="subsection-header">Akumulasi Penyusutan (Accumulated Depreciation)</td>
            </tr>
            @foreach($data['contra_assets']['accounts'] as $account)
                <tr class="account-row">
                    <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                    <td class="number">({{ number_format($account->balance, 0, ',', '.') }})</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td>Total Akumulasi Penyusutan</td>
                <td class="number">({{ number_format($data['contra_assets']['total'], 0, ',', '.') }})</td>
            </tr>
            <tr><td colspan="2">&nbsp;</td></tr>
        @endif
        
        <!-- Total Assets -->
        <tr class="total-row">
            <td>TOTAL ASET</td>
            <td class="number">{{ number_format($data['total_assets'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- LIABILITIES Section -->
        <tr>
            <td colspan="2" class="section-header liabilities">KEWAJIBAN (LIABILITIES)</td>
        </tr>
        
        <!-- Current Liabilities -->
        <tr>
            <td colspan="2" class="subsection-header">Kewajiban Lancar (Current Liabilities)</td>
        </tr>
        @foreach($data['current_liabilities']['accounts'] as $account)
            <tr class="account-row">
                <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="number">{{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Kewajiban Lancar</td>
            <td class="number">{{ number_format($data['current_liabilities']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Long-term Liabilities -->
        <tr>
            <td colspan="2" class="subsection-header">Kewajiban Jangka Panjang (Long-term Liabilities)</td>
        </tr>
        @foreach($data['long_term_liabilities']['accounts'] as $account)
            <tr class="account-row">
                <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="number">{{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-row">
            <td>Total Kewajiban Jangka Panjang</td>
            <td class="number">{{ number_format($data['long_term_liabilities']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Total Liabilities -->
        <tr class="total-row">
            <td>TOTAL KEWAJIBAN</td>
            <td class="number">{{ number_format($data['total_liabilities'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- EQUITY Section -->
        <tr>
            <td colspan="2" class="section-header equity">EKUITAS (EQUITY)</td>
        </tr>
        
        @foreach($data['equity']['accounts'] as $account)
            <tr class="account-row">
                <td style="padding-left: 20px;">{{ $account->kode }} - {{ $account->nama }}</td>
                <td class="number">{{ number_format($account->balance, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="account-row">
            <td style="padding-left: 20px;">Laba Ditahan (Retained Earnings)</td>
            <td class="number">{{ number_format($data['retained_earnings'], 0, ',', '.') }}</td>
        </tr>
        
        <tr class="total-row">
            <td>TOTAL EKUITAS</td>
            <td class="number">{{ number_format($data['total_equity'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Total Liabilities + Equity -->
        <tr class="grand-total-row">
            <td>TOTAL KEWAJIBAN & EKUITAS</td>
            <td class="number">{{ number_format($data['total_liabilities_and_equity'], 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        
        <!-- Balance Check -->
        <tr>
            <td colspan="2" style="text-align: center; font-weight: bold; background-color: {{ $data['is_balanced'] ? '#d1e7dd' : '#f8d7da' }}; padding: 10px;">
                @if($data['is_balanced'])
                    ✓ NERACA SEIMBANG (BALANCED)
                @else
                    ⚠ NERACA TIDAK SEIMBANG! Selisih: {{ number_format(abs($data['difference']), 0, ',', '.') }}
                @endif
            </td>
        </tr>
        
        <!-- Comparison Section -->
        @if($comparison)
            <tr><td colspan="2">&nbsp;</td></tr>
            <tr><td colspan="2">&nbsp;</td></tr>
            <tr>
                <td colspan="2" style="text-align: center; font-weight: bold; font-size: 12pt; background-color: #f0f0f0;">
                    PERBANDINGAN TANGGAL
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;">
                    {{ \Carbon\Carbon::parse($as_of_date)->format('d M Y') }} vs 
                    {{ \Carbon\Carbon::parse($comparison_date ?? now()->subMonth()->format('Y-m-d'))->format('d M Y') }}
                </td>
            </tr>
            <tr><td colspan="2">&nbsp;</td></tr>
            
            <tr style="background-color: #e0e0e0; font-weight: bold;">
                <td>Keterangan</td>
                <td>Periode Saat Ini</td>
                <td>Periode Sebelumnya</td>
                <td>Perubahan</td>
                <td>%</td>
            </tr>
            
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td colspan="5">ASET</td>
            </tr>
            <tr>
                <td>Aset Lancar</td>
                <td class="number">{{ number_format($comparison['current_assets']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_assets']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_assets']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_assets']['percentage'], 2) }}%</td>
            </tr>
            <tr>
                <td>Aset Tetap</td>
                <td class="number">{{ number_format($comparison['fixed_assets']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['fixed_assets']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['fixed_assets']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['fixed_assets']['percentage'], 2) }}%</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <td>Total Aset</td>
                <td class="number">{{ number_format($comparison['total_assets']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_assets']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_assets']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_assets']['percentage'], 2) }}%</td>
            </tr>
            
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td colspan="5">KEWAJIBAN</td>
            </tr>
            <tr>
                <td>Kewajiban Lancar</td>
                <td class="number">{{ number_format($comparison['current_liabilities']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_liabilities']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_liabilities']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['current_liabilities']['percentage'], 2) }}%</td>
            </tr>
            <tr>
                <td>Kewajiban Jangka Panjang</td>
                <td class="number">{{ number_format($comparison['long_term_liabilities']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['long_term_liabilities']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['long_term_liabilities']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['long_term_liabilities']['percentage'], 2) }}%</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <td>Total Kewajiban</td>
                <td class="number">{{ number_format($comparison['total_liabilities']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_liabilities']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_liabilities']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_liabilities']['percentage'], 2) }}%</td>
            </tr>
            
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td colspan="5">EKUITAS</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <td>Total Ekuitas</td>
                <td class="number">{{ number_format($comparison['total_equity']['current'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_equity']['previous'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_equity']['change'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($comparison['total_equity']['percentage'], 2) }}%</td>
            </tr>
        @endif
    </table>
</body>
</html>
