<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Penyusutan Aset - {{ $year }}_{{ $month }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #2d3748;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-bottom: 2.5px solid #15803d;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #1a202c;
            letter-spacing: 0.5px;
        }

        .company-tag {
            display: inline-block;
            font-size: 8.5px;
            font-weight: bold;
            color: #15803d;
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            padding: 1px 4px;
            border-radius: 3px;
            margin-left: 4px;
        }

        .company-address {
            font-size: 8.5px;
            color: #4a5568;
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
            font-size: 10px;
            font-weight: bold;
            color: #1a202c;
            margin-top: 2px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 14px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .data-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-size: 8.5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .sign-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .sign-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8.5px;
        }

        .sign-box {
            height: 45px;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">
                    DUTA TUNGGAL <span class="company-tag">ERP</span>
                </div>
                <div class="company-address">
                    Cabang: {{ $cabang->nama ?? 'Konsolidasian (Semua Cabang)' }}<br>
                    Modul Akuntansi & Manajemen Aset Tetap
                </div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h1>REKAPITULASI PENYUSUTAN BULANAN</h1>
                <div class="doc-subtitle">Periode: {{ $periodLabel }}</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Dicetak: {{ now()->translatedFormat('d F Y, H:i') }} WIB
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Rekap Data --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">No</th>
                <th style="width: 10%;">Kode Aset</th>
                <th style="width: 20%;">Nama Barang / Aset</th>
                <th style="width: 10%;">Cabang</th>
                <th style="width: 12%;">Akun Beban (Dr)</th>
                <th style="width: 12%;">Akun Akum. (Cr)</th>
                <th style="width: 11%;" class="text-right">Biaya Perolehan</th>
                <th style="width: 11%;" class="text-right">Beban Bulan Ini</th>
                <th style="width: 11%;" class="text-right">Nilai Buku Akhir</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCost = 0;
                $totalDepreciation = 0;
                $totalBookValue = 0;
            @endphp
            @forelse ($items as $idx => $item)
                @php
                    $totalCost += (float) ($item->asset->purchase_cost ?? 0);
                    $totalDepreciation += (float) $item->amount;
                    $totalBookValue += (float) $item->book_value;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td style="font-family: 'Courier New', monospace; font-weight: bold;">{{ $item->asset->code ?? '-' }}</td>
                    <td><strong>{{ $item->asset->name ?? '-' }}</strong></td>
                    <td>{{ $item->asset->cabang->nama ?? '-' }}</td>
                    <td style="font-family: 'Courier New', monospace;">
                        {{ $item->asset->depreciationExpenseCoa->code ?? '-' }} - {{ \Illuminate\Support\Str::limit($item->asset->depreciationExpenseCoa->name ?? '', 18) }}
                    </td>
                    <td style="font-family: 'Courier New', monospace;">
                        {{ $item->asset->accumulatedDepreciationCoa->code ?? '-' }} - {{ \Illuminate\Support\Str::limit($item->asset->accumulatedDepreciationCoa->name ?? '', 18) }}
                    </td>
                    <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($item->asset->purchase_cost ?? 0) }}</td>
                    <td class="text-right" style="font-weight: bold; color: #15803d;">{{ \App\Helpers\MoneyHelper::rupiah($item->amount) }}</td>
                    <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($item->book_value) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px; color: #94a3b8;">
                        Tidak ada catatan penyusutan aset berstatus recorded untuk periode {{ $periodLabel }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($items->isNotEmpty())
            <tfoot>
                <tr style="background-color: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1;">
                    <td colspan="6" class="text-right" style="text-transform: uppercase;">Total Beban Penyusutan Periode Ini:</td>
                    <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($totalCost) }}</td>
                    <td class="text-right" style="color: #15803d; font-size: 10px;">{{ \App\Helpers\MoneyHelper::rupiah($totalDepreciation) }}</td>
                    <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($totalBookValue) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Tanda Tangan Pengesahan --}}
    <table class="sign-table">
        <tr>
            <td>
                Dipersiapkan Oleh,<br>
                <strong>Accounting Staff</strong>
                <div class="sign-box"></div>
                <div class="sign-name">{{ Auth::user()->name ?? 'Accounting' }}</div>
                <div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">Tgl: {{ now()->translatedFormat('d F Y') }}</div>
            </td>
            <td>
                Diperiksa Oleh,<br>
                <strong>Finance Supervisor</strong>
                <div class="sign-box"></div>
                <div class="sign-name">( ............................................ )</div>
                <div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">Tgl: .................................</div>
            </td>
            <td>
                Disetujui Oleh,<br>
                <strong>Finance & Accounting Manager</strong>
                <div class="sign-box"></div>
                <div class="sign-name">( ............................................ )</div>
                <div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">Tgl: .................................</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Dokumen Laporan Keuangan Sah — Duta Tunggal ERP | Dicetak dari Sistem Resmi
    </div>
</body>
</html>
