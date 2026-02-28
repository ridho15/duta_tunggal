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
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Reset warehouse when cabang changes
                                $set('warehouse_id', null);
                            })
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
                                $manageType = $user?->manage_type ?? [];
                                if ($user && is_array($manageType) && in_array('all', $manageType)) {
                                    return null; // Let user choose
                                } else {
                                    return $user?->cabang_id;
                                }
                            })
                            ->required()
                            ->disabled(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                return !($user && is_array($manageType) && in_array('all', $manageType));
                            })
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih.',
                            ]),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function (callable $get) {
                                $cabangId = $get('cabang_id');
                                if ($cabangId) {
                                    return Warehouse::where('cabang_id', $cabangId)->get()->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    });
                                }
                                return [];
                            })
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, callable $get) {
                                $cabangId = $get('cabang_id');
                                $query = Warehouse::where('perusahaan', 'like', "%{$search}%")
                                    ->orWhere('kode', 'like', "%{$search}%");

                                if ($cabangId) {
                                    $query->where('cabang_id', $cabangId);
                                }

                                return $query->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    });
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'Gudang wajib dipilih.',
                            ]),
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
                            ->validationMessages([
                                'required' => 'Supplier wajib dipilih.',
                            ]),
                        DatePicker::make('request_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal request wajib diisi.',
                            ]),
                        Textarea::make('note')
                            ->label('Note')
                            ->nullable(),
                        Repeater::make('orderRequestItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(6)
                            ->hint('Tambahkan item produk yang ingin dipesan')
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
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Use supplier price from product_supplier pivot; fallback to cost_price
                                                $supplierId = $get('../../supplier_id');
                                                $unitPrice = (float) $product->cost_price;
                                                if ($supplierId) {
                                                    $supplierProduct = $product->suppliers()->where('suppliers.id', $supplierId)->first();
                                                    if ($supplierProduct) {
                                                        $unitPrice = (float) $supplierProduct->pivot->supplier_price;
                                                    }
                                                }
                                                $set('unit_price', $unitPrice);
                                                // Recalculate subtotal
                                                $quantity = $get('quantity') ?? 0;
                                                $discount = $get('discount') ?? 0;
                                                $tax = $get('tax') ?? 0;
                                                $subtotal = ($quantity * $unitPrice) - $discount + $tax;
                                                $set('subtotal', $subtotal);
                                            }
                                        }
                                    })
                                    ->options(function (callable $get) {
                                        // Task 11: Allow selecting any product regardless of supplier
                                        return Product::orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => "({$product->sku}) {$product->name}"];
                                            });
                                    })
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        return Product::where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => "({$product->sku}) {$product->name}"];
                                            });
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Produk wajib dipilih.',
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = $state ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $subtotal = ($quantity * $unitPrice) - $discount + $tax;
                                        $set('subtotal', $subtotal);
                                    })
                                    ->required()
                                    ->minValue(0.01)
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi.',
                                        'numeric' => 'Quantity harus berupa angka.',
                                        'min' => 'Quantity minimal 0.01.',
                                    ]),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->default(0)
                                    ->reactive()
                                    ->readonly()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $state ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $subtotal = ($quantity * $unitPrice) - $discount + $tax;
                                        $set('subtotal', $subtotal);
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Harga satuan wajib diisi.',
                                        'numeric' => 'Harga satuan harus berupa angka.',
                                    ]),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $discount = $state ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $subtotal = ($quantity * $unitPrice) - $discount + $tax;
                                        $set('subtotal', $subtotal);
                                    }),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $tax = $state ?? 0;
                                        $subtotal = ($quantity * $unitPrice) - $discount + $tax;
                                        $set('subtotal', $subtotal);
                                    }),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->disabled()
                                    ->dehydrated(),
                                Textarea::make('note')
                                    ->nullable()
                                    ->label('Note')
                                    ->columnSpanFull()
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    }),
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
                TextColumn::make('supplier.perusahaan')
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
                            'rejected' => 'danger',
                            'closed' => 'warning'
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
                        'closed' => 'Closed',
                    ])
                    ->placeholder('All Statuses'),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'perusahaan')
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
                                ->default(fn($record) => $record->supplier_id)
                                ->options(function ($record) {
                                    return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search, $record) {
                                    return Supplier::where('perusahaan', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                        });
                                })
                                ->required()
                                ->validationMessages([
                                    'required' => 'Supplier wajib dipilih.',
                                ]),
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
                                )
                                ->validationMessages([
                                    'required' => 'Nomor PO wajib diisi.',
                                    'max' => 'Nomor PO maksimal 255 karakter.',
                                ]),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Tanggal order wajib diisi.',
                                ]),
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
                                ->default(fn($record) => $record->supplier_id)
                                ->options(function ($record) {
                                    return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                    });
                                })
                                ->getSearchResultsUsing(function (string $search, $record) {
                                    return Supplier::where('perusahaan', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                        });
                                })
                                ->required(fn(\Filament\Forms\Get $get) => $get('create_purchase_order'))
                                ->validationMessages([
                                    'required' => 'Supplier wajib dipilih.',
                                ]),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required(fn(\Filament\Forms\Get $get) => $get('create_purchase_order'))
                                ->suffixAction(
                                    FormAction::make('generatePoNumber')
                                        ->icon('heroicon-o-arrow-path')
                                        ->action(function ($set) {
                                            $set('po_number', HelperController::generatePoNumber());
                                        })
                                )
                                ->validationMessages([
                                    'required' => 'Nomor PO wajib diisi.',
                                    'max' => 'Nomor PO maksimal 255 karakter.',
                                ]),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required(fn(\Filament\Forms\Get $get) => $get('create_purchase_order'))
                                ->validationMessages([
                                    'required' => 'Tanggal order wajib diisi.',
                                ]),
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
                        }),
                    Action::make('close')
                        ->label('Close')
                        ->color('warning')
                        ->icon('heroicon-o-lock-closed')
                        ->requiresConfirmation()
                        ->modalHeading('Close Order Request')
                        ->modalDescription('Are you sure you want to close this order request? This action cannot be undone.')
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && in_array($record->status, ['draft', 'approved']);
                        })
                        ->action(function ($record) {
                            $record->update(['status' => 'closed']);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request Closed");
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
        // Set cabang_id if not provided
        if (empty($data['cabang_id'])) {
            $user = Auth::user();
            $data['cabang_id'] = $user?->cabang_id;
        }

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }
}
