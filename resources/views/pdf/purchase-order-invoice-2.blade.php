<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            float: left;
            width: 50%;
        }

        .invoice-title {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-title h1 {
            color: #333;
            font-size: 32px;
            margin: 0;
        }

        .invoice-details {
            clear: both;
            margin: 30px 0;
        }

        .customer-info {
            float: left;
            width: 50%;
        }

        .invoice-info {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            clear: both;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            float: right;
            width: 40%;
            margin-top: 20px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .totals-table .total-row {
            font-weight: bold;
            border-top: 2px solid #333;
            background-color: #f5f5f5;
        }

        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .rupiah {
            font-family: monospace;
        }
    </style>
</head>

<body>
    <div class="header clearfix">
        <div class="company-info">
            <h2>PT.DUTA TUNGGAL</h2>
            <p>Jl. Raya Bogor KM 36, Cibinong, Bogor<br>
                Cibinong, Jawa Barat 16911<br>
                Telp: (021) 875-1234<br>
                Email: info@dutatunggal.com</p>
            @if($invoice->cabang)
            <p style="margin-top: 10px; font-size: 11px; color: #666;">
                <strong>Cabang: {{ $invoice->cabang->nama }}</strong><br>
                {{ $invoice->cabang->alamat ?? '' }}
            </p>
            @endif
        </div>
        <div class="invoice-title">
            <h1>INVOICE PEMBELIAN</h1>
            <p><strong>No. Invoice:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->format('d M Y') }}</p>
        </div>
    </div>

    <div class="invoice-details clearfix">
        <div class="customer-info">
            <h3>Supplier:</h3>
            <p><strong>{{ $invoice->supplier_name ?? $invoice->fromModel->supplier->perusahaan }}</strong><br>
                @if($invoice->fromModel->supplier->perusahaan)
                {{ $invoice->fromModel->supplier->perusahaan }}<br>
                @endif
                {{ $invoice->fromModel->supplier->address }}<br>
                @if($invoice->fromModel->supplier->phone)
                Telp: {{ $invoice->fromModel->supplier->phone }}<br>
                @endif
                @if($invoice->fromModel->supplier->email)
                Email: {{ $invoice->fromModel->supplier->email }}
                @endif
            </p>
        </div>
        <div class="invoice-info">
            <table>
                <tr>
                    <td><strong>No. PO:</strong></td>
                    <td>{{ $invoice->fromModel->po_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Tanggal Invoice:</strong></td>
                    <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Jatuh Tempo:</strong></td>
                    <td>{{ \Carbon\Carbon::parse($invoice->due_date)->locale('id')->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>{{ ucfirst($invoice->status) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th class="text-left">SKU</th>
                <th class="text-left">Produk</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItem as $item)
            <tr>
                <td class="text-left">{{ $item->product->sku ?? 'N/A' }}</td>
                <td class="text-left">{{ $item->product->name ?? 'N/A' }}</td>
                <td class="text-center">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                <td class="text-right rupiah">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right rupiah">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Purchase Receipts Information --}}
    @if($invoice->purchase_receipts && is_array($invoice->purchase_receipts) && count($invoice->purchase_receipts) > 0)
    <div style="margin: 20px 0; clear: both;">
        <h4 style="margin-bottom: 10px; color: #333;">Purchase Receipts Terkait:</h4>
        <div style="font-size: 11px; color: #666;">
            @php
                $receipts = \App\Models\PurchaseReceipt::whereIn('id', $invoice->purchase_receipts)->get();
            @endphp
            @foreach($receipts as $receipt)
            <div style="margin-bottom: 5px;">
                • {{ $receipt->receipt_number }} - {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right rupiah">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            
            {{-- Biaya lain dari invoice --}}
            @if($invoice->other_fee && is_array($invoice->other_fee))
                @foreach($invoice->other_fee as $fee)
                <tr>
                    <td>{{ $fee['name'] ?? 'Biaya Lain' }}:</td>
                    <td class="text-right rupiah">Rp {{ number_format($fee['amount'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @endif
            
            {{-- Biaya dari Purchase Receipts --}}
            @if($invoice->purchase_receipts && is_array($invoice->purchase_receipts))
                @php
                    $receiptBiayas = \App\Models\PurchaseReceiptBiaya::whereHas('purchaseReceipt', function($query) use ($invoice) {
                        $query->whereIn('id', $invoice->purchase_receipts);
                    })->get();
                @endphp
                @foreach($receiptBiayas as $biaya)
                <tr>
                    <td>{{ $biaya->nama_biaya }}:</td>
                    <td class="text-right rupiah">Rp {{ number_format($biaya->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @endif
            
            {{-- PPN --}}
            @if($invoice->ppn_rate > 0)
            <tr>
                <td>DPP:</td>
                <td class="text-right rupiah">Rp {{ number_format($invoice->dpp ?? $invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>PPN {{ $invoice->ppn_rate }}%:</td>
                <td class="text-right rupiah">Rp {{ number_format($invoice->ppn_amount ?? (($invoice->dpp ?? $invoice->subtotal) * $invoice->ppn_rate / 100), 0, ',', '.') }}</td>
            </tr>
            @endif
            
            {{-- Tax tambahan jika ada --}}
            @if($invoice->tax > 0)
            <tr>
                <td>Tax ({{ $invoice->tax }}%):</td>
                <td class="text-right rupiah">Rp {{ number_format($invoice->tax_amount ?? ($invoice->subtotal * $invoice->tax / 100), 0, ',', '.') }}</td>
            </tr>
            @endif
            
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="text-right rupiah"><strong>Rp {{ number_format($invoice->total, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div style="text-align: left; margin-bottom: 20px;">
            <p><strong>Catatan:</strong></p>
            <p style="font-size: 11px; line-height: 1.5;">
                • Invoice ini dibuat berdasarkan Purchase Order No: {{ $invoice->fromModel->po_number ?? 'N/A' }}<br>
                • Pembayaran mohon ditransfer ke rekening:<br>
                  &nbsp;&nbsp;&nbsp;BCA: 123-456-7890 a/n PT. DUTA TUNGGAL<br>
                  &nbsp;&nbsp;&nbsp;BRI: 098-765-4321 a/n PT. DUTA TUNGGAL<br>
                • Pembayaran dianggap sah setelah diterima konfirmasi dari pihak kami
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <p><strong>Terima kasih atas kepercayaan Anda berbisnis dengan kami!</strong></p>
            <p style="margin-top: 20px;">
                Hormat kami,<br>
                <strong>PT. DUTA TUNGGAL</strong>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 40px; font-size: 10px; color: #666;">
            <p>Invoice ini dicetak otomatis dari sistem pada {{ \Carbon\Carbon::now()->format('d M Y H:i:s') }}</p>
        </div>
    </div>
</body>

</html>