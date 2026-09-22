<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Jalan {{ $doc['number'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 4px;
        }

        .logo {
            height: 56px;
        }

        .header h2 {
            margin: 2px 0 0 0;
            font-size: 16px;
        }

        .header p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #333;
        }

        .title {
            font-size: 17px;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 6px;
        }

        .banner {
            border: 2px solid #b91c1c;
            color: #b91c1c;
            text-align: center;
            font-weight: bold;
            padding: 5px;
            margin: 8px 0;
        }

        .banner.draft {
            border-color: #6b7280;
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info td {
            border: none;
            padding: 2px 4px;
            vertical-align: top;
        }

        table.info td.label {
            width: 17%;
            white-space: nowrap;
        }

        table.items {
            margin-top: 4px;
        }

        table.items th,
        table.items td {
            border: 1px solid #333;
            padding: 4px;
            text-align: left;
            vertical-align: top;
        }

        table.items th {
            background: #efefef;
        }

        .num {
            text-align: right !important;
        }

        .center {
            text-align: center !important;
        }

        .group-title {
            margin: 12px 0 2px 0;
            font-weight: bold;
            font-size: 11px;
        }

        .group-meta {
            margin: 0 0 2px 0;
            font-size: 10px;
            color: #333;
        }

        .muted {
            color: #555;
        }

        table.sign td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 0 6px;
            width: 25%;
        }

        .sign-head {
            height: 26px;
        }

        .sign-space {
            height: 62px;
        }

        .sign-line {
            border-top: 1px solid #000;
            margin: 0 8px;
            padding-top: 2px;
            font-size: 10px;
        }

        .footer {
            margin-top: 14px;
            font-size: 9px;
            color: #555;
        }
    </style>
</head>

<body>

    <div class="header">
        <img src="{{ public_path('logo_duta_tunggal.png') }}" class="logo" alt="Logo">
        <h2>{{ $doc['company']['name'] }}</h2>
        @if ($doc['company']['branch'])
            <p><strong>{{ $doc['company']['branch'] }}</strong></p>
        @endif
        @if ($doc['company']['address'] || $doc['company']['phone'] || $doc['company']['email'])
            <p>
                {{ $doc['company']['address'] }}
                @if ($doc['company']['address'] && ($doc['company']['phone'] || $doc['company']['email']))<br>@endif
                @if ($doc['company']['phone']) Telp: {{ $doc['company']['phone'] }} @endif
                @if ($doc['company']['phone'] && $doc['company']['email']) | @endif
                @if ($doc['company']['email']) Email: {{ $doc['company']['email'] }} @endif
            </p>
        @endif
        <div class="title">SURAT JALAN</div>
    </div>

    @if ($doc['is_cancelled'])
        <div class="banner">
            DIBATALKAN — dokumen ini tidak berlaku
            @if ($doc['cancelled_at'])
                <br><span style="font-weight: normal;">Dibatalkan {{ $doc['cancelled_at'] }}@if ($doc['cancelled_by']) oleh {{ $doc['cancelled_by'] }}@endif
                    @if ($doc['cancel_reason']) — {{ $doc['cancel_reason'] }} @endif</span>
            @endif
        </div>
    @elseif ($doc['is_draft'])
        <div class="banner draft">DRAFT — belum diterbitkan</div>
    @endif

    <table class="info">
        <tr>
            <td class="label">No. Surat Jalan</td>
            <td>: <strong>{{ $doc['number'] }}</strong></td>
            <td class="label">Metode Pengiriman</td>
            <td>: {{ $doc['delivery']['method_label'] }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal</td>
            <td>: {{ $doc['issued_date'] ?? '-' }}</td>
            <td class="label">{{ $doc['signatures']['driver_role'] }}</td>
            <td>: {{ $doc['delivery']['scheduled'] ? $doc['delivery']['sender_name'] : $doc['delivery']['method_label'] }}</td>
        </tr>
        <tr>
            <td class="label">No. Delivery Order</td>
            <td>: {{ implode(', ', $doc['delivery_order_numbers']) ?: '-' }}</td>
            <td class="label">Kendaraan (Plat)</td>
            <td>: {{ $doc['delivery']['scheduled'] ? $doc['delivery']['vehicle'] : $doc['delivery']['method_label'] }}</td>
        </tr>
        <tr>
            <td class="label">No. Sales Order</td>
            <td>: {{ implode(', ', $doc['sales_order_numbers']) ?: '-' }}</td>
            <td class="label">No. Resi</td>
            <td>: {{ $doc['delivery']['tracking_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Customer</td>
            <td>: {{ implode(', ', $doc['customers']) ?: '-' }}</td>
            <td class="label">No. Jadwal</td>
            <td>: {{ $doc['delivery']['schedule_number'] ?? '-' }}@if ($doc['delivery']['departure']) ({{ $doc['delivery']['departure'] }})@endif</td>
        </tr>
        <tr>
            <td class="label">Alamat Pengiriman</td>
            <td colspan="3">: {{ implode(' | ', $doc['addresses']) ?: '-' }}</td>
        </tr>
    </table>

    @forelse ($doc['groups'] as $group)
        <div class="group-title">Delivery Order: {{ $group['do_number'] }}</div>
        <div class="group-meta">
            SO: {{ implode(', ', $group['sales_orders']) ?: '-' }}
            &nbsp;|&nbsp; Customer: {{ implode(', ', $group['customers']) ?: '-' }}
            @if (count($doc['groups']) > 1 && count($group['addresses']))
                &nbsp;|&nbsp; Alamat: {{ implode(' | ', $group['addresses']) }}
            @endif
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;" class="center">No</th>
                    <th style="width: 16%;">SKU</th>
                    <th>Nama Barang</th>
                    <th style="width: 10%;" class="num">Qty</th>
                    <th style="width: 9%;">Satuan</th>
                    <th style="width: 22%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($group['items'] as $item)
                    <tr>
                        <td class="center">{{ $item['no'] }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="num">{{ $item['quantity'] }}</td>
                        <td>{{ $item['unit'] }}</td>
                        <td>{{ $item['note'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="center muted">Tidak ada barang pada Delivery Order ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p class="muted" style="margin-top: 12px;">Belum ada Delivery Order yang tercantum pada Surat Jalan ini.</p>
    @endforelse

    <p style="margin-top: 8px;">Jumlah baris barang: <strong>{{ $doc['total_lines'] }}</strong>. Barang diterima dalam keadaan baik dan jumlah sesuai daftar di atas.</p>

    <table class="sign" style="margin-top: 18px;">
        <tr>
            <td>
                <div class="sign-head">Yang Menyerahkan<br>(Gudang)</div>
                <div class="sign-space"></div>
                <div class="sign-line">Nama jelas / tanda tangan</div>
            </td>
            <td>
                <div class="sign-head">{{ $doc['signatures']['driver_role'] }}</div>
                <div class="sign-space"></div>
                <div class="sign-line">{{ $doc['signatures']['driver_name'] ?? 'Nama jelas / tanda tangan' }}</div>
            </td>
            <td>
                <div class="sign-head">Penerima<br>(Nama jelas, tanggal, cap)</div>
                <div class="sign-space"></div>
                <div class="sign-line">Tgl: ____ / ____ / ________</div>
            </td>
            <td>
                <div class="sign-head">Mengetahui<br>{{ $doc['company']['name'] }}</div>
                <div class="sign-space"></div>
                <div class="sign-line">Nama jelas / tanda tangan</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dicetak {{ $doc['printed_at'] }}@if ($doc['printed_by']) oleh {{ $doc['printed_by'] }}@endif
        &nbsp;|&nbsp; Status: {{ $doc['status_label'] }}
    </div>

</body>

</html>
