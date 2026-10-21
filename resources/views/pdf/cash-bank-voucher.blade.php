<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Kas/Bank - {{ $transaction->number }}</title>
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

        .meta-table {
            width: 100%;
            margin-bottom: 12px;
        }

        .meta-table td {
            vertical-align: top;
            width: 50%;
        }

        .info-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            background-color: #f8fafc;
        }

        .info-card-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #15803d;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .info-row {
            margin-bottom: 3px;
        }

        .info-label {
            display: inline-block;
            width: 110px;
            color: #718096;
            font-size: 9.5px;
        }

        .info-val {
            font-weight: 600;
            color: #1a202c;
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

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .items-table th {
            background-color: #15803d;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #15803d;
            text-align: left;
        }

        .items-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .total-row td {
            background-color: #edf2f7;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #cbd5e0;
        }

        .signatures-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .signatures-table td {
            width: 25%;
            text-align: center;
            vertical-align: top;
            padding: 0 5px;
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
        $typeNames = [
            'cash_in' => 'BUKTI KAS MASUK (BKM)',
            'cash_out' => 'BUKTI KAS KELUAR (BKK)',
            'bank_in' => 'BUKTI BANK MASUK (BBM)',
            'bank_out' => 'BUKTI BANK KELUAR (BBK)',
        ];
        $docTitle = $typeNames[$transaction->type] ?? 'BUKTI KAS & BANK';
        $isIn = in_array($transaction->type, ['cash_in', 'bank_in']);
        $terbilang = \App\Services\DocumentPrintBuilder::terbilang((float)$transaction->amount) . ' rupiah';
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
                <h1>{{ $docTitle }}</h1>
                <div class="doc-number">{{ $transaction->number }}</div>
                <div style="font-size: 9px; color: #718096; margin-top: 2px;">
                    Cabang: {{ $transaction->cabang?->nama ?? 'Kantor Pusat' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Meta Info --}}
    <table class="meta-table">
        <tr>
            <td style="padding-right: 6px;">
                <div class="info-card">
                    <div class="info-card-title">Informasi Kas / Bank</div>
                    <div class="info-row">
                        <span class="info-label">Akun Kas/Bank:</span>
                        <span class="info-val">{{ $transaction->accountCoa?->name ?? '-' }} ({{ $transaction->accountCoa?->code }})</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Transaksi:</span>
                        <span class="info-val">{{ \Carbon\Carbon::parse($transaction->date)->translatedFormat('d F Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tipe Transaksi:</span>
                        <span class="info-val">{{ ucwords(str_replace('_', ' ', $transaction->type)) }}</span>
                    </div>
                </div>
            </td>
            <td style="padding-left: 6px;">
                <div class="info-card">
                    <div class="info-card-title">{{ $isIn ? 'Diterima Dari' : 'Dibayarkan Kepada' }}</div>
                    <div class="info-row">
                        <span class="info-label">Pihak Terkait:</span>
                        <span class="info-val">{{ $transaction->counterparty ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Pos Akun Utama:</span>
                        <span class="info-val">{{ $transaction->offsetCoa?->name ?? 'Multi-Pos Breakdown' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Keterangan:</span>
                        <span class="info-val">{{ $transaction->description ?: '-' }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Terbilang Box --}}
    <div class="terbilang-box">
        <div class="terbilang-title">Terbilang:</div>
        <div class="terbilang-text"># {{ ucwords($terbilang) }} #</div>
    </div>

    {{-- Rincian Tabel Pos --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 25%;">Kode &amp; Nama Akun COA</th>
                <th style="width: 45%;">Keterangan Pos Pengeluaran / Penerimaan</th>
                <th style="width: 25%; text-align: right;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @if ($transaction->transactionDetails->isNotEmpty())
                @foreach ($transaction->transactionDetails as $idx => $detail)
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $detail->chartOfAccount?->code }}</strong><br>
                            {{ $detail->chartOfAccount?->name }}
                        </td>
                        <td>{{ $detail->description ?: ($transaction->description ?: '-') }}</td>
                        <td style="text-align: right; font-weight: 600;">
                            {{ number_format($detail->amount, 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <strong>{{ $transaction->offsetCoa?->code }}</strong><br>
                        {{ $transaction->offsetCoa?->name }}
                    </td>
                    <td>{{ $transaction->description ?: 'Transaksi Operasional Kas & Bank' }}</td>
                    <td style="text-align: right; font-weight: 600;">
                        {{ number_format($transaction->amount, 2, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align: right; text-transform: uppercase;">Total Nominal:</td>
                <td style="text-align: right; color: #15803d; font-size: 11px;">
                    Rp {{ number_format($transaction->amount, 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Tanda Tangan --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Mengetahui / Menyetujui</div>
                    <div class="sig-line">( Pimpinan / Manager )</div>
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
                    <div class="sig-title">Kasir / Teller</div>
                    <div class="sig-line">( Petugas Kasir )</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">{{ $isIn ? 'Penyetor' : 'Penerima Uang' }}</div>
                    <div class="sig-line">( {{ $transaction->counterparty ?: 'Pihak Terkait' }} )</div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 15px; font-size: 8px; color: #a0aec0; text-align: right;">
        Dicetak secara otomatis oleh Duta Tunggal ERP pada {{ now()->translatedFormat('d F Y H:i:s') }}
    </div>
</body>
</html>
