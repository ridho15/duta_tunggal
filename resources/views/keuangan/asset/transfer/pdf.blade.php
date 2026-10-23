<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Mutasi Aset #{{ $transfer->id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #15803d;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .doc-title {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-no {
            text-align: right;
            font-size: 10px;
            color: #4b5563;
            font-family: monospace;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.info-table td.label {
            width: 25%;
            color: #6b7280;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
        }
        table.info-table td.value {
            width: 75%;
            color: #111827;
            font-weight: 500;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-approved { background-color: #dbeafe; color: #1e40af; }
        .badge-completed { background-color: #d1fae5; color: #065f46; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-space {
            height: 60px;
        }
        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 4px;
            font-weight: bold;
            font-size: 10px;
        }
        .signature-role {
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table style="width: 100%;" class="header">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-subtitle">Enterprise Resource Planning System</div>
            </td>
            <td style="width: 50%; vertical-align: middle;">
                <div class="doc-title">Berita Acara Mutasi Aset Tetap</div>
                <div class="doc-no">No: MUT-AST-{{ str_pad($transfer->id, 5, '0', STR_PAD_LEFT) }}</div>
                <div style="text-align: right; font-size: 9px; color: #6b7280;">
                    Tgl Dokumen: {{ $transfer->transfer_date ? $transfer->transfer_date->format('d/m/Y') : now()->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Ringkasan Status --}}
    <div style="margin-bottom: 16px; padding: 8px 12px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;">
        <table style="width: 100%;">
            <tr>
                <td style="font-size: 10px; color: #4b5563;">Status Mutasi Saat Ini:</td>
                <td style="text-align: right;">
                    @if ($transfer->status === 'pending')
                        <span class="badge badge-pending">Menunggu Persetujuan (Pending)</span>
                    @elseif ($transfer->status === 'approved')
                        <span class="badge badge-approved">Disetujui (Dalam Transit)</span>
                    @elseif ($transfer->status === 'completed')
                        <span class="badge badge-completed">Selesai (Completed)</span>
                    @else
                        <span class="badge badge-cancelled">Dibatalkan (Cancelled)</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Rincian Aset Tetap --}}
    <div class="section-title">1. Identitas Aset Tetap Yang Dimutasi</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Kode Aset</th>
                <th>Nama Aset</th>
                <th>Kategori Akun (COA)</th>
                <th style="text-align: right;">Nilai Perolehan</th>
                <th style="text-align: right;">Nilai Buku Bersih (NBV)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-family: monospace; font-weight: bold;">{{ $transfer->asset->code ?? '-' }}</td>
                <td style="font-weight: bold;">{{ $transfer->asset->name ?? '-' }}</td>
                <td>{{ $transfer->asset->assetCoa->name ?? '-' }}</td>
                <td style="text-align: right; font-family: monospace;">{{ \App\Helpers\MoneyHelper::rupiah($transfer->asset->purchase_cost ?? 0) }}</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #15803d;">{{ \App\Helpers\MoneyHelper::rupiah($transfer->asset->book_value ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Rute Pemindahan Cabang --}}
    <div class="section-title">2. Rute Pemindahan & Lokasi Fisik</div>
    <table class="info-table">
        <tr>
            <td class="label">Cabang Pengirim (Asal)</td>
            <td class="value">: <strong>{{ $transfer->fromCabang->nama ?? '-' }}</strong> ({{ $transfer->fromCabang->kode ?? '-' }})</td>
        </tr>
        <tr>
            <td class="label">Cabang Penerima (Tujuan)</td>
            <td class="value">: <strong style="color: #15803d;">{{ $transfer->toCabang->nama ?? '-' }}</strong> ({{ $transfer->toCabang->kode ?? '-' }})</td>
        </tr>
        <tr>
            <td class="label">Tanggal Rencana Mutasi</td>
            <td class="value">: {{ $transfer->transfer_date ? $transfer->transfer_date->translatedFormat('d F Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alasan / Justifikasi</td>
            <td class="value">: {{ $transfer->reason ?: '-' }}</td>
        </tr>
    </table>

    {{-- Riwayat Otorisasi --}}
    <div class="section-title">3. Riwayat Otorisasi & Penyelesaian</div>
    <table class="info-table">
        <tr>
            <td class="label">Diajukan Oleh</td>
            <td class="value">: {{ $transfer->requestedBy->name ?? 'System' }} ({{ $transfer->created_at->format('d/m/Y H:i') }})</td>
        </tr>
        <tr>
            <td class="label">Disetujui Oleh</td>
            <td class="value">: {{ $transfer->approvedBy->name ?? '-' }} {{ $transfer->approved_at ? '(' . $transfer->approved_at->format('d/m/Y H:i') . ')' : '' }}</td>
        </tr>
        <tr>
            <td class="label">Diserahterimakan Oleh</td>
            <td class="value">: {{ $transfer->completedBy->name ?? '-' }} {{ $transfer->completed_at ? '(' . $transfer->completed_at->format('d/m/Y H:i') . ')' : '' }}</td>
        </tr>
    </table>

    {{-- Lembar Pengesahan / Tanda Tangan 3 Pihak --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-role">Yang Mengajukan (Cabang Asal)</div>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $transfer->requestedBy->name ?? 'Pengirim' }}</div>
                <div class="signature-role">Penanggung Jawab Cabang Asal</div>
            </td>
            <td>
                <div class="signature-role">Menyetujui (Manajemen)</div>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $transfer->approvedBy->name ?? 'Pimpinan / Finance' }}</div>
                <div class="signature-role">Head of Accounting & Asset</div>
            </td>
            <td>
                <div class="signature-role">Yang Menerima (Cabang Tujuan)</div>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $transfer->completedBy->name ?? 'Penerima' }}</div>
                <div class="signature-role">Penanggung Jawab Cabang Penerima</div>
            </td>
        </tr>
    </table>
</body>
</html>
