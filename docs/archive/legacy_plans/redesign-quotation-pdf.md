# Plan: Redesign PDF Quotation with Consistent Style

## Context
The PDF quotation template needs to be redesigned to match the professional style of `purchase-order.blade.php` which has:
- Better header with company logo and info
- Professional table design with proper styling
- Signature section with placeholder
- Consistent typography and layout

## Goals
1. Make quotation PDF **landscape** orientation
2. Match design style with `purchase-order.blade.php` (header, tables, signature)
3. Add **UOM/Satuan** column to product items table
4. Create empty signature placeholders (no image, just signature box)
5. Ensure all PDFs use `public_path('logo_duta_tunggal.png')` for logo

## Files to Modify

### 1. `resources/views/pdf/quotation.blade.php`
Redesign completely to match purchase-order style with:
- Header table with company logo and info
- Info table with quotation details
- Items table with UOM column
- Signature section with empty placeholder
- Consistent CSS styling

## Implementation Details

### Header Section
```html
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
```

### Info Table
```html
<table class="info-table">
    <tr>
        <td class="label">No. Quotation</td>
        <td>: <strong>{{ $quotation->quotation_number }}</strong></td>
        <td class="label">Tanggal</td>
        <td>: {{ \Carbon\Carbon::parse($quotation->date)->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Customer</td>
        <td>: <strong>{{ $quotation->customer->name ?? '-' }}</strong></td>
        <td class="label">Valid Until</td>
        <td>: {{ $quotation->valid_until ? \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Cabang</td>
        <td>: {{ $quotation->cabang->nama ?? '-' }}</td>
        <td></td>
        <td></td>
    </tr>
</table>
```

### Items Table with UOM
```html
<table class="items-table">
    <thead>
        <tr>
            <th class="center" style="width: 5%;">No</th>
            <th>Nama Barang</th>
            <th class="center" style="width: 8%;">Satuan</th>
            <th class="center" style="width: 8%;">Qty</th>
            <th class="right" style="width: 12%;">Harga Satuan</th>
            <th class="right" style="width: 7%;">Disc (%)</th>
            <th class="center" style="width: 9%;">Tipe Pajak</th>
            <th class="right" style="width: 7%;">Tax (%)</th>
            <th class="right" style="width: 11%;">DPP</th>
            <th class="right" style="width: 9%;">PPN</th>
            <th class="right" style="width: 12%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quotation->quotationItem as $index => $item)
        <tr>
            <td class="center">{{ $index + 1 }}</td>
            <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
            <td class="center">{{ $item->product->uom->name ?? '-' }}</td>
            <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
            <td class="right">{{ $formatMoney($item->unit_price) }}</td>
            <td class="right">{{ number_format($item->discount, 2, ',', '.') }}%</td>
            <td class="center">{{ $displayTaxType }}</td>
            <td class="right">{{ number_format($item->tax, 2, ',', '.') }}%</td>
            <td class="right">{{ $formatMoney($itemDPP) }}</td>
            <td class="right">{{ $formatMoney($taxAmount) }}</td>
            <td class="right">{{ $formatMoney($lineSubtotal) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
```

### Signature Section (Empty Placeholder)
```html
<div class="signature-section">
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-box">
                    <p style="margin-bottom: 10px;">Hormat Kami,</p>
                    <div style="height: 70px; margin: 10px 0;"></div>
                    <div class="signature-name">
                        {{ $quotation->createdBy->name ?? 'Staff Penjualan' }}
                    </div>
                </div>
            </td>
            <td>
                <div class="signature-box">
                    <p style="margin-bottom: 10px;">Disetujui Oleh,</p>
                    <div style="height: 70px; margin: 10px 0;"></div>
                    <div class="signature-name">
                        {{ $quotation->customer->name ?? 'Customer' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>
```

## CSS to Include
- `.header-table` - Company header with logo
- `.company-name` - Company name styling
- `.company-info` - Company info styling
- `.logo` - Logo styling (height: 50px)
- `.title-container` - Title section
- `.title` - Title text styling
- `.info-table` - Info table layout
- `.info-table td.label` - Label styling
- `.items-table` - Items table with borders
- `.items-table th` - Header row styling
- `.right` - Right alignment class
- `.center` - Center alignment class
- `.summary-row` - Summary row styling
- `.signature-section` - Signature section
- `.signature-table` - Signature table
- `.signature-box` - Signature box for each signer
- `.signature-name` - Signature name styling

## Verification
1. Check PDF renders correctly with:
   - Company logo visible
   - All table headers correct
   - UOM column shows unit name
   - Signature boxes are empty (no images)
2. Test with sample quotation data
3. Verify alignment and styling consistency