<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Jurnal - {{ $header->reference }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 15mm 15mm 15mm;
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
            border-collapse: collapse;
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
            width: 100px;
            color: #718096;
            font-size: 9px;
        }

        .info-val {
            font-weight: 600;
            color: #1a202c;
            font-size: 9.5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #cbd5e0;
            letter-spacing: 0.3px;
        }

        .items-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .total-row td {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #cbd5e0;
        }

        .amount-words-box {
            border: 1px dashed #cbd5e0;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 9px;
            color: #334155;
        }

        .amount-words-box strong {
            color: #15803d;
            text-transform: uppercase;
            font-size: 8.5px;
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
            height: 85px;
            position: relative;
            background-color: #ffffff;
        }

        .sig-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }

        .sig-line {
            position: absolute;
            bottom: 8px;
            left: 12px;
            right: 12px;
            border-top: 1px solid #94a3b8;
            padding-top: 3px;
            font-size: 8.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $typeNames = [
            'manual' => 'JURNAL UMUM MANUAL',
            'adjustment' => 'JURNAL PENYESUAIAN (AJP)',
            'reversal' => 'JURNAL PEMBALIKAN (REVERSAL)',
            'purchase' => 'JURNAL TRANSAKSI PEMBELIAN',
            'purchase_invoice' => 'JURNAL TAGIHAN PEMBELIAN',
            'purchase_payment' => 'JURNAL PEMBAYARAN PEMBELIAN',
            'purchase_return' => 'JURNAL RETUR PEMBELIAN',
            'sales' => 'JURNAL TRANSAKSI PENJUALAN',
            'sales_invoice' => 'JURNAL FAKTUR PENJUALAN',
            'sales_payment' => 'JURNAL PENERIMAAN PIUTANG',
            'cash_bank' => 'JURNAL KAS & BANK',
            'cashbank_in' => 'JURNAL KAS / BANK MASUK',
            'cashbank_out' => 'JURNAL KAS / BANK KELUAR',
            'cashbank_transfer' => 'JURNAL MUTASI KAS / BANK',
            'inventory' => 'JURNAL MUTASI PERSEDIAAN',
        ];
        $docTitle = $typeNames[$header->journal_type] ?? 'BUKTI VOUCHER JURNAL';
        $terbilang = \App\Services\DocumentPrintBuilder::terbilang((float) $header->total_debit) . ' rupiah';
        $diff = abs((float) $header->total_debit - (float) $header->total_credit);
    @endphp

    {{-- Kop Surat Header --}}
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
                <h1>{{ $docTitle }}</h1>
                <div class="doc-number">{{ $header->reference }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                    Cabang: {{ $header->cabang?->nama ?? $header->cabang?->name ?? 'Kantor Pusat' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Meta Informasi Voucher --}}
    <table class="meta-table">
        <tr>
            <td style="padding-right: 6px;">
                <div class="info-card">
                    <div class="info-card-title">Informasi Voucher Jurnal</div>
                    <div class="info-row">
                        <span class="info-label">No. Referensi:</span>
                        <span class="info-val" style="font-family: monospace;">{{ $header->reference }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Buku:</span>
                        <span class="info-val">{{ \Carbon\Carbon::parse($header->date)->translatedFormat('d F Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tipe Jurnal:</span>
                        <span class="info-val">{{ ucwords(str_replace('_', ' ', $header->journal_type)) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status Keseimbangan:</span>
                        <span class="info-val" style="color: {{ $diff < 0.01 ? '#15803d' : '#b91c1c' }};">
                            {{ $diff < 0.01 ? 'SEIMBANG (DEBIT = KREDIT)' : 'TIDAK SEIMBANG' }}
                        </span>
                    </div>
                </div>
            </td>
            <td style="padding-left: 6px;">
                <div class="info-card">
                    <div class="info-card-title">Keterangan / Deskripsi</div>
                    <div class="info-row" style="min-height: 48px;">
                        <span class="info-val" style="font-weight: normal; color: #334155; line-height: 1.5;">
                            {{ $header->description ?: 'Tidak ada catatan tambahan untuk voucher jurnal ini.' }}
                        </span>
                    </div>
                    <div class="info-row" style="margin-top: 4px; border-top: 1px dashed #e2e8f0; padding-top: 3px;">
                        <span class="info-label" style="width: 80px;">Dicetak Pada:</span>
                        <span class="info-val" style="font-weight: normal; color: #64748b; font-size: 8.5px;">
                            {{ now()->translatedFormat('d F Y H:i') }} WIB
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Baris Jurnal (Double-Entry Debit/Credit) --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">No</th>
                <th style="width: 14%; text-align: left;">Kode Akun</th>
                <th style="width: 32%; text-align: left;">Nama Perkiraan (COA)</th>
                <th style="width: 26%; text-align: left;">Keterangan / Memo</th>
                <th style="width: 12%; text-align: right;">Debit (Rp)</th>
                <th style="width: 12%; text-align: right;">Kredit (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $index => $line)
                @php
                    $isDebit = (float) $line->debit > 0;
                    $isCredit = (float) $line->credit > 0;
                @endphp
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold; {{ $isCredit ? 'padding-left: 14px; color: #475569;' : 'color: #0f172a;' }}">
                        {{ $line->coa?->code ?? '-' }}
                    </td>
                    <td style="{{ $isCredit ? 'padding-left: 16px; color: #334155;' : 'font-weight: 600; color: #0f172a;' }}">
                        @if ($isCredit)
                            <span style="color: #94a3b8; font-family: monospace;">&bull;</span>
                        @endif
                        {{ $line->coa?->name ?? 'Rekening Tidak Ditemukan' }}
                    </td>
                    <td style="color: #475569;">
                        {{ $line->description ?: '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: 600; {{ $isDebit ? 'color: #15803d;' : 'color: #94a3b8;' }}">
                        {{ $isDebit ? number_format((float) $line->debit, 2, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: 600; {{ $isCredit ? 'color: #1d4ed8;' : 'color: #94a3b8;' }}">
                        {{ $isCredit ? number_format((float) $line->credit, 2, ',', '.') : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right; text-transform: uppercase; letter-spacing: 0.5px; padding-right: 12px;">
                    TOTAL MUTASI:
                </td>
                <td style="text-align: right; font-family: monospace; color: #15803d; font-size: 10px;">
                    {{ number_format((float) $header->total_debit, 2, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace; color: #1d4ed8; font-size: 10px;">
                    {{ number_format((float) $header->total_credit, 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Kotak Terbilang --}}
    <div class="amount-words-box">
        <strong>Terbilang:</strong> {{ ucwords($terbilang) }}
    </div>

    {{-- Kolom Otorisasi & Tanda Tangan Akuntansi --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Dibuat Oleh</div>
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
