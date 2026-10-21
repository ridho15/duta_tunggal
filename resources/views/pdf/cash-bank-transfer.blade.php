<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Transfer Kas/Bank - {{ $transfer->number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #2d3748;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #15803d;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1a202c;
            letter-spacing: 0.5px;
        }

        .company-address {
            font-size: 9px;
            color: #4a5568;
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 15px;
            font-weight: 800;
            color: #15803d;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-title .doc-number {
            font-size: 11px;
            font-weight: bold;
            color: #2d3748;
            margin-top: 2px;
            font-family: monospace;
        }

        .transfer-flow-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 10px 0;
        }

        .transfer-flow-table td {
            vertical-align: top;
            padding: 0;
        }

        .flow-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            background-color: #f8fafc;
        }

        .flow-card-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .terbilang-box {
            border: 1px dashed #cbd5e1;
            background-color: #f1f5f9;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 14px;
        }

        .terbilang-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 2px;
        }

        .terbilang-text {
            font-size: 10px;
            font-weight: 600;
            color: #0f172a;
            font-style: italic;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .details-table th {
            background-color: #15803d;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #15803d;
            text-align: left;
        }

        .details-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }

        .total-row td {
            background-color: #edf2f7;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #cbd5e0;
        }

        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }

        .sig-box {
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            padding: 8px;
            height: 90px;
            position: relative;
        }

        .sig-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #4a5568;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 4px;
        }

        .sig-line {
            position: absolute;
            bottom: 8px;
            left: 10px;
            right: 10px;
            border-top: 1px solid #a0aec0;
            padding-top: 2px;
            font-size: 8.5px;
            color: #718096;
        }
    </style>
</head>
<body>
    @php
        $terbilang = \App\Services\DocumentPrintBuilder::terbilang((float)$transfer->amount) . ' rupiah';
        $totalPengurangan = (float)$transfer->amount + (float)$transfer->other_costs;
    @endphp

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">CV. DUTA TUNGGAL</div>
                <div class="company-address">
                    Jl. Raya Bypass Krian KM 32, Sidoarjo, Jawa Timur<br>
                    Telp: (031) 8971234 &bull; Email: finance@dutatunggal.co.id
                </div>
            </td>
            <td class="doc-title" style="width: 45%;">
                <h1>BUKTI TRANSFER KAS &amp; BANK</h1>
                <div class="doc-number">{{ $transfer->number }}</div>
                <div style="font-size: 9px; color: #718096; margin-top: 2px;">
                    Tanggal: {{ \Carbon\Carbon::parse($transfer->date)->translatedFormat('d F Y') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Diagram Transfer Alur --}}
    <table class="transfer-flow-table">
        <tr>
            <td style="width: 48%;">
                <div class="flow-card" style="border-left: 3px solid #e11d48;">
                    <div class="flow-card-title" style="color: #be123c;">1. Sumber Dana (Rekening Asal)</div>
                    <div style="font-size: 11px; font-weight: bold; color: #1e293b;">
                        {{ $transfer->fromCoa?->name ?? '-' }}
                    </div>
                    <div style="font-size: 9px; color: #64748b; font-family: monospace;">
                        Kode Akun: {{ $transfer->fromCoa?->code }}
                    </div>
                    <div style="margin-top: 6px; font-size: 9.5px; color: #be123c; font-weight: bold;">
                        Pengurangan Saldo: Rp {{ number_format($totalPengurangan, 2, ',', '.') }}
                    </div>
                </div>
            </td>
            <td style="width: 48%;">
                <div class="flow-card" style="border-left: 3px solid #15803d;">
                    <div class="flow-card-title" style="color: #15803d;">2. Penerima Dana (Rekening Tujuan)</div>
                    <div style="font-size: 11px; font-weight: bold; color: #1e293b;">
                        {{ $transfer->toCoa?->name ?? '-' }}
                    </div>
                    <div style="font-size: 9px; color: #64748b; font-family: monospace;">
                        Kode Akun: {{ $transfer->toCoa?->code }}
                    </div>
                    <div style="margin-top: 6px; font-size: 9.5px; color: #15803d; font-weight: bold;">
                        Penambahan Saldo: Rp {{ number_format($transfer->amount, 2, ',', '.') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Terbilang Box --}}
    <div class="terbilang-box">
        <div class="terbilang-title">Terbilang Nominal Transfer:</div>
        <div class="terbilang-text"># {{ ucwords($terbilang) }} #</div>
    </div>

    {{-- Rincian Tabel Transfer --}}
    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 60%;">Keterangan &amp; Komponen Transfer</th>
                <th style="width: 35%; text-align: right;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td>
                    <strong>Nominal Pemindahan Saldo Bersih</strong><br>
                    <span style="color: #64748b; font-size: 9px;">Dari: {{ $transfer->fromCoa?->name }} &rarr; Ke: {{ $transfer->toCoa?->name }}</span>
                </td>
                <td style="text-align: right; font-weight: 600;">
                    {{ number_format($transfer->amount, 2, ',', '.') }}
                </td>
            </tr>
            @if ($transfer->other_costs > 0)
                <tr>
                    <td style="text-align: center;">2</td>
                    <td>
                        <strong>Biaya Administrasi Bank / Transfer</strong><br>
                        <span style="color: #64748b; font-size: 9px;">Pos Akun Beban: {{ $transfer->otherCostsCoa?->code ?? '-' }} - {{ $transfer->otherCostsCoa?->name ?? 'Biaya Admin' }}</span>
                    </td>
                    <td style="text-align: right; font-weight: 600;">
                        {{ number_format($transfer->other_costs, 2, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" style="text-align: right; text-transform: uppercase;">Total Kas/Bank yang Dikeluarkan:</td>
                <td style="text-align: right; color: #15803d; font-size: 11px;">
                    Rp {{ number_format($totalPengurangan, 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Catatan Tambahan --}}
    @if ($transfer->reference || $transfer->description)
        <div style="margin-bottom: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px;">
            @if ($transfer->reference)
                <div style="font-size: 9.5px; margin-bottom: 2px;">
                    <strong>No. Referensi Bank:</strong> <span style="font-family: monospace;">{{ $transfer->reference }}</span>
                </div>
            @endif
            @if ($transfer->description)
                <div style="font-size: 9.5px; color: #4a5568;">
                    <strong>Catatan:</strong> {{ $transfer->description }}
                </div>
            @endif
        </div>
    @endif

    {{-- Tanda Tangan --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Disetujui Oleh</div>
                    <div class="sig-line">( Pimpinan / Finance Manager )</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Dibukukan Oleh</div>
                    <div class="sig-line">( Akunting / Pembukuan )</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Petugas Pelaksana Transfer</div>
                    <div class="sig-line">( Kasir / Teller Bank )</div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 15px; font-size: 8px; color: #a0aec0; text-align: right;">
        Dicetak secara otomatis oleh Duta Tunggal ERP pada {{ now()->translatedFormat('d F Y H:i:s') }}
    </div>
</body>
</html>
