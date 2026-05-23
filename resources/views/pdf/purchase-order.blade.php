<!DOCTYPE html>
<html lang="en">

<head>
    @php
        $purchaseOrder->loadMissing(['purchaseOrderItem.currency', 'purchaseOrderCurrency.currency']);

        $headerCurrencyId =
            $purchaseOrder->purchaseOrderCurrency->first()?->currency_id ??
            $purchaseOrder->purchaseOrderItem->first()?->currency_id;
        $isImport = (bool) ($purchaseOrder->is_import ?? false);

        $formatMoney = function (float $amount, ?int $currencyId) {
            $currency = $currencyId ? \App\Models\Currency::find($currencyId) : null;
            $symbol = $currency?->symbol ?: \App\Support\CurrencyConversionResolver::resolveSymbol($currencyId);
            $decimals = strtoupper((string) ($currency?->code ?? '')) === 'IDR' ? 0 : 2;

            return $symbol .
                ' ' .
                number_format($amount, $decimals, $decimals === 0 ? ',' : '.', $decimals === 0 ? '.' : ',');
        };

        // TOP / credit term is the payment term; expected date remains the logistics date.
        $topType = strtolower(trim((string) ($purchaseOrder->top_type ?? '')));
        if ($topType === '') {
            $topType =
                ((int) ($purchaseOrder->tempo_hutang ?? ($purchaseOrder->supplier->tempo_hutang ?? 0))) > 0
                    ? 'credit_days'
                    : 'cod';
        }
        $tempoHutang = (int) ($purchaseOrder->tempo_hutang ?? ($purchaseOrder->supplier->tempo_hutang ?? 0));
        $topLabel = match ($topType) {
            'advance_before_delivery' => 'Advance Before Delivery',
            'deposit_balance' => 'Deposit + Balance',
            'credit_days' => 'Credit ' . ($tempoHutang > 0 ? $tempoHutang . ' hari' : '... Days'),
            default => 'COD',
        };
        $jatuhTempoDate =
            $topType === 'credit_days' && $tempoHutang > 0
                ? \Carbon\Carbon::parse($purchaseOrder->order_date)->addDays($tempoHutang)->format('d/m/Y')
                : '-';
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 20mm;
        }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border: none;
            margin-bottom: 5px;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #222;
        }

        .company-info {
            font-size: 11px;
            color: #555;
        }

        .logo {
            height: 50px;
            object-fit: contain;
        }

        .title-container {
            text-align: center;
            margin: 15px 0;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: inline-block;
            border-bottom: 2px solid #222;
            padding-bottom: 3px;
        }

        .info-table {
            width: 100%;
            border: none;
            margin-bottom: 15px;
        }

        .info-table td {
            border: none;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-table td.label {
            font-weight: bold;
            width: 130px;
            color: #555;
            white-space: nowrap;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        .items-table th {
            background-color: #f4f6f8;
            font-weight: bold;
            color: #444;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .right {
            text-align: right !important;
        }

        .center {
            text-align: center !important;
        }

        .summary-row td {
            border-top: none;
            border-bottom: none;
            padding: 5px 8px;
            font-size: 11px;
        }

        .summary-row.total td {
            border-top: 1px solid #222;
            border-bottom: 2px solid #222;
            background-color: #f4f6f8;
            font-size: 12px;
            font-weight: bold;
            color: #111;
        }

        .asset-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .asset-table th,
        .asset-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        
        .asset-table th {
            background-color: #f4f6f8;
        }

        .signature-section {
            margin-top: 40px;
            width: 100%;
        }

        .signature-table {
            width: 100%;
            border: none;
        }

        .signature-table td {
            border: none;
            text-align: center;
            width: 50%;
            vertical-align: bottom;
            padding: 0;
        }

        .signature-box {
            display: inline-block;
            width: 250px;
        }

        .signature-img {
            height: 70px;
            margin: 10px 0;
            object-fit: contain;
        }
        
        .signature-name {
            font-weight: bold;
            border-top: 1px solid #888;
            padding-top: 5px;
            width: 80%;
            margin: 0 auto;
        }
    </style>
</head>

<body>

    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-info">
                    Jl. Contoh No. 123<br>
                    Jakarta, Indonesia<br>
                    Telp: (021) 12345678<br>
                    Email: admin@dutatunggal.co.id
                </div>
            </td>
            <td class="right">
                <img src="{{ public_path('logo_duta_tunggal.png') }}" class="logo" alt="Logo">
            </td>
        </tr>
    </table>

    <div class="title-container">
        <div class="title">PURCHASE ORDER</div>
    </div>

    @if ($isImport)
        <div style="margin-bottom: 10px; font-style: italic; color: #666; font-size: 10px;">
            * Transaksi impor diringkas pada PO. Breakdown fiskal lengkap ditampilkan pada dokumen Purchase Invoice.
        </div>
    @endif

    <table class="info-table">
        <tr>
            <td class="label">No. PO</td>
            <td>: <strong>{{ $purchaseOrder->po_number }}</strong></td>
            <td class="label">Tanggal PO</td>
            <td>: {{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Supplier</td>
            <td>: <strong>{{ $purchaseOrder->supplier->perusahaan }}</strong><br>
                <span style="padding-left: 8px;">{{ $purchaseOrder->supplier->address }}</span>
            </td>
            <td class="label">Tanggal Diharapkan</td>
            <td>: {{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Term of Payment</td>
            <td>: {{ $topLabel }}</td>
            <td class="label">Cabang</td>
            <td>: {{ $purchaseOrder->cabang->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tipe Order</td>
            <td>: {{ $purchaseOrder->is_asset ? 'Asset' : 'Non Asset' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="center" style="width: 4%;">No</th>
                <th>Nama Barang</th>
                <th class="center" style="width: 6%;">Qty</th>
                <th class="center" style="width: 7%;">Satuan</th>
                <th class="right" style="width: 11%;">Harga Satuan</th>
                <th class="right" style="width: 8%;">Disc (%)</th>
                <th class="right" style="width: 10%;">Disc (Rp)</th>
                @unless ($isImport)
                    <th class="center" style="width: 9%;">Tipe Pajak</th>
                    <th class="right" style="width: 7%;">Tax (%)</th>
                    <th class="right" style="width: 11%;">DPP</th>
                    <th class="right" style="width: 9%;">PPN</th>
                @endunless
                <th class="right" style="width: 12%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotal = 0;
                $totalBruto = 0;
                $totalDiskon = 0;
                $totalAfterDiskon = 0;
                $totalDPP = 0;
                $totalPPN = 0;
            @endphp
            @foreach ($purchaseOrder->purchaseOrderItem as $index => $item)
                @php
                    $quantity = (float) $item->quantity;
                    $unitPrice = (float) $item->unit_price;
                    $discount = (float) $item->discount; // stored as %
                    $taxRate = (float) $item->tax; // stored as %
                    $taxType = \App\Services\TaxService::normalizeType($item->tipe_pajak);
                    $lineCurrencyId = $item->currency_id ?? $headerCurrencyId;

                    $bruto = $quantity * $unitPrice;
                    $discountAmount = $bruto * ($discount / 100.0);
                    $afterDiscount = $bruto - $discountAmount;

                    $taxResult = \App\Services\TaxService::compute($afterDiscount, $taxRate, $taxType);
                    $itemDPP = $taxResult['dpp'];
                    $itemPPN = $taxResult['ppn'];
                    $itemTotal = $taxResult['total'];

                    $summaryBruto =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $bruto,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $bruto;
                    $summaryDiskon =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $discountAmount,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $discountAmount;
                    $summaryAfterDiskon =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $afterDiscount,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $afterDiscount;
                    $summaryDpp =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $itemDPP,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $itemDPP;
                    $summaryPpn =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $itemPPN,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $itemPPN;
                    $summaryTotal =
                        $headerCurrencyId && $lineCurrencyId !== $headerCurrencyId
                            ? (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies(
                                $itemTotal,
                                $lineCurrencyId,
                                $headerCurrencyId,
                            )
                            : $itemTotal;

                    $totalBruto += $bruto;
                    $totalDiskon += $discountAmount;
                    $totalAfterDiskon += $afterDiscount;
                    $totalDPP += $itemDPP;
                    $totalPPN += $itemPPN;
                    $grandTotal += $itemTotal;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                    <td class="center">{{ number_format($quantity, 0, ',', '.') }}</td>
                    <td class="center">{{ $item->product->uom->name ?? '-' }}</td>
                    <td class="right">{{ $formatMoney($unitPrice, $lineCurrencyId) }}</td>
                    <td class="right">{{ number_format($discount, 2, ',', '.') }}%</td>
                    <td class="right">{{ $formatMoney($discountAmount, $lineCurrencyId) }}</td>
                    @unless ($isImport)
                        <td class="center">{{ $taxType }}</td>
                        <td class="right">{{ number_format($taxRate, 2, ',', '.') }}%</td>
                        <td class="right">{{ $formatMoney($itemDPP, $lineCurrencyId) }}</td>
                        <td class="right">{{ $formatMoney($itemPPN, $lineCurrencyId) }}</td>
                    @endunless
                    <td class="right">{{ $formatMoney($itemTotal, $lineCurrencyId) }}</td>
                </tr>
            @endforeach

            {{-- Summary Breakdown --}}
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 11 }}" class="right">Subtotal Bruto</td>
                <td class="right">{{ $formatMoney($totalBruto, $headerCurrencyId) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 11 }}" class="right">Total Diskon</td>
                <td class="right">({{ $formatMoney($totalDiskon, $headerCurrencyId) }})</td>
            </tr>
            <tr class="summary-row">
                <td colspan="{{ $isImport ? 7 : 11 }}" class="right">Sub Total (setelah diskon)</td>
                <td class="right">{{ $formatMoney($totalAfterDiskon, $headerCurrencyId) }}</td>
            </tr>
            @unless ($isImport)
                <tr class="summary-row">
                    <td colspan="11" class="right">DPP (Dasar Pengenaan Pajak)</td>
                    <td class="right">{{ $formatMoney($totalDPP, $headerCurrencyId) }}</td>
                </tr>
                <tr class="summary-row">
                    <td colspan="11" class="right">PPN</td>
                    <td class="right">{{ $formatMoney($totalPPN, $headerCurrencyId) }}</td>
                </tr>
            @endunless
            <tr class="summary-row total">
                <td colspan="{{ $isImport ? 7 : 11 }}" class="right">GRAND TOTAL</td>
                <td class="right">{{ $formatMoney($grandTotal, $headerCurrencyId) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($purchaseOrder->is_asset)
        <div style="margin-top: 25px;">
            <h4 style="margin-bottom: 8px; color: #444;">INFORMASI ASET</h4>
            <table class="asset-table">
                <thead>
                    <tr>
                        <th class="center" style="width: 5%;">No</th>
                        <th>Nama Aset</th>
                        <th class="right">Nilai Perolehan</th>
                        <th class="center">Umur Manfaat (Thn)</th>
                        <th class="right">Nilai Sisa</th>
                        <th>COA Aset</th>
                        <th>COA Akumulasi Depresiasi</th>
                        <th>COA Beban Depresiasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->assets as $index => $asset)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $asset->name }}</td>
                            <td class="right">Rp.{{ number_format($asset->purchase_cost, 0, ',', '.') }}</td>
                            <td class="center">{{ $asset->useful_life_years }}</td>
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

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">Dibuat Oleh,</p>
                        @if ($purchaseOrder->createdBy->signature)
                            <img src="{{ public_path('storage' . $purchaseOrder->createdBy->signature) }}" class="signature-img" alt="Signature">
                        @else
                            <div style="height: 70px; margin: 10px 0;"></div>
                        @endif
                        <div class="signature-name">
                            {{ $purchaseOrder->created_by->name ?? 'Staff Purchasing' }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">Disetujui Oleh,</p>
                        @if ($purchaseOrder->approvedBy->signature)
                            <img src="{{ public_path('storage' . $purchaseOrder->approvedBy->signature) }}" class="signature-img" alt="Signature">
                        @else
                            <div style="height: 70px; margin: 10px 0;"></div>
                        @endif
                        <div class="signature-name">
                            {{ $purchaseOrder->approved_by->name ?? 'Direktur' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>
