<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Penerimaan - {{ $voucherNumber }}</title>
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
    {{-- Voucher penerimaan kas/bank: tata letak identik dengan Voucher Pengeluaran Pembayaran Hutang (permintaan akunting 27 Nov 2026) --}}
    @php
        $st = strtolower((string) $receipt->status);
        // Pelunasan memakai saldo titip: bukan kas masuk (jurnal Dr Deposit Pelanggan / Cr Piutang)
        $fromDeposit = ! isset($deposit) && strtolower((string) $receipt->payment_method) === 'deposit';
    @endphp

    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">{{ $company['name'] }}</div>
                <div class="company-address">
                    {!! collect([$company['address'], $company['phone'] ? 'Telp: '.$company['phone'] : null, $company['email'] ? 'Email: '.$company['email'] : null, $company['npwp'] ? 'NPWP: '.$company['npwp'] : null])->filter()->map(fn ($l) => e($l))->implode('<br>') !!}
                </div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h1>{{ $fromDeposit ? 'BUKTI PEMAKAIAN SALDO TITIP' : 'BUKTI PENERIMAAN KAS / BANK' }}</h1>
                <div class="doc-number">No. Voucher: {{ $voucherNumber }}</div>
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

    <table class="meta-table">
        <tr>
            <td style="padding-right: 5px;">
                <div class="info-card">
                    <div class="info-card-title">Diterima Dari (Customer)</div>
                    <table class="info-grid">
                        <tr><td class="label">Nama Customer</td><td class="colon">:</td><td class="value"><strong>{{ $receipt->customer?->name ?? 'Customer' }}</strong></td></tr>
                        <tr><td class="label">Kode Customer</td><td class="colon">:</td><td class="value">{{ $receipt->customer?->code ?? '-' }}</td></tr>
                        <tr><td class="label">Bank Pengirim</td><td class="colon">:</td><td class="value">{{ $receipt->bank_name ?: '-' }}</td></tr>
                        <tr><td class="label">Ref. Transfer / Giro</td><td class="colon">:</td><td class="value">{{ $receipt->payment_reference ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="padding-left: 5px;">
                <div class="info-card">
                    <div class="info-card-title">Rincian Penerimaan Dana</div>
                    <table class="info-grid">
                        <tr><td class="label">Tanggal Terima</td><td class="colon">:</td><td class="value">{{ $receipt->payment_date ? \Carbon\Carbon::parse($receipt->payment_date)->translatedFormat('d F Y') : '-' }}</td></tr>
                        <tr><td class="label">Metode Bayar</td><td class="colon">:</td><td class="value"><strong>{{ $receipt->payment_method }}</strong></td></tr>
                        <tr><td class="label">Akun Kas / Bank</td><td class="colon">:</td><td class="value">{{ $receipt->coa ? "({$receipt->coa->code}) {$receipt->coa->name}" : '-' }}</td></tr>
                        <tr><td class="label">{{ isset($deposit) ? 'Cabang / No. Titip' : 'Cabang / No. Kwitansi' }}</td><td class="colon">:</td><td class="value">{{ $receipt->cabang?->nama ?? 'Pusat' }} · {{ $kwitansiNumber }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 35%;">{{ isset($deposit) ? 'Keterangan' : 'No. Faktur Penjualan' }}</th>
                <th style="width: 18%;">Tgl &amp; Jatuh Tempo</th>
                <th style="width: 20%; text-align: right;">Total Tagihan</th>
                <th style="width: 22%; text-align: right;">Nominal Diterima (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @if (isset($deposit))
                {{-- Titip dana customer: belum ada faktur, uang dicatat sebagai Deposit Pelanggan (utang ke customer) --}}
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <strong>Titip Dana Customer {{ $deposit->deposit_number }}</strong>
                        <br><span style="color: #718096; font-size: 8.5px;">Belum ada faktur; dipakai saat pelunasan invoice berikutnya</span>
                    </td>
                    <td>{{ $receipt->payment_date ? \Carbon\Carbon::parse($receipt->payment_date)->format('d/m/Y') : '-' }}</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; font-weight: bold; color: #15803d;">{{ \App\Helpers\MoneyHelper::rupiah($deposit->amount) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="4" style="color: #4a5568; font-size: 8.5px;">
                        Jurnal: Debit {{ $receipt->coa ? "({$receipt->coa->code}) {$receipt->coa->name}" : 'Kas / Bank' }}
                        · Kredit {{ $deposit->coa?->code ? "({$deposit->coa->code}) {$deposit->coa->name}" : 'Deposit Pelanggan' }}.
                        Sisa saldo titip: {{ \App\Helpers\MoneyHelper::rupiah($deposit->remaining_amount) }}.
                    </td>
                </tr>
            @else
            @forelse ($receipt->customerReceiptItem as $idx => $line)
                @php $inv = $line->invoice; @endphp
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $inv?->invoice_number ?? 'Invoice #' . $line->invoice_id }}</strong>
                        @if ($line->method)
                            <br><span style="color: #718096; font-size: 8.5px;">Metode: {{ $line->method }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $inv?->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}
                        <br><span style="color: #718096; font-size: 8.5px;">Due: {{ $inv?->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '-' }}</span>
                    </td>
                    <td style="text-align: right;">{{ \App\Helpers\MoneyHelper::rupiah($inv?->total ?? 0) }}</td>
                    <td style="text-align: right; font-weight: bold; color: #15803d;">{{ \App\Helpers\MoneyHelper::rupiah($line->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #a0aec0; padding: 15px;">Tidak ada rincian faktur yang tercatat.</td></tr>
            @endforelse
            @endif
            @if ((float) $receipt->overpayment_amount > 0)
                <tr>
                    <td></td>
                    <td colspan="3"><strong>Kelebihan bayar</strong> <span style="color: #718096; font-size: 8.5px;">(dicatat sebagai Deposit / Saldo Titip Customer)</span></td>
                    <td style="text-align: right; font-weight: bold; color: #15803d;">{{ \App\Helpers\MoneyHelper::rupiah($receipt->overpayment_amount) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="total-box">
        <tr>
            <td class="total-label">{{ $fromDeposit ? 'TOTAL PELUNASAN DARI SALDO TITIP:' : 'TOTAL PENERIMAAN KAS / BANK:' }}</td>
            <td class="total-value" style="width: 25%;">{{ \App\Helpers\MoneyHelper::rupiah($receipt->total_payment) }}</td>
        </tr>
    </table>

    @if ($fromDeposit)
        <div class="notes-box" style="margin-bottom: 8px;">
            <strong>Catatan akuntansi:</strong>
            <div>Tidak ada uang kas / bank masuk. Piutang dilunasi dari saldo titip customer: Debit Deposit Pelanggan, Kredit Piutang Usaha.</div>
        </div>
    @endif
    @if ($receipt->notes)
        <div class="notes-box">
            <div class="notes-title">Catatan / Keterangan Penerimaan:</div>
            <div>{{ $receipt->notes }}</div>
        </div>
    @endif

    <table class="signature-table">
        <tr>
            <td>
                @php $made = is_object($receipt) && method_exists($receipt, 'signatureFor') ? $receipt->signatureFor('created') : null; @endphp
                <div class="signature-title">Dibuat Oleh (Finance / Kasir)</div>
                @if ($made && ($img = $made->imagePath()))
                    <img src="{{ $img }}" alt="" style="max-height: 40px; max-width: 120px; margin-top: -40px;">
                @endif
                <div class="signature-name">{{ $made?->signer_name ?? $receipt->createdBy?->name ?? 'Staff Finance' }}</div>
                <div class="signature-date">Tgl: {{ $made ? $made->signed_at->format('d/m/Y') : '..........................' }}</div>
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
                <div class="signature-title">Disetor Oleh (Customer)</div>
                <div class="signature-name">{{ $receipt->customer?->name ?? 'Penyetor' }}</div>
                <div class="signature-date">Tgl: ..........................</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini diterbitkan secara otomatis oleh Duta Tunggal ERP pada {{ now()->format('d/m/Y H:i:s') }} WIB dan sah sebagai bukti penerimaan kas/bank perusahaan.
    </div>
</body>
</html>
