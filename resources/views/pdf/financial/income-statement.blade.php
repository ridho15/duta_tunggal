<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi - {{ $startDate }} s/d {{ $endDate }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #15803d;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }

        .company-address {
            font-size: 8.5px;
            color: #475569;
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 14px;
            font-weight: 800;
            color: #15803d;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-title .doc-subtitle {
            font-size: 9px;
            font-weight: 600;
            color: #334155;
            margin-top: 2px;
        }

        .section-header {
            background-color: #f1f5f9;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 8px;
            margin-top: 10px;
            margin-bottom: 4px;
            border-left: 3px solid #15803d;
        }

        .line-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .line-table td {
            padding: 3px 6px;
            font-size: 8.5px;
        }

        .line-subtotal {
            background-color: #f8fafc;
            font-weight: bold;
            border-top: 1px solid #cbd5e0;
            border-bottom: 1px solid #cbd5e0;
        }

        .highlight-box {
            border: 1.5px solid #15803d;
            background-color: #f0fdf4;
            padding: 6px 10px;
            margin: 8px 0;
            border-radius: 4px;
        }

        .highlight-box.loss {
            border-color: #e11d48;
            background-color: #fff1f2;
        }

        .signatures-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .sig-box {
            border: 1px solid #cbd5e0;
            border-radius: 5px;
            padding: 6px;
            height: 75px;
            position: relative;
            background-color: #ffffff;
        }

        .sig-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 3px;
        }

        .sig-line {
            position: absolute;
            bottom: 6px;
            left: 10px;
            right: 10px;
            border-top: 1px solid #94a3b8;
            padding-top: 2px;
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $salesRev = $reportData['sales_revenue']['total'];
        $grossProf = $reportData['gross_profit'];
        $opExp = $reportData['operating_expenses']['total'];
        $netProf = $reportData['net_profit'];
        $isProfit = $netProf >= 0;
    @endphp

    {{-- Kop Surat --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">CV. DUTA TUNGGAL</div>
                <div class="company-address">
                    Jl. Raya Bypass Krian KM 32, Sidoarjo, Jawa Timur<br>
                    Telp: (031) 8971234 &bull; Email: accounting@dutatunggal.co.id
                </div>
            </td>
            <td class="doc-title" style="width: 45%;">
                <h1>LAPORAN LABA RUGI</h1>
                <div class="doc-subtitle">
                    Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}
                </div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">
                    Entitas: {{ $selectedCabang ? $selectedCabang->nama : 'Konsolidasian (Semua Cabang)' }} &bull; IDR
                </div>
            </td>
        </tr>
    </table>

    {{-- 1. PENDAPATAN USAHA --}}
    <div class="section-header">1. PENDAPATAN USAHA (SALES REVENUE)</div>
    <table class="line-table">
        @foreach ($reportData['sales_revenue']['accounts'] as $acc)
            <tr>
                <td style="width: 15%; font-family: monospace; color: #64748b;">{{ $acc['code'] }}</td>
                <td style="width: 55%;">{{ $acc['name'] }}</td>
                <td style="width: 30%; text-align: right; font-family: monospace;">
                    {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                </td>
            </tr>
        @endforeach
        <tr class="line-subtotal">
            <td colspan="2" style="text-align: right; text-transform: uppercase;">TOTAL PENDAPATAN USAHA:</td>
            <td style="text-align: right; font-family: monospace; font-weight: bold; color: #0f172a;">
                Rp {{ number_format($salesRev, 2, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- 2. HARGA POKOK PENJUALAN --}}
    <div class="section-header">2. BEBAN POKOK PENJUALAN (HPP / COGS)</div>
    <table class="line-table">
        @foreach ($reportData['cogs']['accounts'] as $acc)
            <tr>
                <td style="width: 15%; font-family: monospace; color: #64748b;">{{ $acc['code'] }}</td>
                <td style="width: 55%;">{{ $acc['name'] }}</td>
                <td style="width: 30%; text-align: right; font-family: monospace;">
                    {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                </td>
            </tr>
        @endforeach
        <tr class="line-subtotal">
            <td colspan="2" style="text-align: right; text-transform: uppercase;">TOTAL HARGA POKOK PENJUALAN:</td>
            <td style="text-align: right; font-family: monospace; font-weight: bold; color: #0f172a;">
                Rp {{ number_format($reportData['cogs']['total'], 2, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- LABA KOTOR --}}
    <div class="highlight-box">
        <table style="width: 100%;">
            <tr>
                <td style="font-weight: bold; text-transform: uppercase;">LABA KOTOR (GROSS PROFIT)</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; font-size: 10.5px; color: #15803d;">
                    Rp {{ number_format($grossProf, 2, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- 3. BEBAN OPERASIONAL --}}
    <div class="section-header">3. BEBAN OPERASIONAL (OPERATING EXPENSES)</div>
    <table class="line-table">
        @foreach ($reportData['operating_expenses']['accounts'] as $acc)
            <tr>
                <td style="width: 15%; font-family: monospace; color: #64748b;">{{ $acc['code'] }}</td>
                <td style="width: 55%;">{{ $acc['name'] }}</td>
                <td style="width: 30%; text-align: right; font-family: monospace;">
                    {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                </td>
            </tr>
        @endforeach
        <tr class="line-subtotal">
            <td colspan="2" style="text-align: right; text-transform: uppercase;">TOTAL BEBAN OPERASIONAL:</td>
            <td style="text-align: right; font-family: monospace; font-weight: bold; color: #0f172a;">
                Rp {{ number_format($opExp, 2, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- LABA OPERASIONAL --}}
    <table style="width: 100%; border: 1px solid #cbd5e0; padding: 4px 6px; background-color: #f8fafc; margin-bottom: 6px;">
        <tr>
            <td style="font-weight: bold; font-size: 9px;">LABA OPERASIONAL (EBIT)</td>
            <td style="text-align: right; font-family: monospace; font-weight: bold; font-size: 9.5px;">
                Rp {{ number_format($reportData['operating_profit'], 2, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- 4. PENDAPATAN & BEBAN LAIN --}}
    <div class="section-header">4. PENDAPATAN / (BEBAN) NON-OPERASIONAL</div>
    <table class="line-table">
        <tr>
            <td style="width: 70%;">Pendapatan Lain-lain</td>
            <td style="width: 30%; text-align: right; font-family: monospace;">
                {{ number_format($reportData['other_income']['total'], 2, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td style="width: 70%;">Beban Lain-lain</td>
            <td style="width: 30%; text-align: right; font-family: monospace;">
                ({{ number_format($reportData['other_expense']['total'], 2, ',', '.') }})
            </td>
        </tr>
        @if ($reportData['tax_expense']['total'] > 0)
            <tr>
                <td style="width: 70%;">Beban Pajak Penghasilan</td>
                <td style="width: 30%; text-align: right; font-family: monospace;">
                    ({{ number_format($reportData['tax_expense']['total'], 2, ',', '.') }})
                </td>
            </tr>
        @endif
    </table>

    {{-- GRAND TOTAL: LABA BERSIH --}}
    <div class="highlight-box {{ $isProfit ? '' : 'loss' }}" style="padding: 10px;">
        <table style="width: 100%;">
            <tr>
                <td style="font-weight: bold; font-size: 11px; text-transform: uppercase; color: {{ $isProfit ? '#14532d' : '#881337' }};">
                    {{ $isProfit ? 'LABA BERSIH TAHUN / PERIODE BERJALAN' : 'RUGI BERSIH TAHUN / PERIODE BERJALAN' }}
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: 800; font-size: 13px; color: {{ $isProfit ? '#15803d' : '#e11d48' }};">
                    Rp {{ number_format($netProf, 2, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Kolom Otorisasi --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Disiapkan Oleh</div>
                    <div class="sig-line">
                        Staff Akuntansi / Pembukuan<br>
                        Tgl: .................................
                    </div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diperiksa Oleh</div>
                    <div class="sig-line">
                        Supervisor Akuntansi<br>
                        Tgl: .................................
                    </div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Disetujui Oleh</div>
                    <div class="sig-line">
                        Direktur Keuangan<br>
                        Tgl: .................................
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
