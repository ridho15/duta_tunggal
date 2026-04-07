<x-filament-panels::page>
    @php
        $receipt = $this->record;
        $receipt->loadMissing([
            'purchaseOrder.supplier',
            'receivedBy',
            'currency',
            'purchaseReceiptItem.product',
            'purchaseReceiptItem.purchaseOrderItem.product',
            'purchaseReceiptItem.purchaseOrderItem.qualityControl.inspectedBy',
            'purchaseReceiptItem.purchaseOrderItem.qualityControl.product',
            'purchaseReceiptItem.purchaseOrderItem.qualityControl.warehouse',
            'purchaseReceiptItem.purchaseOrderItem.qualityControl.rak',
            'purchaseReceiptItem.warehouse',
            'purchaseReceiptItem.rak',
            'purchaseReceiptItem.qualityControl.inspectedBy',
            'purchaseReceiptItem.qualityControl.product',
            'purchaseReceiptItem.qualityControl.warehouse',
            'purchaseReceiptItem.qualityControl.rak',
            'purchaseReceiptBiaya.coa',
        ]);
        $receiptJournalEntries = $receipt->relatedJournalEntries()->orderBy('date')->orderBy('id')->get();
        $receiptItems = $receipt->purchaseReceiptItem->sortBy('id');
        $qcItems = $receiptItems->filter(fn ($item) => $item->resolvedQualityControl()?->exists);
        $receiptItemLabels = $receiptItems
            ->mapWithKeys(fn ($item) => [$item->id => sprintf('Item #%s - %s', $item->id, $item->product->name ?? '-')]);
        $totalDebit = (float) $receiptJournalEntries->sum('debit');
        $totalCredit = (float) $receiptJournalEntries->sum('credit');
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-2 border-b border-gray-200 pb-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Informasi Purchase Receipt</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Ringkasan receipt, purchase order, supplier, dan status penerimaan.</p>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Receipt Number</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->receipt_number }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Receipt Date</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->receipt_date?->format('d M Y H:i') ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($receipt->status ?? 'draft') }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Related Journal Entries</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receiptJournalEntries->count() }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->purchaseOrder->supplier->perusahaan ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Purchase Order</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->purchaseOrder->po_number ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Received By</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->receivedBy->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Currency</div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $receipt->currency->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Detail Produk dan QC Purchase</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Setiap baris menampilkan produk yang diterima, data PO, dan QC purchase yang terhubung ke receipt item atau item PO terkait.
                </p>
            </div>

            <div class="p-6">
                @if($receiptItems->isEmpty())
                    <div class="text-sm italic text-gray-500">Belum ada item pada purchase receipt ini.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300 text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Produk</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Purchase Order Item</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Qty PO</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Received</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Accepted</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Rejected</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Warehouse / Rak</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">QC Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiptItems as $item)
                                    @php
                                        $qc = $item->resolvedQualityControl();
                                        $poItem = $item->purchaseOrderItem;
                                        $qcStatusLabel = $qc->exists
                                            ? ($qc->status_formatted ?? 'Belum diproses')
                                            : 'Belum ada QC';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2">
                                            <div class="font-semibold text-gray-900">{{ $item->product->name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">SKU: {{ $item->product->sku ?? '-' }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2">
                                            <div class="font-semibold text-gray-900">{{ $poItem->product->name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">Unit price: Rp {{ number_format((float) ($poItem->unit_price ?? 0), 0, ',', '.') }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono">{{ number_format((float) ($poItem->quantity ?? 0), 0, ',', '.') }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono">{{ number_format((float) ($item->qty_received ?? 0), 0, ',', '.') }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono">{{ number_format((float) ($item->qty_accepted ?? 0), 0, ',', '.') }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono">{{ number_format((float) ($item->qty_rejected ?? 0), 0, ',', '.') }}</td>
                                        <td class="border border-gray-300 px-3 py-2">
                                            <div class="font-semibold text-gray-900">{{ $item->warehouse->name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">Rak: {{ $item->rak->name ?? '-' }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2">
                                            @if($qc->exists)
                                                <div class="font-semibold text-gray-900">{{ $qc->qc_number ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">Status: {{ $qcStatusLabel }}</div>
                                                <div class="text-xs text-gray-500">Passed: {{ number_format((float) ($qc->passed_quantity ?? 0), 0, ',', '.') }} | Rejected: {{ number_format((float) ($qc->rejected_quantity ?? 0), 0, ',', '.') }}</div>
                                                <div class="text-xs text-gray-500">Inspected By: {{ $qc->inspectedBy->name ?? '-' }}</div>
                                                @if(! empty($qc->notes))
                                                    <div class="mt-1 text-xs italic text-gray-500">{{ $qc->notes }}</div>
                                                @endif
                                            @else
                                                <div class="text-sm italic text-gray-500">Belum ada QC purchase.</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 grid gap-4 text-sm text-gray-700 dark:text-gray-300 md:grid-cols-3">
                        <div><span class="font-semibold">Total Item:</span> {{ $receiptItems->count() }}</div>
                        <div><span class="font-semibold">Item dengan QC:</span> {{ $qcItems->count() }}</div>
                        <div><span class="font-semibold">Item tanpa QC:</span> {{ $receiptItems->count() - $qcItems->count() }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Jurnal Penerimaan Barang</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Baris jurnal yang terhubung langsung ke penerimaan barang dan item penerimaannya.
                </p>
            </div>

            <div class="grid gap-4 border-b border-gray-200 px-6 py-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300 md:grid-cols-3">
                <div><span class="font-semibold">Total Journal Entries:</span> {{ $receiptJournalEntries->count() }}</div>
                <div><span class="font-semibold">Total Debit:</span> Rp {{ number_format($totalDebit, 0, ',', '.') }}</div>
                <div><span class="font-semibold">Total Credit:</span> Rp {{ number_format($totalCredit, 0, ',', '.') }}</div>
            </div>

            <div class="p-6">
                @if($receiptJournalEntries->isEmpty())
                    <div class="text-sm italic text-gray-500">Belum ada journal entries yang terhubung ke purchase receipt ini.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300 text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Tanggal</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Sumber</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Reference</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Debit</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right font-semibold">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiptJournalEntries as $entry)
                                    @php
                                        $sourceLabel = match ($entry->source_type) {
                                            \App\Models\PurchaseReceipt::class => 'Penerimaan Barang',
                                            \App\Models\PurchaseReceiptItem::class => $receiptItemLabels->get($entry->source_id, 'Item Penerimaan #' . $entry->source_id),
                                            default => class_basename($entry->source_type ?? 'Journal'),
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2 whitespace-nowrap">{{ optional($entry->date)->format('d M Y') ?? '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ $sourceLabel }}</td>
                                        <td class="border border-gray-300 px-3 py-2">
                                            <div class="font-mono text-xs font-semibold">{{ $entry->coa->code ?? '-' }}</div>
                                            <div>{{ $entry->coa->name ?? '-' }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2">{{ $entry->reference ?? '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono {{ $entry->debit > 0 ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-right font-mono {{ $entry->credit > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                    @if(!empty($entry->description))
                                        <tr class="bg-gray-50">
                                            <td colspan="6" class="border border-gray-300 px-3 py-1 text-xs text-gray-600 italic">
                                                <strong>Description:</strong> {{ $entry->description }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>