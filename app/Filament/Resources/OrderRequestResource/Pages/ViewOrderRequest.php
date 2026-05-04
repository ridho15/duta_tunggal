<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Helpers\MoneyHelper;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ViewOrderRequest extends ViewRecord
{
    protected static string $resource = OrderRequestResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure header kode fields are present for the view page
        $cabangId = $this->record->cabang_id ?? null;
        $warehouseId = $this->record->warehouse_id ?? null;

        $data['cabang_kode'] = '-';
        $data['warehouse_kode'] = '-';

        if ($cabangId) {
            $c = \App\Models\Cabang::find($cabangId);
            $data['cabang_kode'] = $c ? ($c->kode ?? '-') : '-';
        }

        if ($warehouseId) {
            $w = \App\Models\Warehouse::find($warehouseId);
            $data['warehouse_kode'] = $w ? ($w->kode ?? '-') : '-';
        }

        // Populate cabang_kode_item for each orderRequestItem so repeater placeholder shows it
        $items = $data['orderRequestItem'] ?? [];
        $populated = [];
        foreach ($this->record->orderRequestItem as $idx => $item) {
            $entry = $items[$idx] ?? [];
            $cabangIdItem = $item->cabang_id ?: $this->record->cabang_id;
            $entry['cabang_kode_item'] = '-';
            if ($cabangIdItem) {
                $c = \App\Models\Cabang::find($cabangIdItem);
                $entry['cabang_kode_item'] = $c ? ($c->kode ?? '-') : '-';
            }
            $populated[] = array_merge($entry, [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ]);
        }

        $data['orderRequestItem'] = $populated;

        return $data;
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()->icon('heroicon-o-trash')
                ->color('danger'),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'draft';
                })
                ->action(function ($record) {
                    $orderRequestService = app(OrderRequestService::class);
                    $orderRequestService->reject($record);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah ditolak. Proses selanjutnya: Pemohon dapat merevisi data dan mengajukan kembali untuk mendapatkan persetujuan.");
                }),
            Action::make('request_approve')
                ->label('Request Approve')
                ->color('primary')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Ajukan Persetujuan')
                ->modalDescription('Apakah Anda yakin ingin mengajukan order request ini untuk disetujui?')
                ->visible(function ($record) {
                    return $record->status == 'draft';
                })
                ->action(function ($record) {
                    $record->update(['status' => 'request_approve']);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah diajukan untuk persetujuan.");
                }),
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->modalWidth('6xl')
                ->modalHeading('Approve Order Request')
                ->modalDescription('Tinjau dan setujui Order Request ini. Pilih item yang akan dibuatkan Purchase Order.')
                ->modalSubmitActionLabel('Approve')
                ->fillForm(function ($record) {
                    $taxType = $record->tax_type ?? 'None';
                    $items = $record->orderRequestItem->map(function ($item) use ($taxType) {
                        $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                        $unitPrice = MoneyHelper::parse($item->unit_price ?? 0);
                        $originalPrice = MoneyHelper::parse($item->original_price ?? $item->unit_price ?? 0);
                        $totalCost = max(0, $remainingQty) * $unitPrice;
                        $cabangId = $item->cabang_id ?: $record->cabang_id;

                        $taxPct = (float)($item->tax ?? 0);
                        $preview = OrderRequestResource::calculateApprovalItemPreview(
                            (float) max(0, $remainingQty),
                            (float) $unitPrice,
                            0,
                            $taxPct,
                            $taxType
                        );

                        $supplierName = $item->supplier_id
                            ? ("({$item->supplier->code}) {$item->supplier->perusahaan}")
                            : '-';
                        $cabangName = $cabangId
                            ? (function () use ($cabangId) {
                                $c = \App\Models\Cabang::find($cabangId);
                                return $c ? "({$c->kode}) {$c->nama}" : '-';
                            })()
                            : '-';
                        $uom = $item->product->uom->abbreviation ?? $item->product->uom->name ?? '-';

                        return [
                            'item_id'          => $item->id,
                            'item_supplier_id' => $item->supplier_id,
                            'item_cabang_id'   => $cabangId,
                            'product_name'     => "({$item->product->sku}) {$item->product->name}",
                            'supplier_name'    => $supplierName,
                            'cabang_name'      => $cabangName,
                            'uom'              => $uom,
                            'quantity'         => max(0, $remainingQty),
                            'original_price'   => $originalPrice,
                            'unit_price'       => $unitPrice,
                            'tax'              => $taxPct,
                            'tax_nominal'      => $preview['tax_nominal'],
                            'total_cost'       => $preview['total_cost'],
                            'subtotal'         => $preview['subtotal'],
                            'max_quantity'     => max(0, $remainingQty),
                            'include'          => $remainingQty > 0,
                        ];
                    })->values()->toArray();

                    $groups = collect($items)
                        ->map(fn ($item) => implode('|', [
                            (string) ($item['item_supplier_id'] ?? ''),
                            (string) ($item['item_cabang_id'] ?? ''),
                        ]))
                        ->filter(fn ($key) => trim($key, '|') !== '')
                        ->unique();
                    $isMultiSupplier = $groups->count() > 1;

                    // Pre-fill supplier from the first item that has a supplier
                    $firstSupplierId = $record->orderRequestItem->firstWhere('supplier_id', '!=', null)?->supplier_id;
                    $firstCabangId = $items[0]['item_cabang_id'] ?? null;

                    return [
                        'supplier_id'           => $isMultiSupplier ? null : $firstSupplierId,
                        'cabang_id'             => $isMultiSupplier ? null : $firstCabangId,
                        'create_purchase_order' => true,
                        'multi_supplier'        => $isMultiSupplier,
                        'tax_type'              => $record->tax_type ?? 'None',
                        'selected_items'        => $items,
                    ];
                })
                ->form([
                    Section::make('Opsi Persetujuan')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Hidden::make('tax_type'),
                            Hidden::make('cabang_id'),
                            Toggle::make('create_purchase_order')
                                ->label('Buat Purchase Order secara otomatis?')
                                ->helperText('Aktifkan untuk langsung membuat PO setelah approval.')
                                ->default(true)
                                ->live()
                                ->columnSpanFull(),
                            Placeholder::make('multi_supplier_notice')
                                ->label('')
                                ->content('Item dalam OR ini memiliki beberapa kombinasi supplier dan cabang berbeda. Sistem akan membuat satu PO per kombinasi secara otomatis.')
                                ->visible(fn(Get $get) => $get('create_purchase_order') && $get('multi_supplier'))
                                ->columnSpanFull(),
                            Hidden::make('multi_supplier'),
                        ]),
                    Section::make('Informasi Purchase Order')
                        ->icon('heroicon-o-document-text')
                        ->columns(2)
                        ->visible(fn(Get $get) => $get('create_purchase_order'))
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Supplier (untuk PO)')
                                ->helperText('Supplier utama untuk Purchase Order. Setiap item memiliki supplier masing-masing (lihat di tabel item).')
                                ->visible(fn(Get $get) => !$get('multi_supplier'))
                                ->preload()
                                ->searchable()
                                ->columnSpanFull()
                                ->options(function () {
                                    return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    return Supplier::where('perusahaan', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                        });
                                })
                                ->required(fn(Get $get) => $get('create_purchase_order') && !$get('multi_supplier'))
                                ->validationMessages(['required' => 'Supplier wajib dipilih.']),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->visible(fn(Get $get) => !$get('multi_supplier'))
                                ->required(fn(Get $get) => $get('create_purchase_order') && !$get('multi_supplier'))
                                ->suffixAction(
                                    FormAction::make('generatePoNumber')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Generate PO Number')
                                        ->action(fn($set) => $set('po_number', HelperController::generatePoNumber()))
                                )
                                ->validationMessages(['required' => 'Nomor PO wajib diisi.']),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required(fn(Get $get) => $get('create_purchase_order'))
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->validationMessages(['required' => 'Tanggal order wajib diisi.']),
                            DatePicker::make('expected_date')
                                ->label('Expected Delivery Date')
                                ->nullable()
                                ->native(false)
                                ->displayFormat('d M Y'),
                            Textarea::make('note')
                                ->label('Catatan')
                                ->rows(3)
                                ->columnSpanFull()
                                ->nullable(),
                        ]),
                    Section::make('Pilih Item yang Akan Dibeli')
                        ->description('Centang item yang akan dimasukkan ke PO. Harga Override dapat diubah; Harga Asli berasal dari master produk.')
                        ->icon('heroicon-o-shopping-cart')
                        ->collapsible()
                        ->visible(fn(Get $get) => $get('create_purchase_order'))
                        ->schema([
                            Repeater::make('selected_items')
                                ->label('')
                                ->columns(4)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Hidden::make('item_id'),
                                    Hidden::make('item_supplier_id'),
                                    Hidden::make('max_quantity'),
                                    TextInput::make('product_name')
                                        ->label('Nama Produk')
                                        ->readOnly(),
                                    TextInput::make('supplier_name')
                                        ->label('Supplier')
                                        ->readOnly(),
                                    TextInput::make('uom')
                                        ->label('Satuan')
                                        ->readOnly()
                                        ->columnSpan(1),
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->minValue(0)
                                        ->reactive()
                                        ->live()
                                        ->required()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $taxType = $get('../../tax_type') ?? 'None';
                                            $preview = OrderRequestResource::calculateApprovalItemPreview(
                                                (float) ($state ?? 0),
                                                (float) MoneyHelper::parse($get('unit_price') ?? 0),
                                                0,
                                                (float) ($get('tax') ?? 0),
                                                $taxType
                                            );

                                            $set('total_cost', $preview['total_cost']);
                                            $set('subtotal', $preview['subtotal']);
                                            $set('tax_nominal', $preview['tax_nominal']);
                                        })
                                        ->helperText(fn($get) => 'Maks qty: ' . ($get('max_quantity') ?? '-'))
                                        ->rules([
                                            fn($get) => function ($attribute, $value, $fail) use ($get) {
                                                $max = $get('max_quantity');
                                                if ($max !== null && $max !== '' && (float) $value > (float) $max) {
                                                    $fail("Qty tidak boleh melebihi {$max}.");
                                                }
                                            },
                                        ])
                                        ->validationMessages([
                                            'required' => 'Qty wajib diisi.',
                                            'min' => 'Qty minimal 0.',
                                        ]),
                                    TextInput::make('fulfilled_quantity')
                                        ->label('Qty Diterima (Penerimaan Barang)')
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0),
                                    TextInput::make('remaining_quantity')
                                        ->label('Sisa Qty Belum Diterima')
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0),
                                    TextInput::make('original_price')
                                        ->label('Harga Asli (Rp)')
                                        ->minValue(0)
                                        ->indonesianMoney(),
                                    TextInput::make('unit_price')
                                        ->label('Harga Override (Rp)')
                                        ->minValue(0)
                                        ->indonesianMoney()
                                        ->reactive()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $taxType = $get('../../tax_type') ?? 'None';
                                            $preview = OrderRequestResource::calculateApprovalItemPreview(
                                                (float) ($get('quantity') ?? 0),
                                                (float) MoneyHelper::parse($state ?? 0),
                                                0,
                                                (float) ($get('tax') ?? 0),
                                                $taxType
                                            );

                                            $set('total_cost', $preview['total_cost']);
                                            $set('subtotal', $preview['subtotal']);
                                            $set('tax_nominal', $preview['tax_nominal']);
                                        }),
                                    TextInput::make('tax')
                                        ->label('Pajak (%)')
                                        ->readOnly()
                                        ->suffix('%')
                                        ->columnSpan(1),
                                    TextInput::make('tax_nominal')
                                        ->label('Nominal Pajak (Rp)')
                                        ->indonesianMoney()
                                        ->readOnly(),
                                    TextInput::make('total_cost')
                                        ->label('Total (Harga × Qty)')
                                        ->indonesianMoney()
                                        ->readOnly(),
                                    TextInput::make('subtotal')
                                        ->label('Subtotal (Rp)')
                                        ->indonesianMoney()
                                        ->readOnly(),
                                    Checkbox::make('include')
                                        ->label('Sertakan')
                                        ->default(true),
                                ]),
                        ]),
                ])
                ->visible(function ($record) {
                    return $record->status == 'request_approve' && Auth::user()->hasPermissionTo('approve order request');
                })
                ->action(function (array $data, $record) {
                    try {
                        $orderRequestService = app(OrderRequestService::class);
                        if ($data['create_purchase_order']) {
                            if (!empty($data['multi_supplier'])) {
                                $includedItems = collect($data['selected_items'] ?? [])->filter(fn($i) => $i['include'] ?? false);
                                if ($includedItems->isEmpty()) {
                                    HelperController::sendNotification(isSuccess: false, title: 'Perhatian', message: 'Pilih minimal satu item.');
                                    return;
                                }

                                $groups = $includedItems->groupBy(function ($item) {
                                    return implode('|', [
                                        (string) ($item['item_supplier_id'] ?? ''),
                                        (string) ($item['item_cabang_id'] ?? ''),
                                    ]);
                                });
                                $created = 0;
                                foreach ($groups as $groupItems) {
                                    $firstItem = $groupItems->first();
                                    $supplierId = $firstItem['item_supplier_id'] ?? null;
                                    $cabangId = $firstItem['item_cabang_id'] ?? null;
                                    if (empty($supplierId) || empty($cabangId)) {
                                        continue;
                                    }
                                    $poNumber = HelperController::generatePoNumber();
                                    while (PurchaseOrder::where('po_number', $poNumber)->exists()) {
                                        $poNumber = HelperController::generatePoNumber();
                                    }

                                    $poData = array_merge($data, [
                                        'supplier_id'    => $supplierId,
                                        'cabang_id'      => $cabangId,
                                        'po_number'      => $poNumber,
                                        'selected_items' => $groupItems->values()->toArray(),
                                        'multi_supplier' => false,
                                    ]);

                                    $orderRequestService->createPurchaseOrder($record, $poData);
                                    $created++;
                                }

                                $record->refresh();
                                $record->update(['status' => 'approved']);
                                HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. {$created} Purchase Order berhasil dibuat per supplier.");
                                return;
                            }

                            $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                            if ($purchaseOrder) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                return;
                            }
                        }

                        $orderRequestService->approve($record, $data);
                        $record->refresh();
                        HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. Proses selanjutnya: Pembuatan Purchase Order oleh Tim Purchasing.");
                    } catch (Throwable $exception) {
                        ProcurementFailureNotifier::danger(
                            'Gagal Memproses Order Request',
                            $exception,
                            'Order request belum dapat diproses. Periksa data yang dipilih lalu coba lagi.'
                        );
                    }
                }),
            Action::make('create_purchase_order')
                ->label('Create Purchase Order')
                ->color('info')
                ->icon('heroicon-o-document-plus')
                ->modalWidth('6xl')
                ->modalHeading('Buat Purchase Order')
                ->modalDescription('Pilih item yang akan dimasukkan ke Purchase Order baru. Harga Override dapat diubah.')
                ->fillForm(function ($record) {
                    $taxType = $record->tax_type ?? 'None';
                    $items = $record->orderRequestItem->map(function ($item) use ($taxType) {
                        $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                        $unitPrice = MoneyHelper::parse($item->unit_price ?? 0);
                        $originalPrice = MoneyHelper::parse($item->original_price ?? $item->unit_price ?? 0);
                        $totalCost = max(0, $remainingQty) * $unitPrice;
                        $cabangId = $item->cabang_id ?: $record->cabang_id;

                        $taxPct = (float)($item->tax ?? 0);
                        $base = $totalCost;
                        try {
                            $taxRes = \App\Services\TaxService::compute($base, $taxPct, $taxType);
                            $taxNom = number_format($taxRes['ppn'], 0, ',', '.');
                        } catch (\Throwable $e) {
                            $taxNom = '0';
                        }

                        $supplierName = $item->supplier_id
                            ? ("({$item->supplier->code}) {$item->supplier->perusahaan}")
                            : '-';
                        $cabangName = $cabangId
                            ? (function () use ($cabangId) {
                                $c = \App\Models\Cabang::find($cabangId);
                                return $c ? "({$c->kode}) {$c->nama}" : '-';
                            })()
                            : '-';
                        $uom = $item->product->uom->abbreviation ?? $item->product->uom->name ?? '-';

                        return [
                            'item_id'          => $item->id,
                            'item_supplier_id' => $item->supplier_id,
                            'item_cabang_id'   => $cabangId,
                            'product_name'     => "({$item->product->sku}) {$item->product->name}",
                            'supplier_name'    => $supplierName,
                            'cabang_name'      => $cabangName,
                            'uom'              => $uom,
                            'quantity'         => max(0, $remainingQty),
                            'original_price'   => $originalPrice,
                            'unit_price'       => $unitPrice,
                            'tax'              => $taxPct,
                            'tax_nominal'      => $taxNom,
                            'total_cost'       => $totalCost,
                            'max_quantity'     => max(0, $remainingQty),
                            'include'          => $remainingQty > 0,
                        ];
                    })->values()->toArray();

                    $groups = collect($items)
                        ->map(fn ($item) => implode('|', [
                            (string) ($item['item_supplier_id'] ?? ''),
                            (string) ($item['item_cabang_id'] ?? ''),
                        ]))
                        ->filter(fn ($key) => trim($key, '|') !== '')
                        ->unique();
                    $isMultiSupplier = $groups->count() > 1;

                    // Pre-fill supplier from the first item that has one
                    $firstSupplierId = $record->orderRequestItem->firstWhere('supplier_id', '!=', null)?->supplier_id;
                    $firstCabangId = $items[0]['item_cabang_id'] ?? null;

                    return [
                        'supplier_id'    => $isMultiSupplier ? null : $firstSupplierId,
                        'cabang_id'      => $isMultiSupplier ? null : $firstCabangId,
                        'multi_supplier' => $isMultiSupplier,
                        'selected_items' => $items,
                    ];
                })
                ->form([
                    Section::make('Informasi Purchase Order')
                        ->icon('heroicon-o-document-text')
                        ->columns(2)
                        ->schema([
                            Placeholder::make('multi_supplier_notice')
                                ->label('')
                                ->content('Item dalam OR ini memiliki beberapa kombinasi supplier dan cabang berbeda. Sistem akan membuat satu PO per kombinasi secara otomatis.')
                                ->visible(fn(Get $get) => $get('multi_supplier'))
                                ->columnSpanFull(),
                            Hidden::make('multi_supplier'),
                            Hidden::make('cabang_id'),
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->visible(fn(Get $get) => !$get('multi_supplier'))
                                ->preload()
                                ->searchable()
                                ->columnSpanFull()
                                ->options(function () {
                                    return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    return Supplier::where('perusahaan', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                        });
                                })
                                ->required(fn(Get $get) => !$get('multi_supplier'))
                                ->validationMessages(['required' => 'Supplier wajib dipilih.']),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->visible(fn(Get $get) => !$get('multi_supplier'))
                                ->required(fn(Get $get) => !$get('multi_supplier'))
                                ->suffixAction(
                                    FormAction::make('generatePoNumber')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Generate PO Number')
                                        ->action(fn($set) => $set('po_number', HelperController::generatePoNumber()))
                                )
                                ->validationMessages(['required' => 'Nomor PO wajib diisi.']),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required()
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->validationMessages(['required' => 'Tanggal order wajib diisi.']),
                            DatePicker::make('expected_date')
                                ->label('Expected Delivery Date')
                                ->nullable()
                                ->native(false)
                                ->displayFormat('d M Y'),
                            Textarea::make('note')
                                ->label('Catatan')
                                ->rows(3)
                                ->columnSpanFull()
                                ->nullable(),
                        ]),
                    Section::make('Pilih Item yang Akan Dibeli')
                        ->description('Centang item yang akan dimasukkan ke dalam Purchase Order ini. Anda dapat mengubah harga override per item.')
                        ->icon('heroicon-o-shopping-cart')
                        ->collapsible()
                        ->schema([
                            Repeater::make('selected_items')
                                ->label('')
                                ->columns(12)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Hidden::make('item_id'),
                                    Hidden::make('item_supplier_id'),
                                    Hidden::make('item_cabang_id'),
                                    Hidden::make('max_quantity'),
                                    TextInput::make('product_name')
                                        ->label('Nama Produk')
                                        ->readOnly()
                                        ->columnSpan(3),
                                    TextInput::make('supplier_name')
                                        ->label('Supplier')
                                        ->readOnly()
                                        ->columnSpan(2),
                                    TextInput::make('cabang_name')
                                        ->label('Cabang')
                                        ->readOnly()
                                        ->columnSpan(2),
                                    TextInput::make('uom')
                                        ->label('Satuan')
                                        ->readOnly()
                                        ->columnSpan(1),
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->helperText(fn($get) => 'Maks qty: ' . ($get('max_quantity') ?? '-'))
                                        ->columnSpan(1)
                                        ->rules([
                                            fn($get) => function ($attribute, $value, $fail) use ($get) {
                                                $max = $get('max_quantity');
                                                if ($max !== null && $max !== '' && (float) $value > (float) $max) {
                                                    $fail("Qty tidak boleh melebihi {$max}.");
                                                }
                                            },
                                        ])
                                        ->validationMessages([
                                            'required' => 'Qty wajib diisi.',
                                            'numeric' => 'Qty harus berupa angka.',
                                            'min' => 'Qty minimal 0.',
                                        ]),
                                    TextInput::make('fulfilled_quantity')
                                        ->label('Qty Diterima (Penerimaan Barang)')
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0),
                                    TextInput::make('remaining_quantity')
                                        ->label('Sisa Qty Belum Diterima')
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0),
                                    TextInput::make('original_price')
                                        ->label('Harga Asli')
                                        ->indonesianMoney()
                                        ->readOnly()
                                        ->columnSpan(2),
                                    TextInput::make('unit_price')
                                        ->label('Harga Override')
                                        ->minValue(0)
                                        ->indonesianMoney()
                                        ->columnSpan(2),
                                    TextInput::make('tax')
                                        ->label('Pajak (%)')
                                        ->readOnly()
                                        ->suffix('%')
                                        ->columnSpan(1),
                                    TextInput::make('tax_nominal')
                                        ->label('Nominal Pajak (Rp)')
                                        ->indonesianMoney()
                                        ->readOnly()
                                        ->columnSpan(2),
                                    Checkbox::make('include')
                                        ->label('Sertakan')
                                        ->default(true)
                                        ->columnSpan(1),
                                ]),
                        ]),
                ])
                ->visible(function ($record) {
                    if (!Auth::user()->hasPermissionTo('approve order request') || $record->status !== 'approved') {
                        return false;
                    }
                    // Show button as long as some items still have unfulfilled quantity
                    return $record->orderRequestItem->contains(
                        fn($item) => ($item->quantity - ($item->fulfilled_quantity ?? 0)) > 0
                    );
                })
                ->action(function (array $data, $record) {
                    $orderRequestService = app(OrderRequestService::class);
                    if (!empty($data['multi_supplier'])) {
                        $includedItems = collect($data['selected_items'] ?? [])->filter(fn($i) => $i['include'] ?? false);
                        if ($includedItems->isEmpty()) {
                            HelperController::sendNotification(isSuccess: false, title: 'Perhatian', message: 'Pilih minimal satu item.');
                            return;
                        }

                        $groups = $includedItems->groupBy(function ($item) {
                            return implode('|', [
                                (string) ($item['item_supplier_id'] ?? ''),
                                (string) ($item['item_cabang_id'] ?? ''),
                            ]);
                        });
                        $created = 0;
                        foreach ($groups as $groupItems) {
                            $firstItem = $groupItems->first();
                            $supplierId = $firstItem['item_supplier_id'] ?? null;
                            $cabangId = $firstItem['item_cabang_id'] ?? null;
                            if (empty($supplierId) || empty($cabangId)) {
                                continue;
                            }
                            $poNumber = HelperController::generatePoNumber();
                            while (PurchaseOrder::where('po_number', $poNumber)->exists()) {
                                $poNumber = HelperController::generatePoNumber();
                            }

                            $poData = array_merge($data, [
                                'supplier_id'    => $supplierId,
                                'cabang_id'      => $cabangId,
                                'po_number'      => $poNumber,
                                'selected_items' => $groupItems->values()->toArray(),
                                'multi_supplier' => false,
                            ]);

                            $orderRequestService->createPurchaseOrder($record, $poData);
                            $created++;
                        }

                        HelperController::sendNotification(isSuccess: true, title: 'Information', message: "{$created} Purchase Order berhasil dibuat per supplier.");
                        return;
                    }

                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                    if ($purchaseOrder) {
                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                        return;
                    }

                    $orderRequestService->createPurchaseOrder($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order berhasil dibuat. Proses selanjutnya: Persetujuan Purchase Order oleh Manajer Purchasing.");
                })
        ];
    }
}
