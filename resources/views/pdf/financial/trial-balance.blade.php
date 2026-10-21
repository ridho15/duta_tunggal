<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Neraca Saldo - {{ $startDate }} s/d {{ $endDate }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.35;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #15803d;
            padding-bottom: 6px;
            margin-bottom: 10px;
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

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #cbd5e0;
            letter-spacing: 0.3px;
        }

        .items-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            font-size: 8.5px;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .total-row td {
            background-color: #edf2f7;
            font-weight: bold;
            font-size: 9px;
            border-top: 2px solid #94a3b8;
        }

        .signatures-table {
            width: 100%;
            margin-top: 20px;
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
            height: 70px;
            position: relative;
            background-color: #ffffff;
        }

        .sig-title {
            font-size: 8px;
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
        $totals = $reportData['grand_totals'];
        $diff = abs($totals['period_debit'] - $totals['period_credit']);
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
                <h1>NERACA SALDO (TRIAL BALANCE)</h1>
                <div class="doc-subtitle">
                    Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Entitas: {{ $selectedCabang ? $selectedCabang->nama : 'Konsolidasian (Semua Cabang)' }} &bull; Mata Uang: IDR (Rp)
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Neraca Saldo --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 3%; text-align: center;">No</th>
                <th style="width: 10%; text-align: left;">Kode Akun</th>
                <th style="width: 35%; text-align: left;">Nama Rekening Perkiraan (COA)</th>
                <th style="width: 8%; text-align: center;">Tipe</th>
                <th style="width: 11%; text-align: right;">Saldo Awal (Rp)</th>
                <th style="width: 11%; text-align: right;">Mutasi Debit (Rp)</th>
                <th style="width: 11%; text-align: right;">Mutasi Kredit (Rp)</th>
                <th style="width: 11%; text-align: right;">Saldo Akhir (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData['rows'] as $index => $row)
                @php $isParent = $row->is_parent; @endphp
                <tr style="{{ $isParent ? 'font-weight: bold; background-color: #f8fafc;' : '' }}">
                    <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; {{ $isParent ? 'color: #0f172a;' : 'padding-left: 10px; color: #334155;' }}">
                        {{ $row->code }}
                    </td>
                    <td style="{{ $isParent ? 'color: #0f172a;' : 'padding-left: 12px; color: #334155;' }}">
                        {{ $row->name }}
                    </td>
                    <td style="text-align: center; color: #475569; font-size: 8px;">
                        {{ $row->type }} ({{ $row->normal_balance }})
                    </td>
                    <td style="text-align: right; font-family: monospace;">
                        {{ number_format($row->beginning_balance, 2, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-family: monospace; {{ $row->period_debit > 0 ? 'color: #15803d;' : 'color: #94a3b8;' }}">
                        {{ $row->period_debit > 0 ? number_format($row->period_debit, 2, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; {{ $row->period_credit > 0 ? 'color: #1d4ed8;' : 'color: #94a3b8;' }}">
                        {{ $row->period_credit > 0 ? number_format($row->period_credit, 2, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: 600;">
                        {{ number_format($row->ending_balance, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right; text-transform: uppercase; padding-right: 8px;">
                    GRAND TOTAL MUTASI:
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($totals['beginning_balance'], 2, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace; color: #15803d;">
                    {{ number_format($totals['period_debit'], 2, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace; color: #1d4ed8;">
                    {{ number_format($totals['period_credit'], 2, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($totals['ending_balance'], 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div style="font-size: 8px; color: #475569; margin-bottom: 10px;">
        Status Keseimbangan Mutasi:
        <strong style="color: {{ $isBalanced ? '#15803d' : '#b91c1c' }};">
            {{ $isBalanced ? 'SEIMBANG (DEBIT = KREDIT)' : 'SELISIH: Rp ' . number_format($diff, 2, ',', '.') }}
        </strong>
    </div>

    {{-- Kolom Otorisasi Tanda Tangan --}}
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
                        Manager Keuangan / Direktur<br>
                        Tgl: .................................
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
