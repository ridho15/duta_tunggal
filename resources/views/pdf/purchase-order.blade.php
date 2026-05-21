<!DOCTYPE html>
<html lang="en">

<head>
    @php
        $purchaseOrder->loadMissing(['purchaseOrderItem.currency', 'purchaseOrderCurrency.currency']);

        $headerCurrencyId = $purchaseOrder->purchaseOrderCurrency->first()?->currency_id
            ?? $purchaseOrder->purchaseOrderItem->first()?->currency_id;
        $isImport = (bool) ($purchaseOrder->is_import ?? false);

        $formatMoney = function (float $amount, ?int $currencyId) {
            $currency = $currencyId ? \App\Models\Currency::find($currencyId) : null;
            $symbol = $currency?->symbol ?: \App\Support\CurrencyConversionResolver::resolveSymbol($currencyId);
            $decimals = strtoupper((string) ($currency?->code ?? '')) === 'IDR' ? 0 : 2;

            return $symbol . ' ' . number_format($amount, $decimals, $decimals === 0 ? ',' : '.', $decimals === 0 ? '.' : ',');
        };

        // TOP / credit term is the payment term; expected date remains the logistics date.
        $topType = strtolower(trim((string) ($purchaseOrder->top_type ?? '')));
        if ($topType === '') {
            $topType = ((int) ($purchaseOrder->tempo_hutang ?? $purchaseOrder->supplier->tempo_hutang ?? 0)) > 0 ? 'credit_days' : 'cod';
        }
        $tempoHutang = (int) ($purchaseOrder->tempo_hutang ?? $purchaseOrder->supplier->tempo_hutang ?? 0);
        $topLabel = match ($topType) {
            'advance_before_delivery' => 'Advance Before Delivery',
            'deposit_balance' => 'Deposit + Balance',
            'credit_days' => 'Credit ' . ($tempoHutang > 0 ? $tempoHutang . ' hari' : '... Days'),
            default => 'COD',
        };
        $jatuhTempoDate = $topType === 'credit_days' && $tempoHutang > 0
            ? \Carbon\Carbon::parse($purchaseOrder->order_date)->addDays($tempoHutang)->format('d/m/Y')
            : '-';
    @endphp
    <style>
        .header {
            text-align: center;
        }

        .company {
            text-align: left;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
            text-align: center;
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #000;
            text-align: left;
        }

        .no-border {
            border: none !important;
        }

        .right {
            text-align: right;
        }

        .signature {
            margin-top: 50px;
            text-align: center;
        }

        .signature .box {
            width: 200px;
            display: inline-block;
            margin: 0 40px;
        }

        .logo {
            width: 120px;
        }
    </style>
</head>

<body>

    <table class="no-border">
        <tr class="no-border">
            <td class="no-border" style="width: 70%">
                <strong>PT DUTA TUNGGAL</strong><br>
                Jl. Contoh No. 123<br>
                Jakarta, Indonesia<br>
                Telp: (021) 12345678<br>
                Email: admin@dutatunggal.co.id
            </td>
            <td class="no-border right">
                <img src="{{ public_path('logo_duta_tunggal.png') }}" class="logo">
            </td>
        </tr>
    </table>

    <div class="title">PEMBELIAN</div>

    @if($isImport)
        <div style="margin-top: 10px; font-style: italic; color: #555;">
            Transaksi impor diringkas pada PO. Breakdown fiskal lengkap ditampilkan pada Purchase Invoice.
        </div>
    @endif

    <table>
        <tr>
            <td><strong>No. PO:</strong></td>
            <td>{{ $purchaseOrder->po_number }}</td>
            <td><strong>Tanggal:</strong></td>
            <td>{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Supplier:</strong></td>
            <td>{{ $purchaseOrder->supplier->perusahaan }}<br>{{ $purchaseOrder->supplier->address }}</td>
            <td><strong>Tanggal Diharapkan:</strong></td>
            <td>{{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td><strong>TOP:</strong></td>
            <td>{{ $topLabel }}</td>
            <td><strong>Jatuh Tempo:</strong></td>
            <td>{{ $jatuhTempoDate }}</td>
        </tr>
        <tr>
            <td><strong>Tipe:</strong></td>
            <td>{{ $purchaseOrder->is_asset ? 'Asset' : 'Non Asset' }}</td>
            <td><strong>Cabang:</strong></td>
            <td>{{ $purchaseOrder->cabang->nama ?? '-' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Harga Satuan</th>
                <th>Diskon (%)</th>
                <th>Diskon (Rp)</th>
                @unless($isImport)
                    <th>Tipe Pajak</th>
                    <th>Tax (%)</th>
                    <th>DPP</th>
                    <th>PPN</th>
                @endunless
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotal        = 0;
                $totalBruto        = 0;
                $totalDiskon       = 0;
                $totalAfterDiskon  = 0;
                $totalDPP          = 0;
                $totalPPN          = 0;
            @endphp
            @foreach ($purchaseOrder->purchaseOrderItem as $index => $item)
            @php
                $quantity       = (float) $item->quantity;
                $unitPrice      = (float) $item->unit_price;
                $discount       = (float) $item->discount;   // stored as %
                $taxRate        = (float) $item->tax;        // stored as %
                $taxType        = \App\Services\TaxService::normalizeType($item->tipe_pajak);
                $lineCurrencyId = $item->currency_id ?? $headerCurrencyId;

                $bruto          = $quantity * $unitPrice;
                $discountAmount = $bruto * ($discount / 100.0);
                $afterDiscount  = $bruto - $discountAmount;

                $taxResult  = \App\Services\TaxService::compute($afterDiscount, $taxRate, $taxType);
                $itemDPP    = $taxResult['dpp'];
                $itemPPN    = $taxResult['ppn'];
                $itemTotal  = $taxResult['total'];

                $summaryBruto = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($bruto, $lineCurrencyId, $headerCurrencyId)
                    : $bruto;
                $summaryDiskon = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($discountAmount, $lineCurrencyId, $headerCurrencyId)
                    : $discountAmount;
                $summaryAfterDiskon = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($afterDiscount, $lineCurrencyId, $headerCurrencyId)
                    : $afterDiscount;
                $summaryDpp = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($itemDPP, $lineCurrencyId, $headerCurrencyId)
                    : $itemDPP;
                $summaryPpn = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($itemPPN, $lineCurrencyId, $headerCurrencyId)
                    : $itemPPN;
                $summaryTotal = $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                    ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($itemTotal, $lineCurrencyId, $headerCurrencyId)
                    : $itemTotal;

                $totalBruto       += $bruto;
                $totalDiskon      += $discountAmount;
                $totalAfterDiskon += $afterDiscount;
                $totalDPP         += $itemDPP;
                $totalPPN         += $itemPPN;
                $grandTotal       += $itemTotal;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td class="right">{{ number_format($quantity, 0, ',', '.') }}</td>
                <td>{{ $item->product->uom->name ?? '-' }}</td>
                <td class="right">{{ $formatMoney($unitPrice, $lineCurrencyId) }}</td>
                <td class="right">{{ number_format($discount, 2, ',', '.') }}%</td>
                <td class="right">{{ $formatMoney($discountAmount, $lineCurrencyId) }}</td>
                @unless($isImport)
                    <td>{{ $taxType }}</td>
                    <td class="right">{{ number_format($taxRate, 2, ',', '.') }}%</td>
                    <td class="right">{{ $formatMoney($itemDPP, $lineCurrencyId) }}</td>
                    <td class="right">{{ $formatMoney($itemPPN, $lineCurrencyId) }}</td>
                @endunless
                <td class="right">{{ $formatMoney($itemTotal, $lineCurrencyId) }}</td>
            </tr>
            @endforeach

            {{-- Summary Breakdown --}}
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 10 }}" class="right">Subtotal Bruto</td>
                <td colspan="{{ $isImport ? 1 : 2 }}" class="right">{{ $formatMoney($totalBruto, $headerCurrencyId) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 10 }}" class="right">Total Diskon</td>
                <td colspan="{{ $isImport ? 1 : 2 }}" class="right">({{ $formatMoney($totalDiskon, $headerCurrencyId) }})</td>
            </tr>
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 10 }}" class="right">Sub Total (setelah diskon)</td>
                <td colspan="{{ $isImport ? 1 : 2 }}" class="right">{{ $formatMoney($totalAfterDiskon, $headerCurrencyId) }}</td>
            </tr>
            @unless($isImport)
                <tr class="summary-row">
                    <td colspan="10" class="right">DPP (Dasar Pengenaan Pajak)</td>
                    <td colspan="2" class="right">{{ $formatMoney($totalDPP, $headerCurrencyId) }}</td>
                </tr>
                <tr class="summary-row">
                    <td colspan="10" class="right">PPN</td>
                    <td colspan="2" class="right">{{ $formatMoney($totalPPN, $headerCurrencyId) }}</td>
                </tr>
            @endunless
            <tr style="background-color: #f0f0f0;">
                <td colspan="{{ $isImport ? 7 : 10 }}" class="right"><strong>TOTAL</strong></td>
                <td colspan="{{ $isImport ? 1 : 2 }}" class="right"><strong>{{ $formatMoney($grandTotal, $headerCurrencyId) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if($purchaseOrder->is_asset)
    <div style="margin-top: 20px;">
        <h3 style="text-decoration: underline; margin-bottom: 10px;">Informasi Asset</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Asset</th>
                    <th>Nilai Perolehan</th>
                    <th>Umur Manfaat (Tahun)</th>
                    <th>Nilai Sisa</th>
                    <th>COA Aset</th>
                    <th>COA Akumulasi</th>
                    <th>COA Beban</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->assets as $index => $asset)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $asset->name }}</td>
                    <td class="right">Rp.{{ number_format($asset->purchase_cost, 0, ',', '.') }}</td>
                    <td class="right">{{ $asset->useful_life_years }}</td>
                    <td class="right">Rp.{{ number_format($asset->salvage_value, 0, ',', '.') }}</td>
                    <td>({{ $asset->assetCoa->code }}) {{ $asset->assetCoa->name }}</td>
                    <td>({{ $asset->accumulatedDepreciationCoa->code }}) {{ $asset->accumulatedDepreciationCoa->name }}</td>
                    <td>({{ $asset->depreciationExpenseCoa->code }}) {{ $asset->depreciationExpenseCoa->name }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="signature">
        <div class="box">
            <p>Disetujui Oleh,</p>
            @if ($purchaseOrder->approvedBy->signature)
            <img src="{{ public_path('storage' . $purchaseOrder->approvedBy->signature) }}" alt="" style="height: 75px">
            @else
            <br><br><br>
            @endif
            <p><strong>{{ $purchaseOrder->approved_by->name ?? 'Owner' }}</strong></p>
        </div>
        <div class="box">
            <p>Dibuat Oleh,</p>
            @if ($purchaseOrder->createdBy->signature)
            <img src="{{ public_path('storage' . $purchaseOrder->createdBy->signature) }}" style="height: 75px" alt="">
            @else
            <br><br><br>
            @endif
            <p><strong>{{ $purchaseOrder->created_by->name ?? 'Staff Purchasing' }}</strong></p>
        </div>
    </div>

</body>

</html>