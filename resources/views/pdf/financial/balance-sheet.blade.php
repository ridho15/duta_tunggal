<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Neraca - {{ $asOfDate }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
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

        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 10px;
        }

        .sheet-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 8px;
            border: 1px solid #cbd5e0;
            letter-spacing: 0.3px;
        }

        .sheet-table td {
            padding: 3px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .category-title {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 8.5px;
        }

        .subtotal-row {
            background-color: #f1f5f9;
            font-weight: bold;
        }

        .grandtotal-row {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 9.5px;
            border-top: 2px solid #64748b;
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
        $totAssets = $reportData['total_assets'];
        $totLiab = $reportData['total_liabilities'];
        $totEq = $reportData['total_equity'];
        $totLiabEq = $reportData['total_liabilities_and_equity'];
        $diff = abs((float) $reportData['difference']);
        $isBalanced = $diff < 0.01;
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
                <h1>LAPORAN NERACA (BALANCE SHEET)</h1>
                <div class="doc-subtitle">
                    Posisi Per: {{ \Carbon\Carbon::parse($asOfDate)->translatedFormat('d F Y') }}
                </div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">
                    Entitas: {{ $selectedCabang ? $selectedCabang->nama : 'Konsolidasian (Semua Cabang)' }} &bull; IDR
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Neraca --}}
    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width: 70%; text-align: left;">KETERANGAN REKENING PERKIRAAN</th>
                <th style="width: 30%; text-align: right;">NOMINAL (RP)</th>
            </tr>
        </thead>
        <tbody>
            {{-- ASET (AKTIVA) --}}
            <tr style="background-color: #f0fdf4;">
                <td colspan="2" style="font-weight: bold; font-size: 9.5px; color: #166534; text-transform: uppercase;">
                    ASET (AKTIVA)
                </td>
            </tr>

            <tr class="category-title">
                <td colspan="2">Aset Lancar (Current Assets)</td>
            </tr>
            @foreach ($reportData['current_assets']['accounts'] as $acc)
                <tr>
                    <td style="padding-left: 15px;">
                        <span style="font-family: monospace; color: #64748b; margin-right: 6px;">{{ $acc->code }}</span>
                        {{ $acc->name }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($acc->balance, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td style="padding-left: 10px; font-weight: bold;">Total Aset Lancar</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    {{ number_format($reportData['current_assets']['total'], 2, ',', '.') }}
                </td>
            </tr>

            <tr class="category-title">
                <td colspan="2">Aset Tetap &amp; Kontra Aset (Fixed Assets)</td>
            </tr>
            @foreach ($reportData['fixed_assets']['accounts'] as $acc)
                <tr>
                    <td style="padding-left: 15px;">
                        <span style="font-family: monospace; color: #64748b; margin-right: 6px;">{{ $acc->code }}</span>
                        {{ $acc->name }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($acc->balance, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            @foreach ($reportData['contra_assets']['accounts'] as $acc)
                <tr style="color: #991b1b;">
                    <td style="padding-left: 15px;">
                        <span style="font-family: monospace; margin-right: 6px;">{{ $acc->code }}</span>
                        {{ $acc->name }} (Akum. Penyusutan)
                    </td>
                    <td style="text-align: right; font-family: monospace;">({{ number_format(abs($acc->balance), 2, ',', '.') }})</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td style="padding-left: 10px; font-weight: bold;">Total Aset Tetap Bersih</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    {{ number_format($reportData['fixed_assets']['total'] - $reportData['contra_assets']['total'], 2, ',', '.') }}
                </td>
            </tr>

            <tr class="grandtotal-row" style="background-color: #dcfce7; color: #14532d;">
                <td style="text-transform: uppercase;">TOTAL ASET (AKTIVA)</td>
                <td style="text-align: right; font-family: monospace; font-size: 10px;">
                    Rp {{ number_format($totAssets, 2, ',', '.') }}
                </td>
            </tr>

            {{-- LIABILITAS & EKUITAS (PASIVA) --}}
            <tr style="background-color: #eff6ff;">
                <td colspan="2" style="font-weight: bold; font-size: 9.5px; color: #1e40af; text-transform: uppercase; padding-top: 10px;">
                    LIABILITAS &amp; EKUITAS (PASIVA)
                </td>
            </tr>

            <tr class="category-title">
                <td colspan="2">Liabilitas Jangka Pendek (Current Liabilities)</td>
            </tr>
            @foreach ($reportData['current_liabilities']['accounts'] as $acc)
                <tr>
                    <td style="padding-left: 15px;">
                        <span style="font-family: monospace; color: #64748b; margin-right: 6px;">{{ $acc->code }}</span>
                        {{ $acc->name }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($acc->balance, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td style="padding-left: 10px; font-weight: bold;">Total Liabilitas Jangka Pendek</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    {{ number_format($reportData['current_liabilities']['total'], 2, ',', '.') }}
                </td>
            </tr>

            @if ($reportData['long_term_liabilities']['total'] > 0 || count($reportData['long_term_liabilities']['accounts']) > 0)
                <tr class="category-title">
                    <td colspan="2">Liabilitas Jangka Panjang (Long-Term Liabilities)</td>
                </tr>
                @foreach ($reportData['long_term_liabilities']['accounts'] as $acc)
                    <tr>
                        <td style="padding-left: 15px;">
                            <span style="font-family: monospace; color: #64748b; margin-right: 6px;">{{ $acc->code }}</span>
                            {{ $acc->name }}
                        </td>
                        <td style="text-align: right; font-family: monospace;">{{ number_format($acc->balance, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td style="padding-left: 10px; font-weight: bold;">Total Liabilitas Jangka Panjang</td>
                    <td style="text-align: right; font-family: monospace; font-weight: bold;">
                        {{ number_format($reportData['long_term_liabilities']['total'], 2, ',', '.') }}
                    </td>
                </tr>
            @endif

            <tr class="subtotal-row" style="background-color: #fef3c7; color: #78350f;">
                <td style="font-weight: bold; text-transform: uppercase;">TOTAL LIABILITAS (KEWAJIBAN)</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    Rp {{ number_format($totLiab, 2, ',', '.') }}
                </td>
            </tr>

            <tr class="category-title">
                <td colspan="2">Ekuitas &amp; Modal Pemilik</td>
            </tr>
            @foreach ($reportData['equity']['accounts'] as $acc)
                <tr>
                    <td style="padding-left: 15px;">
                        <span style="font-family: monospace; color: #64748b; margin-right: 6px;">{{ $acc->code }}</span>
                        {{ $acc->name }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($acc->balance, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding-left: 15px; font-weight: bold;">
                    <span style="font-family: monospace; color: #64748b; margin-right: 6px;">3999</span>
                    Laba Ditahan &amp; Laba Periode Berjalan
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    {{ number_format($reportData['retained_earnings'], 2, ',', '.') }}
                </td>
            </tr>
            <tr class="subtotal-row" style="background-color: #f3e8ff; color: #581c87;">
                <td style="font-weight: bold; text-transform: uppercase;">TOTAL EKUITAS (MODAL)</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    Rp {{ number_format($totEq, 2, ',', '.') }}
                </td>
            </tr>

            <tr class="grandtotal-row" style="background-color: #dbeafe; color: #1e3a8a;">
                <td style="text-transform: uppercase;">TOTAL LIABILITAS &amp; EKUITAS</td>
                <td style="text-align: right; font-family: monospace; font-size: 10px;">
                    Rp {{ number_format($totLiabEq, 2, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <div style="font-size: 8px; color: #475569; margin-bottom: 10px;">
        Status Persamaan Akuntansi:
        <strong style="color: {{ $isBalanced ? '#15803d' : '#b91c1c' }};">
            {{ $isBalanced ? 'SEIMBANG (ASET = LIABILITAS + EKUITAS)' : 'SELISIH: Rp ' . number_format($diff, 2, ',', '.') }}
        </strong>
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
                        Direktur Utama<br>
                        Tgl: .................................
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
