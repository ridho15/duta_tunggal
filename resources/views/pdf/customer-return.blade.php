<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Return {{ $return->return_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .company-info { float: left; width: 55%; }
        .doc-title    { float: right; width: 45%; text-align: right; }
        .doc-title h1 { color: #c0392b; font-size: 28px; margin: 0 0 6px; }
        .doc-title .doc-number { font-size: 14px; font-weight: bold; }

        .clearfix::after { content: ""; display: table; clear: both; }

        .meta-section {
            clear: both;
            margin: 20px 0;
        }

        .meta-left  { float: left;  width: 48%; }
        .meta-right { float: right; width: 48%; }

        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 6px; vertical-align: top; }
        .meta-table td:first-child { width: 40%; font-weight: bold; color: #555; }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: #fff;
        }
        .status-pending       { background: #e67e22; }
        .status-received      { background: #2980b9; }
        .status-qc_inspection { background: #8e44ad; }
        .status-approved      { background: #27ae60; }
        .status-rejected      { background: #c0392b; }
        .status-completed     { background: #27ae60; }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 6px 10px;
            border-left: 4px solid #c0392b;
            margin: 24px 0 10px;
            clear: both;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            clear: both;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 7px 8px;
            font-size: 11px;
        }

        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }

        .items-table td.center { text-align: center; }
        .items-table td.right  { text-align: right; }

        .decision-repair  { color: #e67e22; font-weight: bold; }
        .decision-replace { color: #2980b9; font-weight: bold; }
        .decision-reject  { color: #c0392b; font-weight: bold; }
        .qc-pass { color: #27ae60; font-weight: bold; }
        .qc-fail { color: #c0392b; font-weight: bold; }

        .signature-section {
            margin-top: 40px;
            clear: both;
        }

        .sig-box {
            float: left;
            width: 30%;
            text-align: center;
            margin-right: 5%;
        }

        .sig-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 6px;
            font-size: 11px;
        }

        .footer {
            clear: both;
            margin-top: 40px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
            color: #888;
        }

        .reason-box {
            background: #fef9e7;
            border: 1px solid #f9e79f;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 6px 0;
        }
    </style>
</head>
<body>

    {{-- ── Header ───────────────────────────────────────────────── --}}
    <div class="header clearfix">
        <div class="company-info">
            <h2 style="margin:0 0 4px;">PT. DUTA TUNGGAL</h2>
            <p style="margin:0; line-height:1.6; color:#555;">
                Distributor &amp; Manufacturer<br>
                Telp: (021) xxx-xxxx
            </p>
        </div>
        <div class="doc-title">
            <h1>CUSTOMER RETURN</h1>
            <div class="doc-number">{{ $return->return_number }}</div>
            <div style="margin-top:6px;">
                @php
                    $statusClass = 'status-' . $return->status;
                    $statusLabels = [
                        'pending'       => 'Menunggu',
                        'received'      => 'Diterima',
                        'qc_inspection' => 'Inspeksi QC',
                        'approved'      => 'Disetujui',
                        'rejected'      => 'Ditolak',
                        'completed'     => 'Selesai',
                    ];
                @endphp
                <span class="status-badge {{ $statusClass }}">
                    {{ $statusLabels[$return->status] ?? $return->status }}
                </span>
            </div>
        </div>
    </div>

    {{-- ── Return & Customer Info ───────────────────────────────── --}}
    <div class="meta-section clearfix">
        <div class="meta-left">
            <p style="margin:0 0 4px;font-weight:bold;font-size:12px;">Informasi Return</p>
            <table class="meta-table">
                <tr>
                    <td>No. Return</td>
                    <td>: {{ $return->return_number }}</td>
                </tr>
                <tr>
                    <td>Tanggal Return</td>
                    <td>: {{ \Carbon\Carbon::parse($return->return_date)->locale('id')->isoFormat('D MMMM Y') }}</td>
                </tr>
                <tr>
                    <td>No. Invoice</td>
                    <td>: {{ $return->invoice->invoice_number ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Tanggal Invoice</td>
                    <td>: {{ optional($return->invoice->invoice_date)->locale('id')->isoFormat('D MMMM Y') ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Cabang</td>
                    <td>: {{ $return->cabang->nama ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="meta-right">
            <p style="margin:0 0 4px;font-weight:bold;font-size:12px;">Data Pelanggan</p>
            <table class="meta-table">
                <tr>
                    <td>Pelanggan</td>
                    <td>: {{ $return->customer->name ?? $return->invoice->customer_name ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Perusahaan</td>
                    <td>: {{ $return->customer->perusahaan ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Alamat</td>
                    <td>: {{ $return->customer->address ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Telepon</td>
                    <td>: {{ $return->customer->phone ?? $return->invoice->customer_phone ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ── Reason ───────────────────────────────────────────────── --}}
    <div class="section-title">Alasan Return</div>
    <div class="reason-box">{{ $return->reason }}</div>
    @if($return->notes)
        <div style="margin-top:6px;"><strong>Catatan:</strong> {{ $return->notes }}</div>
    @endif

    {{-- ── Returned Items ───────────────────────────────────────── --}}
    <div class="section-title">Daftar Produk yang Dikembalikan</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:22%">Produk</th>
                <th style="width:8%" class="center">SKU</th>
                <th style="width:7%" class="center">Qty</th>
                <th style="width:25%">Deskripsi Masalah</th>
                <th style="width:10%" class="center">Hasil QC</th>
                <th style="width:15%">Catatan QC</th>
                <th style="width:10%" class="center">Keputusan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($return->customerReturnItems as $index => $item)
            @php
                $decisionLabels = [
                    'repair'  => 'Perbaikan',
                    'replace' => 'Penggantian',
                    'reject'  => 'Ditolak',
                ];
                $decisionClass = $item->decision ? 'decision-' . $item->decision : '';
                $qcClass = $item->qc_result === 'pass' ? 'qc-pass' : ($item->qc_result === 'fail' ? 'qc-fail' : '');
                $qcLabel = $item->qc_result === 'pass' ? 'Lolos' : ($item->qc_result === 'fail' ? 'Gagal' : '-');
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item->product->name ?? '-' }}</td>
                <td class="center">{{ $item->product->sku ?? '-' }}</td>
                <td class="center">{{ $item->quantity }}</td>
                <td>{{ $item->problem_description ?? '-' }}</td>
                <td class="center {{ $qcClass }}">{{ $qcLabel }}</td>
                <td>{{ $item->qc_notes ?? '-' }}</td>
                <td class="center {{ $decisionClass }}">
                    {{ $decisionLabels[$item->decision] ?? '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="center" style="padding:12px;color:#888;">Tidak ada item</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Process Log ──────────────────────────────────────────── --}}
    @if($return->received_at || $return->qc_inspected_at || $return->approved_at || $return->rejected_at)
    <div class="section-title">Log Proses</div>
    <table class="meta-table" style="width:60%;">
        @if($return->received_at)
        <tr>
            <td>Diterima Oleh</td>
            <td>: {{ $return->receivedBy->name ?? '-' }}</td>
            <td style="padding-left:12px;">{{ \Carbon\Carbon::parse($return->received_at)->locale('id')->isoFormat('D MMM Y HH:mm') }}</td>
        </tr>
        @endif
        @if($return->qc_inspected_at)
        <tr>
            <td>Diinspeksi Oleh</td>
            <td>: {{ $return->qcInspectedBy->name ?? '-' }}</td>
            <td style="padding-left:12px;">{{ \Carbon\Carbon::parse($return->qc_inspected_at)->locale('id')->isoFormat('D MMM Y HH:mm') }}</td>
        </tr>
        @endif
        @if($return->approved_at)
        <tr>
            <td>Disetujui Oleh</td>
            <td>: {{ $return->approvedBy->name ?? '-' }}</td>
            <td style="padding-left:12px;">{{ \Carbon\Carbon::parse($return->approved_at)->locale('id')->isoFormat('D MMM Y HH:mm') }}</td>
        </tr>
        @endif
        @if($return->rejected_at)
        <tr>
            <td>Ditolak Oleh</td>
            <td>: {{ $return->rejectedBy->name ?? '-' }}</td>
            <td style="padding-left:12px;">{{ \Carbon\Carbon::parse($return->rejected_at)->locale('id')->isoFormat('D MMM Y HH:mm') }}</td>
        </tr>
        @endif
    </table>
    @endif

    {{-- ── Signatures ───────────────────────────────────────────── --}}
    <div class="signature-section clearfix" style="margin-top:50px;">
        <div class="sig-box">
            <div class="sig-line">Dibuat Oleh</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">QC Inspector</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Disetujui Oleh</div>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem ERP PT. Duta Tunggal &bull;
           Dicetak pada: {{ now()->locale('id')->isoFormat('D MMMM Y, HH:mm') }}
        </p>
    </div>

</body>
</html>
