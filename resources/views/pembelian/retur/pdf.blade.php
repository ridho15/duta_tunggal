<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Retur Pembelian - {{ $return->nota_retur }}</title>
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
            font-size: 14px;
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

        .doc-title .doc-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .badge-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-draft { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

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
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #15803d;
        }

        .items-table td {
            padding: 5px 8px;
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
            padding: 5px 10px;
            font-size: 9.5px;
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
            font-size: 11px;
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
            width: 33.33%;
        }

        .signature-title {
            font-size: 9px;
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 45px;
        }

        .signature-name {
            font-size: 9px;
            font-weight: bold;
            color: #1a202c;
            border-top: 1px solid #cbd5e0;
            display: inline-block;
            min-width: 130px;
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
        $receipt = $return->purchaseReceipt;
        $po = $receipt?->purchaseOrder;
        $supplier = $receipt?->supplier ?? $po?->supplier;

        $totalQty = $return->purchaseReturnItem->sum(fn ($i) => (float)$i->qty_returned);
        $totalAmount = $return->purchaseReturnItem->sum(fn ($i) => (float)$i->qty_returned * (float)$i->unit_price);

        $badgeClass = match($return->status) {
            'approved' => 'badge-approved',
            'pending_approval' => 'badge-pending',
            'rejected' => 'badge-rejected',
            default => 'badge-draft',
        };
        $badgeText = match($return->status) {
            'approved' => 'Disetujui (Approved)',
            'pending_approval' => 'Menunggu Approval',
            'rejected' => 'Ditolak (Rejected)',
            default => 'Draft Disimpan',
        };
    @endphp

    {{-- Header Perusahaan & Judul Dokumen --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-address">
                    Jl. Industri Raya No. 88, Kawasan Industri<br>
                    Telp: (021) 8989-1234 | Email: purchasing@dutatunggal.co.id<br>
                    NPWP: 01.234.567.8-012.000
                </div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h1>Nota Retur Pembelian</h1>
                <div style="font-size: 9px; color: #718096; text-transform: uppercase; font-weight: 600;">Purchase Return &amp; Debit Note</div>
                <div class="doc-number">{{ $return->nota_retur }}</div>
                <div>
                    <span class="doc-badge {{ $badgeClass }}">{{ $badgeText }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Meta Informasi: Supplier & Dokumen Pengadaan --}}
    <table class="meta-table">
        <tr>
            <td style="padding-right: 6px;">
                <div class="info-card">
                    <div class="info-card-title">Informasi Pemasok (Vendor)</div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">Nama Vendor</td>
                            <td class="colon">:</td>
                            <td class="value"><strong>{{ $supplier?->perusahaan ?: ($supplier?->name ?: '-') }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">Kode Vendor</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $supplier?->code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Kontak / Telepon</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $supplier?->phone ?? $supplier?->telephone ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Alamat</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $supplier?->address ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="padding-left: 6px;">
                <div class="info-card">
                    <div class="info-card-title">Dokumen Sumber &amp; Pengiriman</div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">Tanggal Retur</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $return->return_date ? \Carbon\Carbon::parse($return->return_date)->translatedFormat('d F Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">No. Penerimaan (GRN)</td>
                            <td class="colon">:</td>
                            <td class="value"><strong>{{ $receipt?->receipt_number ?: '-' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">No. Purchase Order</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $po?->po_number ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Gudang / Cabang</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $receipt?->warehouse?->name ?: 'Gudang Utama' }} ({{ $return->cabang?->nama ?: 'Kantor Pusat' }})</td>
                        </tr>
                        <tr>
                            <td class="label">Surat Jalan Retur</td>
                            <td class="colon">:</td>
                            <td class="value">{{ $return->delivery_note ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabel Rincian Barang yang Diretur --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">No</th>
                <th style="width: 80px;">Kode Barang</th>
                <th>Nama Produk &amp; Deskripsi</th>
                <th style="width: 50px; text-align: center;">Satuan</th>
                <th style="width: 60px; text-align: center;">Qty Retur</th>
                <th style="width: 85px; text-align: right;">Harga PO (Rp)</th>
                <th style="width: 95px; text-align: right;">Subtotal (Rp)</th>
                <th style="width: 110px;">Alasan Pengembalian</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($return->purchaseReturnItem as $idx => $item)
                @php
                    $lineTotal = (float)$item->qty_returned * (float)$item->unit_price;
                @endphp
                <tr>
                    <td style="text-align: center; color: #718096;">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace; font-size: 8.5px;">{{ $item->product?->code ?? '-' }}</td>
                    <td>
                        <strong>{{ $item->product?->name ?? 'Produk' }}</strong>
                    </td>
                    <td style="text-align: center;">{{ $item->product?->unitOfMeasure?->name ?? 'Unit' }}</td>
                    <td style="text-align: center; font-weight: bold; color: #b45309;">
                        {{ number_format($item->qty_returned, 2) }}
                    </td>
                    <td style="text-align: right;">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format($lineTotal, 0, ',', '.') }}</td>
                    <td style="color: #4a5568;">{{ $item->reason ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Ringkasan Total & Potongan Hutang --}}
    <table class="total-box">
        <tr>
            <td class="total-label">Total Kuantitas Fisik:</td>
            <td class="total-value" style="width: 25%;">{{ number_format($totalQty, 2) }} Unit</td>
        </tr>
        <tr>
            <td class="total-label">Total Nilai Retur (DPP / Pemotong Hutang Dagang):</td>
            <td class="total-value" style="width: 25%; font-size: 12px;">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Catatan & Keterangan Tambahan --}}
    @if ($return->notes || $return->delivery_note || $return->credit_note_number)
        <div class="notes-box">
            <div class="notes-title">Catatan Dokumen &amp; Resolusi Vendor:</div>
            @if ($return->notes)
                <div>Catatan: {{ $return->notes }}</div>
            @endif
            @if ($return->delivery_note)
                <div>Surat Jalan Pengembalian: {{ $return->delivery_note }} {{ $return->physical_return_date ? ' | Tgl Pengiriman Fisik: ' . \Carbon\Carbon::parse($return->physical_return_date)->format('d/m/Y') : '' }}</div>
            @endif
            @if ($return->shipping_details)
                <div>Ekspedisi: {{ $return->shipping_details }}</div>
            @endif
            @if ($return->credit_note_number)
                <div>No. Credit Note Vendor: {{ $return->credit_note_number }} {{ $return->credit_note_amount ? ' (Rp ' . number_format($return->credit_note_amount, 0, ',', '.') . ')' : '' }}</div>
            @endif
        </div>
    @endif

    {{-- Kolom Tanda Tangan 3 Pihak --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">Dibuat Oleh,</div>
                <div class="signature-name">{{ $return->createdBy?->name ?? 'Staff Gudang/Purchasing' }}</div>
                <div class="signature-date">Tgl: {{ $return->created_at ? $return->created_at->format('d/m/Y') : date('d/m/Y') }}</div>
            </td>
            <td>
                <div class="signature-title">Disetujui Oleh,</div>
                <div class="signature-name">{{ $return->approvedBy?->name ?? '( ......................... )' }}</div>
                <div class="signature-date">Manajer Pembelian / Keuangan</div>
            </td>
            <td>
                <div class="signature-title">Diterima Pihak Vendor / Ekspedisi,</div>
                <div class="signature-name">( ........................................ )</div>
                <div class="signature-date">Nama Terang &amp; Cap Vendor</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Dokumen ini diterbitkan secara elektronik oleh Duta Tunggal ERP pada {{ now()->format('d/m/Y H:i:s') }} dan sah tanpa stempel basah jika telah disetujui dalam sistem.
    </div>
</body>
</html>
