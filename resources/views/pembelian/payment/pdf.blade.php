<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Pembayaran - {{ $payment->payment_number }}</title>
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
            font-size: 15px;
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
        }

        .doc-title .doc-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-partial { background-color: #fef3c7; color: #92400e; }
        .badge-draft { background-color: #f3f4f6; color: #374151; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }

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
            padding-bottom: 3px;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .info-grid {
            width: 100%;
        }

        .info-grid td {
            padding: 1.5px 0;
            font-size: 9.5px;
        }

        .info-grid .label {
            color: #718096;
            width: 38%;
        }

        .info-grid .colon {
            width: 4%;
            color: #a0aec0;
        }

        .info-grid .value {
            color: #1a202c;
            font-weight: 500;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        .items-table th {
            background-color: #15803d;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #15803d;
        }

        .items-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .total-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .total-box td {
            padding: 6px 10px;
            font-size: 10px;
        }

        .total-box .total-label {
            text-align: right;
            font-weight: bold;
            color: #2d3748;
            width: 75%;
        }

        .total-box .total-value {
            text-align: right;
            font-weight: bold;
            font-size: 12px;
            color: #15803d;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 4px;
        }

        .notes-box {
            border: 1px dashed #cbd5e0;
            border-radius: 6px;
            padding: 8px 10px;
            background-color: #ffffff;
            margin-bottom: 15px;
            font-size: 9px;
        }

        .notes-title {
            font-weight: bold;
            color: #4a5568;
            margin-bottom: 3px;
        }

        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .signature-table td {
            text-align: center;
            vertical-align: top;
            width: 25%;
        }

        .signature-title {
            font-size: 9px;
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 50px;
        }

        .signature-name {
            font-size: 9px;
            font-weight: bold;
            color: #1a202c;
            border-top: 1px solid #cbd5e0;
            display: inline-block;
            min-width: 120px;
            padding-top: 4px;
        }

        .signature-date {
            font-size: 8px;
            color: #718096;
            margin-top: 2px;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 8px;
            color: #a0aec0;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $st = strtolower((string)$payment->status);
    @endphp

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-address">
                    Jl. Industri Raya No. 88, Kawasan Industri<br>
                    Telp: (021) 8989-1234 | Email: finance@dutatunggal.co.id<br>
                    NPWP: 01.234.567.8-012.000
                </div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h1>BUKTI PENGELUARAN KAS / BANK</h1>
                <div class="doc-number">No. Voucher: {{ $payment->payment_number }}</div>
                <div>
                    @if ($st === 'paid')
                        <span class="doc-badge badge-paid">LUNAS</span>
                    @elseif ($st === 'partial')
                        <span class="doc-badge badge-partial">DIBAYAR SEBAGIAN</span>
                    @elseif ($st === 'draft')
                        <span class="doc-badge badge-draft">DRAFT</span>
                    @elseif ($st === 'cancelled')
                        <span class="doc-badge badge-cancelled">DIBATALKAN</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Meta Details --}}
    <table class="meta-table">
        <tr>
            <td style="padding-right: 5px;">
                <div class="info-card">
                    <div class="info-card-title">Penerima Pembayaran (Vendor)</div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">Nama Perusahaan</td>
                            <td class="colon">:</td>
                            <td class="value"><strong>{{ $payment->supplier?->perusahaan ?? 'Vendor' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">Kode Vendor</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->supplier?->code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Rekening Tujuan</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->target_bank_account ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Ref. Transfer Bank</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->transfer_reference_number ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="padding-left: 5px;">
                <div class="info-card">
                    <div class="info-card-title">Rincian Pengeluaran Dana</div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">Tanggal Bayar</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->translatedFormat('d F Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Metode Bayar</td>
                            <td class="colon">:</td>
                            <td class="value"><strong>{{ $payment->payment_method }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">Akun Kas / Bank</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->coa ? "({$payment->coa->code}) {$payment->coa->name}" : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Cabang</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $payment->cabang?->nama ?? 'Pusat' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 35%;">No. Faktur Pembelian (Vendor &amp; Sistem)</th>
                <th style="width: 18%;">Tgl &amp; Jatuh Tempo</th>
                <th style="width: 20%; text-align: right;">Total Tagihan</th>
                <th style="width: 22%; text-align: right;">Nominal Dibayar (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payment->vendorPaymentDetail as $idx => $detail)
                @php
                    $inv = $detail->invoice;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $inv?->invoice_number ?? 'Invoice #' . $detail->invoice_id }}</strong>
                        @if ($inv?->supplier_invoice_number)
                            <br><span style="color: #718096; font-size: 8.5px;">Vendor No: {{ $inv->supplier_invoice_number }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $inv?->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}
                        <br><span style="color: #718096; font-size: 8.5px;">Due: {{ $inv?->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '-' }}</span>
                    </td>
                    <td style="text-align: right;">
                        {{ \App\Helpers\MoneyHelper::rupiah($inv?->total ?? 0) }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #15803d;">
                        {{ \App\Helpers\MoneyHelper::rupiah($detail->amount) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #a0aec0; padding: 15px;">
                        Tidak ada rincian faktur yang tercatat.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Total Box --}}
    <table class="total-box">
        <tr>
            <td class="total-label">TOTAL PENGELUARAN KAS / BANK:</td>
            <td class="total-value" style="width: 25%;">
                {{ \App\Helpers\MoneyHelper::rupiah($payment->total_payment) }}
            </td>
        </tr>
    </table>

    {{-- Catatan --}}
    @if ($payment->notes)
        <div class="notes-box">
            <div class="notes-title">Catatan / Keterangan Pembayaran:</div>
            <div>{{ $payment->notes }}</div>
        </div>
    @endif

    {{-- Tanda Tangan (4 Kolom) --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">Dibuat Oleh (Finance)</div>
                <div class="signature-name">{{ auth()->user()?->name ?? 'Staff Finance' }}</div>
                <div class="signature-date">Tgl: {{ now()->format('d/m/Y') }}</div>
            </td>
            <td>
                <div class="signature-title">Diperiksa Oleh (Accounting)</div>
                <div class="signature-name">( .................................... )</div>
                <div class="signature-date">Tgl: ..........................</div>
            </td>
            <td>
                <div class="signature-title">Disetujui Oleh (Direktur)</div>
                <div class="signature-name">( .................................... )</div>
                <div class="signature-date">Tgl: ..........................</div>
            </td>
            <td>
                <div class="signature-title">Diterima Oleh (Vendor)</div>
                <div class="signature-name">{{ $payment->supplier?->kontak_person ?? $payment->supplier?->perusahaan ?? 'Penerima' }}</div>
                <div class="signature-date">Tgl: ..........................</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Dokumen ini diterbitkan secara otomatis oleh Duta Tunggal ERP pada {{ now()->format('d/m/Y H:i:s') }} WIB dan sah sebagai bukti pengeluaran kas/bank perusahaan.
    </div>
</body>
</html>
