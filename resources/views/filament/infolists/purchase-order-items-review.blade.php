@php
    use App\Filament\Resources\PurchaseOrderResource;
    use App\Models\OrderRequestItem;
    use App\Support\CurrencyConversionResolver;

    $record = $getRecord();
    $record->loadMissing([
        'purchaseOrderItem.product.uom',
        'purchaseOrderItem.product.cabang',
        'purchaseOrderItem.currency',
        'purchaseOrderItem.referItemModel.cabang',
        'purchaseOrderItem.purchaseReceiptItem',
        'purchaseOrderItem.qualityControls',
        'purchaseOrderItem.purchaseOrder.cabang',
    ]);

    $formatQty = fn ($qty) => number_format((float) $qty, 2, ',', '.');
    $formatCabang = function ($item): string {
        $cabang = $item->referItemModel?->cabang;
        if (! $cabang || ! $cabang->exists) {
            $cabang = $item->product?->cabang;
        }
        if (! $cabang || ! $cabang->exists) {
            $cabang = $item->purchaseOrder?->cabang;
        }

        if (! $cabang || ! $cabang->exists) {
            return '-';
        }

        return $cabang->kode ? "({$cabang->kode}) {$cabang->nama}" : ($cabang->nama ?? '-');
    };

    $rows = $record->purchaseOrderItem->values()->map(function ($item, $index) use ($formatQty, $formatCabang) {
        $currencyId = is_numeric($item->currency_id ?? null) ? (int) $item->currency_id : null;
        $preview = PurchaseOrderResource::calculateCurrencyPreview(
            (float) ($item->quantity ?? 0),
            (float) ($item->unit_price ?? 0),
            (float) ($item->discount ?? 0),
            (float) ($item->tax ?? 0),
            PurchaseOrderResource::normalizeTaxTypeValue($item->tipe_pajak ?? null),
            $currencyId
        );
        $symbol = CurrencyConversionResolver::resolveSymbol($currencyId);
        $receipt = $item->purchaseReceiptItem;
        $received = (float) $receipt->sum('qty_received');
        $accepted = (float) $receipt->sum('qty_accepted');
        $rejected = (float) $receipt->sum('qty_rejected');
        $source = filled($item->refer_item_model_id) ? 'Order Request' : 'Manual';
        $refer = filled($item->refer_item_model_id)
            ? class_basename($item->refer_item_model_type ?: OrderRequestItem::class) . ' #' . $item->refer_item_model_id
            : '-';
        $product = $item->product && $item->product->exists
            ? '(' . ($item->product->sku ?? '-') . ') ' . ($item->product->name ?? '-')
            : '-';
        $currency = $item->currency && $item->currency->exists
            ? trim(($item->currency->code ? $item->currency->code . ' - ' : '') . $item->currency->name . ' (' . $item->currency->symbol . ')')
            : '-';

        return [
            'key' => (string) ($item->id ?? $index),
            'no' => $index + 1,
            'sku' => $item->product?->sku ?? '-',
            'product' => $product,
            'product_name' => $item->product?->name ?? '-',
            'source' => $source,
            'source_value' => filled($item->refer_item_model_id) ? 'order_request' : 'manual',
            'refer' => $refer,
            'currency' => $currency,
            'qty' => $formatQty($item->quantity ?? 0),
            'uom' => $item->product?->uom?->abbreviation ?? $item->product?->uom?->name ?? '-',
            'cabang' => $formatCabang($item),
            'unit_price' => $symbol . ' ' . PurchaseOrderResource::formatCurrencyPreviewState($item->unit_price ?? 0, $currencyId),
            'discount' => number_format((float) ($item->discount ?? 0), 2, ',', '.') . '%',
            'discount_nominal' => $symbol . ' ' . PurchaseOrderResource::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId),
            'total' => $symbol . ' ' . PurchaseOrderResource::formatCurrencyPreviewState($preview['total'], $currencyId),
            'tax' => number_format((float) ($item->tax ?? 0), 2, ',', '.') . '%',
            'tax_nominal' => $symbol . ' ' . PurchaseOrderResource::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId),
            'tipe_pajak' => strtoupper(PurchaseOrderResource::normalizeTaxTypeValue($item->tipe_pajak ?? null)),
            'tipe_pajak_value' => PurchaseOrderResource::normalizeTaxTypeValue($item->tipe_pajak ?? null),
            'subtotal' => $symbol . ' ' . PurchaseOrderResource::formatCurrencyPreviewState($preview['subtotal'], $currencyId),
            'received' => $formatQty($received),
            'accepted' => $formatQty($accepted),
            'rejected' => $formatQty($rejected),
            'remaining' => $formatQty(max(0, (float) ($item->quantity ?? 0) - $accepted)),
            'qc_status' => $item->qualityControls->count() > 0 ? 'Sudah ada QC (' . $item->qualityControls->count() . ')' : 'Belum ada QC',
            'search' => strtolower(implode(' ', [
                $item->product?->sku,
                $item->product?->name,
                $source,
                $refer,
                $currency,
                $formatCabang($item),
                PurchaseOrderResource::normalizeTaxTypeValue($item->tipe_pajak ?? null),
            ])),
        ];
    })->all();
@endphp

<style>
    .dt-po-review{border:1px solid #e5e7eb;border-radius:12px;background:#fff;overflow:hidden}
    .dt-po-review-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:12px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
    .dt-po-review-search{flex:1 1 280px;min-width:220px;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;font-size:13px;background:#fff}
    .dt-po-review-filters{display:flex;gap:8px;flex-wrap:wrap}
    .dt-po-review-select{border:1px solid #d1d5db;border-radius:10px;padding:9px 34px 9px 11px;background-color:#fff;font-size:13px}
    .dt-po-review-table-wrap{overflow:auto}
    .dt-po-review-table{width:100%;min-width:1280px;border-collapse:collapse;font-size:13px}
    .dt-po-review-table th{padding:10px;border-bottom:1px solid #e5e7eb;background:#f8fafc;color:#475569;font-weight:800;text-align:left;white-space:nowrap}
    .dt-po-review-table td{padding:10px;border-bottom:1px solid #eef2f7;vertical-align:middle}
    .dt-po-review-number{text-align:right;white-space:nowrap}
    .dt-po-review-badge{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800;background:#eff6ff;color:#1d4ed8;white-space:nowrap}
    .dt-po-review-badge.manual{background:#f3f4f6;color:#4b5563}
    .dt-po-review-expand{border:1px solid #e5e7eb;border-radius:9px;background:#fff;color:#1d4ed8;font-weight:900;width:30px;height:30px;cursor:pointer}
    .dt-po-review-detail{background:#fcfdff}
    .dt-po-review-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:14px 18px}
    .dt-po-review-card{border:1px solid #e5e7eb;border-radius:11px;background:#fff;padding:12px}
    .dt-po-review-card strong{display:block;margin-bottom:7px;color:#111827}
    .dt-po-review-line{display:flex;gap:8px;padding:3px 0;font-size:12px}
    .dt-po-review-line span:first-child{flex:0 0 120px;color:#64748b;font-weight:800}
    .dt-po-review-empty{padding:18px;text-align:center;color:#64748b}
    @media(max-width:900px){.dt-po-review-detail-grid{grid-template-columns:1fr}.dt-po-review-toolbar{align-items:stretch}.dt-po-review-filters{width:100%}.dt-po-review-select{flex:1 1 160px}}
</style>

<div class="dt-po-review" data-dt-po-review>
    <div class="dt-po-review-toolbar">
        <input class="dt-po-review-search" data-dt-po-review-search type="search" placeholder="Search item / product / currency / cabang...">
        <div class="dt-po-review-filters">
            <select class="dt-po-review-select" data-dt-po-review-tax>
                <option value="">Semua tipe pajak</option>
                <option value="inklusif">Inklusif</option>
                <option value="eklusif">Eklusif</option>
                <option value="none">Non Pajak</option>
            </select>
            <select class="dt-po-review-select" data-dt-po-review-source>
                <option value="">Semua sumber</option>
                <option value="order_request">Dari Order Request</option>
                <option value="manual">Manual</option>
            </select>
        </div>
    </div>

    <div class="dt-po-review-table-wrap">
        <table class="dt-po-review-table">
            <thead>
                <tr>
                    <th></th>
                    <th>No</th>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Source</th>
                    <th>Refer Item</th>
                    <th>Mata Uang</th>
                    <th>Qty</th>
                    <th>UOM</th>
                    <th>Cabang</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                    <th>Tipe Pajak</th>
                    <th>Remaining Qty</th>
                </tr>
            </thead>
            @foreach ($rows as $row)
                <tbody data-dt-po-review-item data-search="{{ $row['search'] }}" data-tax="{{ $row['tipe_pajak_value'] }}" data-source="{{ $row['source_value'] }}">
                        <tr>
                            <td><button type="button" class="dt-po-review-expand" data-dt-po-review-toggle>+</button></td>
                            <td>{{ $row['no'] }}</td>
                            <td>{{ $row['sku'] }}</td>
                            <td><strong>{{ $row['product_name'] }}</strong></td>
                            <td><span class="dt-po-review-badge {{ $row['source_value'] === 'manual' ? 'manual' : '' }}">{{ $row['source'] }}</span></td>
                            <td>{{ $row['refer'] }}</td>
                            <td>{{ $row['currency'] }}</td>
                            <td class="dt-po-review-number">{{ $row['qty'] }}</td>
                            <td>{{ $row['uom'] }}</td>
                            <td>{{ $row['cabang'] }}</td>
                            <td class="dt-po-review-number">{{ $row['unit_price'] }}</td>
                            <td class="dt-po-review-number">{{ $row['subtotal'] }}</td>
                            <td><span class="dt-po-review-badge">{{ $row['tipe_pajak'] }}</span></td>
                            <td class="dt-po-review-number">{{ $row['remaining'] }}</td>
                        </tr>
                        <tr class="dt-po-review-detail" data-dt-po-review-detail hidden>
                            <td colspan="14">
                                <div class="dt-po-review-detail-grid">
                                    <div class="dt-po-review-card">
                                        <strong>Produk & Sumber</strong>
                                        <div class="dt-po-review-line"><span>Product</span><div>{{ $row['product'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Source</span><div>{{ $row['source'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Refer Item</span><div>{{ $row['refer'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Cabang</span><div>{{ $row['cabang'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Qty / UOM</span><div>{{ $row['qty'] }} {{ $row['uom'] }}</div></div>
                                    </div>
                                    <div class="dt-po-review-card">
                                        <strong>Harga & Pajak</strong>
                                        <div class="dt-po-review-line"><span>Unit Price</span><div>{{ $row['unit_price'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Discount</span><div>{{ $row['discount'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Disc Nominal</span><div>{{ $row['discount_nominal'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Total</span><div>{{ $row['total'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Tax</span><div>{{ $row['tax'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Tax Nominal</span><div>{{ $row['tax_nominal'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Subtotal</span><div>{{ $row['subtotal'] }}</div></div>
                                    </div>
                                    <div class="dt-po-review-card">
                                        <strong>Receipt & QC</strong>
                                        <div class="dt-po-review-line"><span>Received</span><div>{{ $row['received'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Accepted</span><div>{{ $row['accepted'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Rejected</span><div>{{ $row['rejected'] }}</div></div>
                                        <div class="dt-po-review-line"><span>Remaining</span><div>{{ $row['remaining'] }}</div></div>
                                        <div class="dt-po-review-line"><span>QC Status</span><div>{{ $row['qc_status'] }}</div></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                </tbody>
            @endforeach
            <tbody>
                <tr data-dt-po-review-empty hidden>
                    <td colspan="14" class="dt-po-review-empty">Tidak ada item yang cocok dengan pencarian/filter aktif.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    (() => {
        const boot = () => {
            document.querySelectorAll('[data-dt-po-review]').forEach((panel) => {
                if (panel.dataset.dtPoReviewReady === '1') return;
                panel.dataset.dtPoReviewReady = '1';

                const search = panel.querySelector('[data-dt-po-review-search]');
                const tax = panel.querySelector('[data-dt-po-review-tax]');
                const source = panel.querySelector('[data-dt-po-review-source]');
                const items = Array.from(panel.querySelectorAll('[data-dt-po-review-item]'));
                const empty = panel.querySelector('[data-dt-po-review-empty]');

                const applyFilters = () => {
                    const term = (search?.value || '').trim().toLowerCase();
                    const taxValue = tax?.value || '';
                    const sourceValue = source?.value || '';
                    let visibleCount = 0;

                    items.forEach((item) => {
                        const matchesSearch = ! term || (item.dataset.search || '').includes(term);
                        const matchesTax = ! taxValue || item.dataset.tax === taxValue;
                        const matchesSource = ! sourceValue || item.dataset.source === sourceValue;
                        const visible = matchesSearch && matchesTax && matchesSource;

                        item.hidden = ! visible;
                        if (visible) visibleCount++;
                    });

                    if (empty) empty.hidden = visibleCount > 0;
                };

                panel.querySelectorAll('[data-dt-po-review-toggle]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const item = button.closest('[data-dt-po-review-item]');
                        const detail = item?.querySelector('[data-dt-po-review-detail]');
                        if (! detail) return;

                        detail.hidden = ! detail.hidden;
                        button.textContent = detail.hidden ? '+' : '-';
                    });
                });

                search?.addEventListener('input', applyFilters);
                tax?.addEventListener('change', applyFilters);
                source?.addEventListener('change', applyFilters);
                applyFilters();
            });
        };

        boot();
        document.addEventListener('livewire:navigated', boot);
        document.addEventListener('livewire:init', boot);
    })();
</script>
