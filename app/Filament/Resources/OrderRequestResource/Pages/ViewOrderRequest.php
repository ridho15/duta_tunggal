<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewOrderRequest extends ViewRecord
{
    protected static string $resource = OrderRequestResource::class;

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
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order request rejected");
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
                    $items = $record->orderRequestItem->map(function ($item) {
                        $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                        return [
                            'item_id'        => $item->id,
                            'product_name'   => "({$item->product->sku}) {$item->product->name}",
                            'quantity'       => max(0, $remainingQty),
                            'original_price' => $item->original_price ?? $item->unit_price ?? 0,
                            'unit_price'     => $item->unit_price ?? 0,
                            'include'        => $remainingQty > 0,
                        ];
                    })->values()->toArray();

                    return [
                        'supplier_id'           => $record->supplier_id,
                        'create_purchase_order' => true,
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
                        ]),
                    Section::make('Informasi Purchase Order')
                        ->icon('heroicon-o-document-text')
                        ->columns(2)
                        ->visible(fn(Get $get) => $get('create_purchase_order'))
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Supplier')
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
                                ->required(fn(Get $get) => $get('create_purchase_order'))
                                ->validationMessages(['required' => 'Supplier wajib dipilih.']),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required(fn(Get $get) => $get('create_purchase_order'))
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
                                ->columns(12)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Hidden::make('item_id'),
                                    TextInput::make('product_name')
                                        ->label('Nama Produk')
                                        ->readOnly()
                                        ->columnSpan(4),
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->columnSpan(1),
                                    TextInput::make('original_price')
                                        ->label('Harga Asli')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->readOnly()
                                        ->columnSpan(2),
                                    TextInput::make('unit_price')
                                        ->label('Harga Override')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('Rp')
                                        ->columnSpan(3),
                                    Checkbox::make('include')
                                        ->label('Sertakan')
                                        ->default(true)
                                        ->columnSpan(2),
                                ]),
                        ]),
                ])
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'draft';
                })
                ->action(function (array $data, $record) {
                    $orderRequestService = app(OrderRequestService::class);
                    if ($data['create_purchase_order']) {
                        $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                        if ($purchaseOrder) {
                            HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                            return;
                        }
                    }

                    $orderRequestService->approve($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request Approved");
                }),
            Action::make('create_purchase_order')
                ->label('Create Purchase Order')
                ->color('info')
                ->icon('heroicon-o-document-plus')
                ->modalWidth('6xl')
                ->modalHeading('Buat Purchase Order')
                ->modalDescription('Pilih item yang akan dimasukkan ke Purchase Order baru. Harga Override dapat diubah.')
                ->fillForm(function ($record) {
                    $items = $record->orderRequestItem->map(function ($item) {
                        $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                        return [
                            'item_id'        => $item->id,
                            'product_name'   => "({$item->product->sku}) {$item->product->name}",
                            'quantity'       => max(0, $remainingQty),
                            'original_price' => $item->original_price ?? $item->unit_price ?? 0,
                            'unit_price'     => $item->unit_price ?? 0,
                            'include'        => $remainingQty > 0,
                        ];
                    })->values()->toArray();

                    return [
                        'supplier_id'    => $record->supplier_id,
                        'selected_items' => $items,
                    ];
                })
                ->form([
                    Section::make('Informasi Purchase Order')
                        ->icon('heroicon-o-document-text')
                        ->columns(2)
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Supplier')
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
                                ->required()
                                ->validationMessages(['required' => 'Supplier wajib dipilih.']),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required()
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
                                    TextInput::make('product_name')
                                        ->label('Nama Produk')
                                        ->readOnly()
                                        ->columnSpan(4),
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->columnSpan(1),
                                    TextInput::make('original_price')
                                        ->label('Harga Asli')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->readOnly()
                                        ->columnSpan(2),
                                    TextInput::make('unit_price')
                                        ->label('Harga Override')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('Rp')
                                        ->columnSpan(3),
                                    Checkbox::make('include')
                                        ->label('Sertakan')
                                        ->default(true)
                                        ->columnSpan(2),
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
                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                    if ($purchaseOrder) {
                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                        return;
                    }

                    $orderRequestService->createPurchaseOrder($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order Created");
                })
        ];
    }
}
