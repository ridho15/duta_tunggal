<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pembelian - {{ $invoice->invoice_number }}</title>
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
            font-family: monospace;
        }

        .info-grid {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }

        .info-grid td {
            vertical-align: top;
            padding: 3px 0;
            font-size: 9.5px;
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
        }

        .info-box-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .items-table th {
            background-color: #f0fdf4;
            color: #166534;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            border: 1px solid #dcfce7;
        }

        .items-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }

        .summary-table {
            width: 100%;
            margin-top: 6px;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 3px 8px;
            font-size: 9.5px;
        }

        .summary-table .total-row td {
            border-top: 2px solid #15803d;
            font-size: 11px;
            font-weight: bold;
            color: #15803d;
            padding-top: 6px;
        }

        .signature-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .signature-box {
            text-align: center;
            vertical-align: top;
            width: 33.33%;
            padding: 0 10px;
        }

        .signature-title {
            font-size: 9px;
            font-weight: bold;
            color: #4a5568;
            margin-bottom: 50px;
        }

        .signature-line {
            border-bottom: 1px solid #718096;
            margin-bottom: 3px;
        }

        .signature-name {
            font-size: 9px;
            font-weight: bold;
            color: #2d3748;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #dcfce7;
            color: #15803d;
        }
    </style>
</head>
<body>
    {{-- Header Dokumen --}}
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="company-name">PT DUTA TUNGGAL JAYA</div>
                <div class="company-address">
                    Jl. Industri Raya Blok B No. 12-14, Kawasan Industri Jababeka<br>
                    Cikarang, Bekasi, Jawa Barat 17530<br>
                    Telp: (021) 8934567 | Email: finance@dutatunggal.co.id
                </div>
            </td>
            <td style="width: 40%;" class="doc-title">
                <h1>Faktur Pembelian</h1>
                <div class="doc-number">{{ $invoice->invoice_number }}</div>
                <div style="margin-top: 4px;">
                    <span class="badge-status">{{ $invoice->status_label }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Info Rekanan & Info Tagihan --}}
    <table class="info-grid">
        <tr>
            <td style="width: 50%; padding-right: 6px;">
                <div class="info-box">
                    <div class="info-box-title">Pemasok / Rekanan (Supplier)</div>
                    <div style="font-size: 11px; font-weight: bold; color: #1a202c;">
                        {{ $invoice->supplier?->perusahaan ?? $invoice->supplier_name }}
                    </div>
                    @if ($invoice->supplier?->name && $invoice->supplier->name !== $invoice->supplier->perusahaan)
                        <div style="color: #4a5568;">Up: {{ $invoice->supplier->name }}</div>
                    @endif
                    <div style="color: #718096; margin-top: 2px;">
                        {{ $invoice->supplier?->address ?? 'Alamat tidak tertera' }}<br>
                        Telp: {{ $invoice->supplier?->phone ?? $invoice->supplier?->telephone ?? '-' }}
                    </div>
                </div>
            </td>
            <td style="width: 50%; padding-left: 6px;">
                <div class="info-box">
                    <div class="info-box-title">Detail Tagihan &amp; Otorisasi</div>
                    <table style="width: 100%;">
                        <tr>
                            <td style="color: #718096; width: 45%;">Tgl. Faktur:</td>
                            <td style="font-weight: bold;">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #718096;">Tgl. Jatuh Tempo:</td>
                            <td style="font-weight: bold; {{ $invoice->isOverdue() ? 'color: #c53030;' : '' }}">
                                {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #718096;">No. Faktur Vendor:</td>
                            <td class="font-mono font-bold">{{ $invoice->supplier_invoice_number ?? '-' }}</td>
                        </tr>
                        @if ($invoice->tax_invoice_number)
                            <tr>
                                <td style="color: #718096;">No. Faktur Pajak:</td>
                                <td class="font-mono">{{ $invoice->tax_invoice_number }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td style="color: #718096;">Cabang:</td>
                            <td>{{ $invoice->cabang?->name ?? 'Pusat' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Referensi 3-Way Matching --}}
    @if (!empty($invoice->purchase_order_ids) || !empty($invoice->purchase_receipts))
        <div style="background-color: #f0fdf4; border: 1px dashed #86efac; border-radius: 4px; padding: 5px 10px; margin-bottom: 12px; font-size: 8.5px; color: #166534;">
            <strong>Referensi 3-Way Matching:</strong>
            @if (!empty($invoice->purchase_order_ids))
                PO Terkait: [ #{{ implode(', #', (array)$invoice->purchase_order_ids) }} ]
            @endif
            @if (!empty($invoice->purchase_receipts))
                &bull; GRN Terkait: [ GRN-{{ implode(', GRN-', (array)$invoice->purchase_receipts) }} ]
            @endif
        </div>
    @endif

    {{-- Tabel Rincian Barang --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">No</th>
                <th style="width: 14%;">Kode/SKU</th>
                <th style="width: 32%;">Deskripsi Barang</th>
                <th style="width: 8%;" class="text-right">Qty</th>
                <th style="width: 8%;">Satuan</th>
                <th style="width: 16%;" class="text-right">Harga Satuan</th>
                <th style="width: 18%;" class="text-right">Total Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->invoiceItem as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-mono">{{ $item->product?->sku ?? '-' }}</td>
                    <td>
                        <div class="font-bold">{{ $item->product?->name ?? 'Item Barang' }}</div>
                    </td>
                    <td class="text-right font-bold">{{ number_format((float)$item->quantity, 0, ',', '.') }}</td>
                    <td>{{ $item->product?->uom?->name ?? 'Pcs' }}</td>
                    <td class="text-right font-mono">Rp {{ number_format((float)$item->price, 0, ',', '.') }}</td>
                    <td class="text-right font-mono font-bold">Rp {{ number_format((float)$item->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 15px; color: #a0aec0;">
                        Tidak ada baris item barang pada faktur ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Ringkasan Finansial --}}
    <table style="width: 100%;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                <div style="font-size: 8.5px; color: #718096; line-height: 1.4;">
                    <strong>Ketentuan Pembayaran:</strong><br>
                    1. Pembayaran ditransfer ke rekening resmi rekanan sesuai invoice pemasok.<br>
                    2. Bukti pembayaran resmi diterbitkan melalui voucher pembayaran Duta Tunggal ERP.<br>
                    3. Dokumen ini adalah bukti penagihan sah yang telah diverifikasi terhadap fisik penerimaan gudang.
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="summary-table">
                    <tr>
                        <td style="color: #4a5568;">Subtotal DPP:</td>
                        <td class="text-right font-mono font-bold">Rp {{ number_format((float)$invoice->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="color: #4a5568;">PPN Masukan ({{ (float)($invoice->ppn_rate ?? 0) }}%):</td>
                        <td class="text-right font-mono">Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}</td>
                    </tr>
                    @if ($invoice->other_fee_total > 0)
                        <tr>
                            <td style="color: #4a5568;">Ongkir / Biaya Lain:</td>
                            <td class="text-right font-mono">Rp {{ number_format((float)$invoice->other_fee_total, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total Tagihan (AP):</td>
                        <td class="text-right font-mono">Rp {{ number_format((float)$invoice->total, 0, ',', '.') }}</td>
                    </tr>
                    @if ($invoice->accountPayable && $invoice->accountPayable->exists)
                        <tr>
                            <td style="color: #718096; font-size: 8.5px; padding-top: 4px;">Sisa Saldo Hutang:</td>
                            <td class="text-right font-mono font-bold" style="font-size: 9px; padding-top: 4px; color: {{ $invoice->accountPayable->remaining > 0 ? '#b45309' : '#15803d' }};">
                                Rp {{ number_format((float)$invoice->accountPayable->remaining, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Kolom Tanda Tangan --}}
    <table class="signature-table">
        <tr>
            <td class="signature-box">
                <div class="signature-title">Dibuat Oleh (Finance AP)</div>
                <div class="signature-line"></div>
                <div class="signature-name">( Staff Finance )</div>
            </td>
            <td class="signature-box">
                <div class="signature-title">Diverifikasi (Purchasing)</div>
                <div class="signature-line"></div>
                <div class="signature-name">( Purchasing Manager )</div>
            </td>
            <td class="signature-box">
                <div class="signature-title">Disetujui Pembayaran</div>
                <div class="signature-line"></div>
                <div class="signature-name">( Direktur / Finance Head )</div>
            </td>
        </tr>
    </table>
</body>
</html>
