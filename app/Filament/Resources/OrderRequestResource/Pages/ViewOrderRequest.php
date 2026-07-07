<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Helpers\MoneyHelper;
use App\Http\Controllers\HelperController;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use App\Support\OrderRequestQuantityLock;
use App\Support\ProcurementFailureNotifier;
use App\Support\TaxTypeHelper;
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
use Filament\Forms\Components\Radio;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ViewOrderRequest extends ViewRecord
{
    protected static string $resource = OrderRequestResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate cabang_kode_item for each orderRequestItem so repeater placeholder shows it
        $items = $data['orderRequestItem'] ?? [];
        $populated = [];
        foreach ($this->record->orderRequestItem as $idx => $item) {
            $entry = $items[$idx] ?? [];
            $cabangIdItem = $item->cabang_id;
            $entry['cabang_kode_item'] = '-';
            if ($cabangIdItem) {
                $c = \App\Models\Cabang::find($cabangIdItem);
                $entry['cabang_kode_item'] = $c ? ($c->kode ?? '-') : '-';
            }
            $populated[] = array_merge($entry, [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'tipe_pajak' => TaxTypeHelper::normalize($item->tipe_pajak ?? null),
                'currency_id' => $item->currency_id ?? $this->record->currency_id ?? null,
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
                    $items = $record->orderRequestItem->map(function ($item) use ($record) {
                        $remainingQty = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'];
                        if ($remainingQty <= 0) {
                            return null;
                        }
                        $unitPrice = MoneyHelper::safeParse($item->unit_price ?? 0);
                        $originalPrice = MoneyHelper::safeParse($item->original_price ?? $item->unit_price ?? 0);
                        $totalCost = max(0, $remainingQty) * $unitPrice;
                        $cabangId = $item->cabang_id;

                        $taxPct = (float)($item->tax ?? 0);
                        $preview = OrderRequestResource::calculateApprovalItemPreview(
                            (float) max(0, $remainingQty),
                            (float) $unitPrice,
                            0,
                            $taxPct,
                            OrderRequestResource::taxServiceTypeFromItemTaxType(
                                TaxTypeHelper::normalize($item->tipe_pajak ?? null)
                            )
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
                            'currency_id'      => $item->currency_id ?? $record->currency_id,
                            'product_name'     => "({$item->product->sku}) {$item->product->name}",
                            'supplier_name'    => $supplierName,
                            'cabang_name'      => $cabangName,
                            'uom'              => $uom,
                            'quantity'         => max(0, $remainingQty),
                            'original_price'   => $originalPrice,
                            'unit_price'       => $unitPrice,
                            'tax'              => $taxPct,
                            'tax_nominal'      => OrderRequestResource::formatMoneyPreviewState($preview['tax_nominal']),
                            'total_cost'       => OrderRequestResource::formatMoneyPreviewState($preview['total_cost']),
                            'subtotal'         => OrderRequestResource::formatMoneyPreviewState($preview['subtotal']),
                            'max_quantity'     => max(0, $remainingQty),
                            'approval_status'  => OrderRequestItem::normalizeApprovalStatus($item->status ?? null),
                            'rejection_note'   => $item->rejection_note,
                            'include'          => $remainingQty > 0,
                            'tipe_pajak'       => OrderRequestResource::normalizeItemTaxType($item->tipe_pajak ?? null),
                        ];
                    })->filter()->values()->toArray();

                    $groups = collect($items)
                        ->map(fn($item) => implode('|', [
                            (string) ($item['item_supplier_id'] ?? ''),
                            (string) ($item['item_cabang_id'] ?? ''),
                        ]))
                        ->filter(fn($key) => trim($key, '|') !== '')
                        ->unique();
                    $isMultiSupplier = $groups->count() > 1;

                    // Pre-fill supplier from the first item that has a supplier
                    $firstSupplierId = $record->orderRequestItem->firstWhere('supplier_id', '!=', null)?->supplier_id;

                    return [
                        'supplier_id'           => $isMultiSupplier ? null : $firstSupplierId,
                        'create_purchase_order' => true,
                        'multi_supplier'        => $isMultiSupplier,
                        'selected_items'        => $items,
                    ];
                })
                ->form([
                    Section::make('Opsi Persetujuan')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Toggle::make('create_purchase_order')
                                ->label('Buat Purchase Order secara otomatis?')
                                ->helperText('Aktifkan untuk langsung membuat PO setelah approval.')
                                ->default(true)
                                ->live()
                                ->columnSpanFull(),
                            Hidden::make('multi_supplier'),
                            Placeholder::make('multi_supplier_notice')
                                ->label('')
                                ->content('Item dalam OR ini memiliki beberapa supplier dan cabang berbeda. Sistem akan membuat satu PO per supplier secara otomatis.')
                                ->visible(fn(Get $get) => $get('create_purchase_order') && $get('multi_supplier'))
                                ->columnSpanFull(),
                        ]),
                    Section::make('Informasi Purchase Order')
                        ->icon('heroicon-o-calendar')
                        ->visible(fn(Get $get) => $get('create_purchase_order'))
                        ->columns(2)
                        ->schema([
                            DatePicker::make('order_date')
                                ->label('Tanggal Pembelian')
                                ->required()
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->validationMessages([
                                    'required' => 'Tanggal pembelian wajib diisi.',
                                ]),
                            DatePicker::make('expected_date')
                                ->label('Tanggal Diharapkan')
                                ->nullable()
                                ->native(false)
                                ->displayFormat('d M Y'),
                            Textarea::make('note')
                                ->label('Catatan')
                                ->nullable()
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    Section::make('Keputusan Item Order Request')
                        ->description(fn (Get $get): string => $get('create_purchase_order')
                            ? 'Tentukan keputusan setiap item. Checkbox Sertakan memilih item Approved yang akan dibuatkan Purchase Order otomatis.'
                            : 'Tentukan keputusan setiap item. Purchase Order tidak akan dibuat otomatis.'
                        )
                        ->icon('heroicon-o-shopping-cart')
                        ->collapsible()
                        ->schema([
                            OrderRequestResource::buildPurchaseOrderSelectedItemsRepeater(includeDependsOnAutoPurchaseOrder: true),
                        ]),
                ])
                ->visible(function ($record) {
                    return $record->status == 'request_approve' && Auth::user()->hasPermissionTo('approve order request');
                })
                ->action(function (array $data, $record) {
                    try {
                        $orderRequestService = app(OrderRequestService::class);
                        if ($data['create_purchase_order']) {
                            $includedItems = OrderRequestResource::selectedPurchaseOrderApprovedItems($data['selected_items'] ?? []);
                            if ($includedItems->isEmpty()) {
                                $data['create_purchase_order'] = false;
                                $orderRequestService->approve($record, $data);
                                $record->refresh();
                                $notification = OrderRequestResource::approvalOutcomeNotification($record);
                                if (OrderRequestItem::normalizeApprovalStatus($record->status) !== OrderRequestItem::STATUS_REJECTED) {
                                    $notification['message'] = 'Order Request disetujui, tetapi tidak ada Purchase Order dibuat karena tidak ada item Approved yang dicentang untuk dibuatkan PO otomatis.';
                                }
                                HelperController::sendNotification(...$notification);
                                return;
                            }

                            $groups = $includedItems->groupBy(function ($item) {
                                return implode('|', [
                                    (string) ($item['item_supplier_id'] ?? ''),
                                    (string) ($item['item_cabang_id'] ?? ''),
                                ]);
                            });

                            if (!empty($data['multi_supplier']) || $groups->count() > 1) {
                                $approvalData = array_merge($data, ['create_purchase_order' => false]);
                                $orderRequestService->approve($record, $approvalData);
                                $record->refresh();

                                $created = 0;
                                foreach ($groups as $groupItems) {
                                    $firstItem = $groupItems->first();
                                    $supplierId = $firstItem['item_supplier_id'] ?? null;
                                    $cabangId = $firstItem['item_cabang_id'] ?? null;
                                    if (empty($supplierId) || empty($cabangId)) {
                                        continue;
                                    }

                                    $poData = array_merge($data, [
                                        'supplier_id'    => $supplierId,
                                        'cabang_id'      => $cabangId,
                                        'po_number'      => self::generateUniquePoNumber(),
                                        'selected_items' => $groupItems->values()->toArray(),
                                        'multi_supplier' => false,
                                    ]);

                                    $orderRequestService->createPurchaseOrder($record, $poData);
                                    $created++;
                                }

                                $record->refresh();
                                $record->syncItemApprovalStatus();
                                $record->refresh();
                                HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. {$created} Purchase Order berhasil dibuat per supplier.");
                                return;
                            }

                            $data['po_number'] = self::generateUniquePoNumber();
                            $data['supplier_id'] = $data['supplier_id'] ?? self::resolveFirstIncludedSupplierId($includedItems);
                            $data['cabang_id'] = $data['cabang_id'] ?? ($includedItems->first()['item_cabang_id'] ?? null);

                            $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                            if ($purchaseOrder) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                return;
                            }
                        }

                        $orderRequestService->approve($record, $data);
                        $record->refresh();
                        HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. Purchase Order dari proses ini otomatis disetujui jika dibuat.");
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
                    $items = $record->orderRequestItem->map(function ($item) use($record) {
                        $remainingQty = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'];
                        if ($remainingQty <= 0) {
                            return null;
                        }
                        $unitPrice = MoneyHelper::safeParse($item->unit_price ?? 0);
                        $originalPrice = MoneyHelper::safeParse($item->original_price ?? $item->unit_price ?? 0);
                        $totalCost = max(0, $remainingQty) * $unitPrice;
                        $cabangId = $item->cabang_id;

                        $taxPct = (float)($item->tax ?? 0);
                        $tipePajak = OrderRequestResource::normalizeItemTaxType($item->tipe_pajak ?? null);
                        $taxType = OrderRequestResource::taxServiceTypeFromItemTaxType($tipePajak);
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
                            'currency_id'      => $item->currency_id ?? $record->currency_id,
                            'product_name'     => "({$item->product->sku}) {$item->product->name}",
                            'supplier_name'    => $supplierName,
                            'cabang_name'      => $cabangName,
                            'uom'              => $uom,
                            'quantity'         => max(0, $remainingQty),
                            'original_price'   => $originalPrice,
                            'unit_price'       => $unitPrice,
                            'tax'              => $taxPct,
                            'tax_nominal'      => OrderRequestResource::formatMoneyPreviewState($preview['tax_nominal']),
                            'total_cost'       => OrderRequestResource::formatMoneyPreviewState($preview['total_cost']),
                            'subtotal'         => OrderRequestResource::formatMoneyPreviewState($preview['subtotal']),
                            'max_quantity'     => max(0, $remainingQty),
                            'approval_status'  => OrderRequestItem::normalizeApprovalStatus($item->status ?? null),
                            'rejection_note'   => $item->rejection_note,
                            'include'          => $remainingQty > 0,
                            'tipe_pajak'       => $tipePajak,
                        ];
                    })->filter()->values()->toArray();

                    $groups = collect($items)
                        ->map(fn($item) => implode('|', [
                            (string) ($item['item_supplier_id'] ?? ''),
                            (string) ($item['item_cabang_id'] ?? ''),
                        ]))
                        ->filter(fn($key) => trim($key, '|') !== '')
                        ->unique();
                    $isMultiSupplier = $groups->count() > 1;

                    // Pre-fill supplier from the first item that has one
                    $firstSupplierId = $record->orderRequestItem->firstWhere('supplier_id', '!=', null)?->supplier_id;

                    return [
                        'supplier_id'    => $isMultiSupplier ? null : $firstSupplierId,
                        'cabang_id'      => $isMultiSupplier ? null : ($items[0]['item_cabang_id'] ?? null),
                        'multi_supplier' => $isMultiSupplier,
                        'selected_items' => $items,
                    ];
                })
                ->form([
                    Section::make('Tanggal & Catatan')
                        ->icon('heroicon-o-calendar')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('order_date')
                                ->label('Tanggal Pembelian')
                                ->required()
                                ->native(false)
                                ->displayFormat('d M Y')
                                ->validationMessages([
                                    'required' => 'Tanggal pembelian wajib diisi.',
                                ]),
                            DatePicker::make('expected_date')
                                ->label('Tanggal Diharapkan')
                                ->nullable()
                                ->native(false)
                                ->displayFormat('d M Y'),
                            Textarea::make('note')
                                ->label('Catatan')
                                ->nullable()
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    Section::make('Pilih Item yang Akan Dibeli')
                        ->description('Hanya item berstatus Approved dengan sisa qty yang dapat dibuatkan Purchase Order. Checkbox Sertakan hanya memilih item mana yang masuk PO.')
                        ->icon('heroicon-o-shopping-cart')
                        ->collapsible()
                        ->schema([
                            OrderRequestResource::buildPurchaseOrderSelectedItemsRepeater(),
                        ]),
                ])
                ->visible(function ($record) {
                    if (!Auth::user()->hasPermissionTo('approve order request') || !in_array($record->status, ['approved', 'partial'], true)) {
                        return false;
                    }
                    return OrderRequestResource::hasApprovedItemsAvailableForPurchaseOrder($record);
                })
                ->action(function (array $data, $record) {
                    $orderRequestService = app(OrderRequestService::class);
                    $includedItems = OrderRequestResource::selectedPurchaseOrderApprovedItems($data['selected_items'] ?? []);
                    if ($includedItems->isEmpty()) {
                        HelperController::sendNotification(isSuccess: false, title: 'Perhatian', message: 'Tidak ada item Approved dengan sisa qty yang bisa dibuatkan Purchase Order.');
                        return;
                    }

                    $groups = $includedItems->groupBy(function ($item) {
                        return implode('|', [
                            (string) ($item['item_supplier_id'] ?? ''),
                            (string) ($item['item_cabang_id'] ?? ''),
                        ]);
                    });

                    if (!empty($data['multi_supplier']) || $groups->count() > 1) {
                        $created = 0;
                        foreach ($groups as $groupItems) {
                            $firstItem = $groupItems->first();
                            $supplierId = $firstItem['item_supplier_id'] ?? null;
                            $cabangId = $firstItem['item_cabang_id'] ?? null;
                            if (empty($supplierId) || empty($cabangId)) {
                                continue;
                            }

                            $poData = array_merge($data, [
                                'supplier_id'    => $supplierId,
                                'cabang_id'      => $cabangId,
                                'po_number'      => self::generateUniquePoNumber(),
                                'selected_items' => $groupItems->values()->toArray(),
                                'multi_supplier' => false,
                            ]);

                            $orderRequestService->createPurchaseOrder($record, $poData);
                            $created++;
                        }

                        HelperController::sendNotification(isSuccess: true, title: 'Information', message: "{$created} Purchase Order berhasil dibuat per supplier.");
                        return;
                    }

                    $data['po_number'] = $data['po_number'] ?? self::generateUniquePoNumber();
                    $data['supplier_id'] = $data['supplier_id'] ?? self::resolveFirstIncludedSupplierId($includedItems);
                    $data['cabang_id'] = $data['cabang_id'] ?? ($includedItems->first()['item_cabang_id'] ?? null);

                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                    if ($purchaseOrder) {
                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                        return;
                    }

                    $orderRequestService->createPurchaseOrder($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order berhasil dibuat dan otomatis disetujui.");
                })
        ];
    }

    private static function generateUniquePoNumber(): string
    {
        do {
            $poNumber = HelperController::generatePoNumber();
        } while (PurchaseOrder::where('po_number', $poNumber)->exists());

        return $poNumber;
    }

    private static function resolveFirstIncludedSupplierId($includedItems): ?int
    {
        $supplierId = $includedItems->first()['item_supplier_id'] ?? null;

        return $supplierId ? (int) $supplierId : null;
    }
}
