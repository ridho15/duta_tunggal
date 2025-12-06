<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderRequestResource\Pages;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Http\Controllers\HelperController;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderRequestResource extends Resource
{
    protected static ?string $model = OrderRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    // Part of the Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Order Request')
                    ->schema([
                        TextInput::make('request_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Nomor request wajib diisi.',
                                'unique' => 'Nomor request sudah digunakan, silakan gunakan nomor yang berbeda.',
                                'max' => 'Nomor request maksimal 255 karakter.',
                            ])
                            ->suffixAction(
                                FormAction::make('generateRequestNumber')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function ($set) {
                                        $set('request_number', HelperController::generateRequestNumber());
                                    })
                            ),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                if ($user && is_array($manageType) && in_array('all', $manageType)) {
                                    return Warehouse::all()->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    });
                                } else {
                                    return Warehouse::where('cabang_id', $user?->cabang_id)->get()->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    });
                                }
                            })
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('name', 'like', "%{$search}%")
                                    ->orWhere('kode', 'like', "%{$search}%");
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    });
                            })
                            ->required(),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                if ($user && is_array($manageType) && in_array('all', $manageType)) {
                                    return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    });
                                } else {
                                    return \App\Models\Cabang::where('id', $user?->cabang_id)->get()->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    });
                                }
                            })
                            ->default(function () {
                                $user = Auth::user();
                                return $user?->cabang_id;
                            })
                            ->required()
                            ->disabled(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                return !($user && is_array($manageType) && in_array('all', $manageType));
                            }),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->reactive()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // If supplier changes, clear existing items so product selection stays consistent
                                $set('orderRequestItem', []);
                            })
                            ->searchable()
                            ->options(function () {
                                return Supplier::select(['id', 'name', 'code'])->get()->mapWithKeys(function ($supplier) {
                                    return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                });
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return Supplier::where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                    });
                            })
                            ->required(),
                        DatePicker::make('request_date')
                            ->required(),
                        Textarea::make('note')
                            ->label('Note')
                            ->nullable(),
                        Repeater::make('orderRequestItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->hint('Pilih supplier terlebih dahulu sebelum menambah item')
                            ->disabled(fn(callable $get) => !$get('supplier_id'))
                            ->minItems(1)
                            ->required()
                            ->validationMessages([
                                'required' => 'Order request harus memiliki setidaknya satu item produk.',
                                'min' => 'Order request harus memiliki setidaknya satu item produk.',
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->reactive()
                                    ->searchable()
                                    ->options(function (callable $get) {
                                        $supplierId = $get('../../supplier_id'); // Mengakses supplier_id dari parent form
                                        if ($supplierId) {
                                            return Product::where('supplier_id', $supplierId)
                                                ->select(['id', 'name', 'sku'])
                                                ->get()
                                                ->mapWithKeys(function ($product) {
                                                    return [$product->id => "({$product->sku}) {$product->name}"];
                                                });
                                        }
                                        return [];
                                    })
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        $supplierId = $get('../../supplier_id'); // Mengakses supplier_id dari parent form
                                        $query = Product::where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%");

                                        if ($supplierId) {
                                            $query->where('supplier_id', $supplierId);
                                        }

                                        return $query->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => "({$product->sku}) {$product->name}"];
                                            });
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Textarea::make('note')
                                    ->nullable()
                                    ->label('Note')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->searchable(),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('warehouse', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->placeholder('No Supplier'),
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'approved' => 'success',
                            'rejected' => 'danger'
                        };
                    })
                    ->badge(),
                TextColumn::make('orderRequestItem')
                    ->label('Items')
                    ->formatStateUsing(function ($state) {
                        return "({$state->product->sku}) {$state->product->name}";
                    })
                    ->badge(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Order Request</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Order Request adalah permintaan pembelian internal yang dapat di-approve menjadi Purchase Order.</li>' .
                            '<li><strong>Cara Approve:</strong> Gunakan tombol <em>Approve</em> pada baris request. Saat approve, Anda dapat memilih untuk membuat Purchase Order secara langsung.</li>' .
                            '<li><strong>Create PO:</strong> Tombol <em>Create Purchase Order</em> memungkinkan pembuatan PO manual dari request yang telah di-approve.</li>' .
                            '<li><strong>Dampak:</strong> Setelah disetujui, request berubah status menjadi <em>approved</em> dan siap diteruskan ke proses pembelian.</li>' .
                            '<li><strong>Catatan:</strong> Akses tombol approve/create PO bergantung pada hak akses pengguna.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->placeholder('All Statuses'),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->code}) {$record->name}";
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->kode}) {$record->name}";
                    })
                    ->searchable()
                    ->preload(),
                Filter::make('request_date')
                    ->form([
                        DatePicker::make('request_date_from')
                            ->label('Request Date From'),
                        DatePicker::make('request_date_until')
                            ->label('Request Date Until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query->when(
                            $data['request_date_from'],
                            function ($query, $date) {
                                $query->whereDate('request_date', '>=', $date);
                            }
                        );
                        $query->when(
                            $data['request_date_until'],
                            function ($query, $date) {
                                $query->whereDate('request_date', '<=', $date);
                            }
                        );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status == 'approved';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.order-request', [
                                'orderRequest' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Order_Request_' . $record->request_number . '.pdf');
                        }),
                    Action::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->color('primary')
                        ->icon('heroicon-o-plus')
                        ->form([
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->preload()
                                ->searchable()
                                ->options(function () {
                                    return Supplier::select(['id', 'name', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    return Supplier::where('name', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                        });
                                })
                                ->default(fn ($record) => $record->supplier_id)
                                ->required(),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required()
                                ->suffixAction(
                                    FormAction::make('generatePoNumber')
                                        ->icon('heroicon-o-arrow-path')
                                        ->action(function ($set) {
                                            $set('po_number', HelperController::generatePoNumber());
                                        })
                                ),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required(),
                            DatePicker::make('expected_date')
                                ->label('Expected Date')
                                ->nullable(),
                            Textarea::make('note')
                                ->label('Note')
                                ->nullable()
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && $record->status == 'approved' && !$record->purchaseOrder;
                        })
                        ->action(function (array $data, $record) {
                            $orderRequestService = app(OrderRequestService::class);
                            // Check purchase order number
                            $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                            if ($purchaseOrder) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                return;
                            }

                            $orderRequestService->createPurchaseOrder($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order Created");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->form([
                            \Filament\Forms\Components\Toggle::make('create_purchase_order')
                                ->label('Buat Purchase Order?')
                                ->default(true)
                                ->reactive(),
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->preload()
                                ->searchable()
                                ->options(function () {
                                    return Supplier::select(['id', 'name', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    return Supplier::where('name', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                        });
                                })
                                ->default(fn ($record) => $record->supplier_id)
                                ->required(fn (\Filament\Forms\Get $get) => $get('create_purchase_order')),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required(fn (\Filament\Forms\Get $get) => $get('create_purchase_order'))
                                ->suffixAction(
                                    FormAction::make('generatePoNumber')
                                        ->icon('heroicon-o-arrow-path')
                                        ->action(function ($set) {
                                            $set('po_number', HelperController::generatePoNumber());
                                        })
                                ),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required(fn (\Filament\Forms\Get $get) => $get('create_purchase_order')),
                            DatePicker::make('expected_date')
                                ->label('Expected Date')
                                ->nullable(),
                            Textarea::make('note')
                                ->label('Note')
                                ->nullable()
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && $record->status == 'draft';
                        })
                        ->action(function (array $data, $record) {
                            $orderRequestService = app(OrderRequestService::class);
                            
                            if ($data['create_purchase_order']) {
                                // Check purchase order number only if creating PO
                                $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                                if ($purchaseOrder) {
                                    HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                    return;
                                }
                            }

                            $orderRequestService->approve($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request Approved");
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderRequests::route('/'),
            'create' => Pages\CreateOrderRequest::route('/create'),
            'view' => ViewOrderRequest::route('/{record}'),
            'edit' => Pages\EditOrderRequest::route('/{record}/edit'),
        ];
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure orderRequestItem is not empty
        if (empty($data['orderRequestItem']) || count($data['orderRequestItem']) === 0) {
            throw ValidationException::withMessages([
                'orderRequestItem' => 'Order request harus memiliki setidaknya satu item produk.'
            ]);
        }

        // Set cabang_id if not provided
        if (empty($data['cabang_id'])) {
            $user = Auth::user();
            $data['cabang_id'] = $user?->cabang_id;
        }

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure orderRequestItem is not empty
        if (empty($data['orderRequestItem']) || count($data['orderRequestItem']) === 0) {
            throw ValidationException::withMessages([
                'orderRequestItem' => 'Order request harus memiliki setidaknya satu item produk.'
            ]);
        }

        return $data;
    }
}
