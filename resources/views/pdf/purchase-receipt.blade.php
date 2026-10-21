<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Penerimaan Barang - {{ $receipt->receipt_number ?: ('GRN-' . $receipt->id) }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            color: #2d3748;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #1a202c;
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
            font-size: 9.5px;
            color: #4a5568;
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 15px;
            font-weight: 800;
            color: #2b6cb0;
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
            font-size: 10px;
        }

        .info-box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 12px;
        }

        .info-box-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 2px;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .table-items th {
            background-color: #edf2f7;
            color: #2d3748;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #cbd5e0;
            text-align: left;
        }

        .table-items td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }

        .table-items tr:nth-child(even) {
            background-color: #f7fafc;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 10px;
        }

        .signature-line {
            margin-top: 55px;
            border-bottom: 1px solid #4a5568;
            width: 70%;
            margin-left: auto;
            margin-right: auto;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-completed { background-color: #c6f6d5; color: #22543d; }
        .badge-draft { background-color: #feebc8; color: #744210; }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">{{ $cabang->nama ?? 'PT. DUTA TUNGGAL' }}</div>
                <div class="company-address">
                    {{ $cabang->alamat ?? 'Kantor Pusat & Pergudangan' }}<br>
                    Telp: {{ $cabang->telepon ?? '-' }} &bull; Email: {{ $cabang->email ?? 'info@dutatunggal.co.id' }}
                </div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h1>Surat Jalan Masuk</h1>
                <div style="font-size: 9px; color: #718096; text-transform: uppercase; font-weight: 600;">Bukti Penerimaan Barang (GRN)</div>
                <div class="doc-number">{{ $receipt->receipt_number ?: ('GRN-' . $receipt->id) }}</div>
            </td>
        </tr>
    </table>

    {{-- Detail Pengiriman & PO --}}
    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; padding-right: 10px;">
                <div class="info-box">
                    <div class="info-box-title">Informasi Supplier / Pengirim</div>
                    <div style="font-size: 11px; font-weight: bold; color: #1a202c;">
                        {{ $receipt->purchaseOrder->supplier->name ?? 'Supplier' }}
                    </div>
                    <div style="color: #4a5568; margin-top: 2px;">
                        Kode: {{ $receipt->purchaseOrder->supplier->kode ?? '-' }}<br>
                        Alamat: {{ $receipt->purchaseOrder->supplier->alamat ?? '-' }}<br>
                        Telepon: {{ $receipt->purchaseOrder->supplier->telepon ?? '-' }}
                    </div>
                </div>
            </td>
            <td style="width: 52%;">
                <div class="info-box">
                    <div class="info-box-title">Dokumen & Lokasi Penerimaan</div>
                    <table style="width: 100%; font-size: 9.5px;" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 42%; color: #718096;">Tanggal Terima:</td>
                            <td style="font-weight: bold;">{{ optional($receipt->receipt_date)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="color: #718096;">No. Surat Jalan Vendor:</td>
                            <td style="font-weight: bold; font-family: monospace;">{{ $receipt->supplier_delivery_note_number ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #718096;">No. Purchase Order (PO):</td>
                            <td style="font-weight: bold; font-family: monospace;">{{ $receipt->purchaseOrder->po_number ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #718096;">Penerima Gudang:</td>
                            <td style="font-weight: bold;">{{ $receipt->receivedBy->name ?? 'Gudang' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Barang Diterima --}}
    <table class="table-items" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th style="width: 90px;">Kode / SKU</th>
                <th>Deskripsi Barang</th>
                <th style="width: 80px;">Gudang / Rak</th>
                <th style="width: 45px;" class="text-center">Qty PO</th>
                <th style="width: 50px;" class="text-center">Tiba</th>
                <th style="width: 50px;" class="text-center">Baik</th>
                <th style="width: 45px;" class="text-center">Tolak</th>
                <th style="width: 45px;" class="text-center">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->purchaseReceiptItem as $index => $item)
                @php
                    $poItem = $item->purchaseOrderItem;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-size: 9px;">{{ $item->product->sku ?? '-' }}</td>
                    <td>
                        <div style="font-weight: bold;">{{ $item->product->name ?? '-' }}</div>
                        @if ($item->qty_rejected > 0 && $item->reason_rejected)
                            <div style="font-size: 8.5px; color: #c53030; margin-top: 1px;">
                                Alasan tolak: {{ $item->reason_rejected }}
                            </div>
                        @endif
                    </td>
                    <td style="font-size: 9px;">
                        {{ $item->warehouse->name ?? 'Gudang' }}
                        @if ($item->rak && $item->rak->name)
                            <br><span style="color: #718096;">Rak: {{ $item->rak->name }}</span>
                        @endif
                    </td>
                    <td class="text-center" style="color: #718096;">{{ number_format($poItem->quantity ?? 0) }}</td>
                    <td class="text-center font-bold">{{ number_format($item->qty_received) }}</td>
                    <td class="text-center font-bold" style="color: #22543d; background-color: #f0fff4;">
                        {{ number_format($item->qty_accepted) }}
                    </td>
                    <td class="text-center font-bold" style="color: #c53030;">
                        {{ $item->qty_rejected > 0 ? number_format($item->qty_rejected) : '-' }}
                    </td>
                    <td class="text-center" style="font-size: 9px; color: #718096;">
                        {{ $item->product->unit->name ?? 'Pcs' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #edf2f7; font-weight: bold;">
                <td colspan="4" class="text-right" style="text-transform: uppercase; font-size: 9px;">Total Kuantitas:</td>
                <td class="text-center">{{ number_format($receipt->purchaseReceiptItem->sum(fn($i) => $i->purchaseOrderItem->quantity ?? 0)) }}</td>
                <td class="text-center">{{ number_format($receipt->purchaseReceiptItem->sum('qty_received')) }}</td>
                <td class="text-center" style="color: #22543d;">{{ number_format($receipt->purchaseReceiptItem->sum('qty_accepted')) }}</td>
                <td class="text-center" style="color: #c53030;">{{ number_format($receipt->purchaseReceiptItem->sum('qty_rejected')) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Catatan Fisik Penerimaan --}}
    @if ($receipt->clean_notes)
        <div style="margin-bottom: 12px; font-size: 9.5px; background-color: #f7fafc; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px;">
            <strong style="color: #4a5568;">Catatan Pemeriksaan Fisik:</strong>
            <p style="margin: 2px 0 0 0; color: #2d3748;">{{ $receipt->clean_notes }}</p>
        </div>
    @endif

    {{-- Tanda Tangan 3 Pihak --}}
    <table class="signature-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div>Pengemudi / Kurir Vendor,</div>
                <div class="signature-line"></div>
                <div style="margin-top: 3px; font-weight: bold; color: #718096;">( ......................................... )</div>
            </td>
            <td>
                <div>Petugas Gudang Penerima,</div>
                <div class="signature-line"></div>
                <div style="margin-top: 3px; font-weight: bold;">( {{ $receipt->receivedBy->name ?? 'Gudang' }} )</div>
            </td>
            <td>
                <div>Mengetahui Kepala Gudang,</div>
                <div class="signature-line"></div>
                <div style="margin-top: 3px; font-weight: bold; color: #718096;">( ......................................... )</div>
            </td>
        </tr>
    </table>
</body>
</html>
