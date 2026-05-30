<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseConfirmationResource\Pages;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\WarehouseConfirmation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Warehouse;
use App\Models\Rak;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WarehouseConfirmationResource extends Resource
{
    protected static ?string $model = WarehouseConfirmation::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Konfirmasi Gudang';

    protected static ?string $pluralLabel = 'Konfirmasi Gudang';

    public static function canViewAny(): bool
    {
        return Auth::check() && Auth::user()->can('view any warehouse confirmation');
    }

    public static function canView($record): bool
    {
        return Auth::check() && Auth::user()->can('view warehouse confirmation');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && Auth::user()->can('create warehouse confirmation');
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && Auth::user()->can('update warehouse confirmation');
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && Auth::user()->can('delete warehouse confirmation');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Warehouse Confirmation')
                    ->schema([
                        Radio::make('confirmation_type')
                            ->label('Tipe Konfirmasi')
                            ->options([
                                'sales_order'          => 'Sales Order',
                                'manufacturing_order'  => 'Manufacturing Order',
                                'material_issue'       => 'Material Issue',
                                'delivery_order'       => 'Delivery Order',
                            ])
                            ->default('sales_order')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tipe konfirmasi harus dipilih',
                            ])
                            ->reactive(),

                        // Virtual field: shows DO number when WC is DO-linked (read-only on edit)
                        TextInput::make('source_number_display')
                            ->label('Sumber Konfirmasi')
                            ->disabled()
                            ->placeholder('-')
                            ->visible(function ($livewire) {
                                if (! ($livewire instanceof \Filament\Resources\Pages\EditRecord)) {
                                    return false;
                                }
                                return $livewire->record?->confirmable_type === \App\Models\DeliveryOrder::class;
                            })
                            ->helperText('WC ini dibuat otomatis dari Delivery Order — tidak dapat diubah.'),

                        // Sales Order picker — shown when type = sales_order
                        Select::make('so_id_virtual')
                            ->label('Sales Order')
                            ->preload()
                            ->searchable()
                            ->options(function ($livewire) {
                                $query = SaleOrder::with('customer')
                                    ->where('status', 'approved')
                                    ->whereIn('tipe_pengiriman', ['Kirim Langsung', 'Ambil Sendiri']);
                                // In edit context, also include the currently-linked SO
                                if ($livewire instanceof \Filament\Resources\Pages\EditRecord
                                    && $livewire->record?->confirmable_type === SaleOrder::class) {
                                    $currentSoId = $livewire->record->confirmable_id;
                                    $query = SaleOrder::with('customer')
                                        ->where(function ($q) use ($currentSoId) {
                                            $q->where('status', 'approved')
                                              ->whereIn('tipe_pengiriman', ['Kirim Langsung', 'Ambil Sendiri'])
                                              ->orWhere('id', $currentSoId);
                                        });
                                }
                                return $query->get()->mapWithKeys(fn ($so) =>
                                    [$so->id => $so->so_number . ' — ' . ($so->customer?->name ?? '-')]
                                );
                            })
                            ->visible(fn ($get) => $get('confirmation_type') === 'sales_order')
                            ->required(function ($livewire, $get) {
                                if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                                    // Not required if the WC is DO-linked (managed separately)
                                    return $livewire->record?->confirmable_type === SaleOrder::class;
                                }
                                return $get('confirmation_type') === 'sales_order';
                            })
                            ->validationMessages(['required' => 'Sales Order harus dipilih'])
                            ->reactive()
                            ->afterStateUpdated(function ($set, $state) {
                                if ($state) {
                                    $saleOrder = SaleOrder::with('saleOrderItem.product')->find($state);
                                    if ($saleOrder) {
                                        $confirmationItems = [];
                                        foreach ($saleOrder->saleOrderItem as $item) {
                                            $confirmationItems[] = [
                                                'sale_order_item_id' => $item->id,
                                                'product_name'       => $item->product->name ?? 'Unknown Product',
                                                'requested_qty'      => $item->quantity,
                                                'confirmed_qty'      => $item->quantity,
                                                'warehouse_id'       => $item->warehouse_id,
                                                'rak_id'             => $item->rak_id,
                                                'status'             => 'request',
                                            ];
                                        }
                                        $set('confirmation_items', $confirmationItems);
                                    }
                                }
                            }),

                        // Manufacturing Order picker
                        Select::make('mo_id_virtual')
                            ->label('Manufacturing Order')
                            ->preload()
                            ->searchable()
                            ->options(fn () => \App\Models\ManufacturingOrder::pluck('mo_number', 'id'))
                            ->visible(fn ($get) => $get('confirmation_type') === 'manufacturing_order')
                            ->required(fn ($get) => $get('confirmation_type') === 'manufacturing_order')
                            ->validationMessages(['required' => 'Manufacturing Order harus dipilih']),

                        // Delivery Order picker — for manually linking to a DO
                        Select::make('do_id_virtual')
                            ->label('Delivery Order')
                            ->preload()
                            ->searchable()
                            ->options(fn () => \App\Models\DeliveryOrder::pluck('do_number', 'id'))
                            ->visible(fn ($get) => $get('confirmation_type') === 'delivery_order')
                            ->required(fn ($get) => $get('confirmation_type') === 'delivery_order')
                            ->validationMessages(['required' => 'Delivery Order harus dipilih']),

                        // Confirmation Items for Sales Order
                        Repeater::make('confirmation_items')
                            ->label('Confirmation Items')
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('product_name')
                                    ->label('Product')
                                    ->disabled(),

                                TextInput::make('requested_qty')
                                    ->label('Requested Qty')
                                    ->disabled()
                                    ->numeric(),

                                TextInput::make('confirmed_qty')
                                    ->label('Confirmed Qty')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Jumlah konfirmasi wajib diisi',
                                        'numeric' => 'Jumlah konfirmasi harus berupa angka'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $requestedQty = $get('requested_qty') ?? 0;
                                        if ($state == $requestedQty) {
                                            $set('status', 'confirmed');
                                        } elseif ($state > 0 && $state < $requestedQty) {
                                            $set('status', 'partial_confirmed');
                                        } elseif ($state == 0) {
                                            $set('status', 'rejected');
                                        }
                                    }),

                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                       ->searchable()
                                       ->options(function () {
                                           $user = Auth::user();
                                           $manageType = $user?->manage_type ?? [];
                                           $query = Warehouse::where('status', 1);

                                           if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                               $query->where('cabang_id', $user?->cabang_id);
                                           }

                                           return $query->orderBy('name')
                                               ->get()
                                               ->mapWithKeys(fn($w) => [$w->id => "({$w->kode}) {$w->name}"]);
                                       })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Warehouse harus dipilih'
                                    ]),

                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');

                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => "({$rak->code}) {$rak->name}"];
                                                });
                                        }

                                        return [];
                                    }),

                                Radio::make('status')
                                    ->label('Item Status')
                                    ->options([
                                        'request' => 'Request',
                                        'confirmed' => 'Confirmed',
                                        'partial_confirmed' => 'Partial Confirmed',
                                        'rejected' => 'Rejected'
                                    ])
                                    ->default('request')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Status item harus dipilih'
                                    ]),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->visible(function ($get) {
                                return $get('confirmation_type') === 'sales_order';
                            }),

                        Textarea::make('note')
                            ->label('Notes')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('source_label')
                    ->label('Sumber')
                    ->searchable(false)
                    ->sortable(false)
                    ->getStateUsing(fn ($record) => $record->source_label),

                TextColumn::make('primary_item_source_label')
                    ->label('Source Item')
                    ->toggleable()
                    ->getStateUsing(fn ($record) => $record->primary_item_source_label),

                TextColumn::make('primary_item_product_label')
                    ->label('Produk')
                    ->toggleable()
                    ->wrap()
                    ->getStateUsing(fn ($record) => $record->primary_item_product_label),

                TextColumn::make('primary_item_warehouse_label')
                    ->label('Gudang')
                    ->toggleable()
                    ->wrap()
                    ->getStateUsing(fn ($record) => $record->primary_item_warehouse_label),

                TextColumn::make('request_qty_summary')
                    ->label('Qty Request')
                    ->toggleable()
                    ->getStateUsing(fn ($record) => $record->request_qty_summary),

                TextColumn::make('confirmable_type_label')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn ($record) => match ($record->confirmable_type) {
                        \App\Models\SaleOrder::class          => 'success',
                        \App\Models\ManufacturingOrder::class => 'warning',
                        \App\Models\MaterialIssue::class      => 'danger',
                        \App\Models\DeliveryOrder::class      => 'info',
                        default                               => 'gray',
                    })
                    ->getStateUsing(fn ($record) => $record->confirmable_type_label),

                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return match (strtolower($state)) {
                            'confirmed' => 'success',
                            'partial_confirmed' => 'warning',
                            'rejected' => 'danger',
                            'request' => 'info',
                            default => 'gray'
                        };
                    }),

                TextColumn::make('user.name')
                    ->label('Confirmed By')
                    ->sortable(),

                TextColumn::make('confirmed_at')
                    ->label('Confirmed At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('note')
                    ->label('Notes')
                    ->limit(50),

                TextColumn::make('item_audit_summary')
                    ->label('Ringkasan Item')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn ($record) => $record->item_audit_summary),

                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'request' => 'Request',
                        'confirmed' => 'Confirmed',
                        'partial_confirmed' => 'Partial Confirmed',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('source_item_type')
                    ->label('Tipe Source Item')
                    ->options([
                        'sale_order_item' => 'Sales Order Item',
                        'material_issue_item' => 'Material Issue Item',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $query, $value): Builder {
                                return $query->whereHas('warehouseConfirmationItems', function (Builder $itemQuery) use ($value) {
                                    if ($value === 'sale_order_item') {
                                        $itemQuery->whereNotNull('sale_order_item_id');
                                    }

                                    if ($value === 'material_issue_item') {
                                        $itemQuery->whereNotNull('material_issue_item_id');
                                    }
                                });
                            },
                        );
                    }),
                SelectFilter::make('product_id')
                    ->label('Produk Request')
                    ->searchable()
                    ->options(function () {
                        return Product::query()
                            ->orderBy('name')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn ($product) => [
                                $product->id => sprintf('(%s) %s', $product->sku ?? '-', $product->name ?? '-'),
                            ])
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $value): Builder => $query->whereHas('warehouseConfirmationItems', function (Builder $itemQuery) use ($value) {
                                $itemQuery->where('product_id', $value);
                            }),
                        );
                    }),
                SelectFilter::make('confirmable_type')
                    ->label('Tipe Dokumen')
                    ->options([
                        SaleOrder::class => 'Sales Order',
                        \App\Models\ManufacturingOrder::class => 'Manufacturing Order',
                        MaterialIssue::class => 'Material Issue',
                        \App\Models\DeliveryOrder::class => 'Delivery Order',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where('confirmable_type', $value),
                        );
                    }),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang Request')
                    ->options(function () {
                        return Warehouse::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($warehouse) => [
                                $warehouse->id => sprintf('(%s) %s', $warehouse->kode ?? '-', $warehouse->name ?? '-'),
                            ])
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $value): Builder => $query->whereHas('warehouseConfirmationItems', function (Builder $itemQuery) use ($value) {
                                $itemQuery->where('warehouse_id', $value);
                            }),
                        );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()->color('primary'),
                    EditAction::make()->color('success'),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Konfirmasi Gudang')
                        ->modalDescription('Setujui konfirmasi gudang ini.')
                        ->action(function (WarehouseConfirmation $record) {
                            $record->update([
                                'status' => 'confirmed',
                                'rejection_reason' => null,
                                'confirmed_by' => Auth::id(),
                                'confirmed_at' => now(),
                            ]);
                            $record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();
                        })
                        ->visible(fn (WarehouseConfirmation $record): bool => strtolower($record->status) === 'request'),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (WarehouseConfirmation $record, array $data) {
                            $record->update([
                                'status' => 'rejected',
                                'rejection_reason' => $data['rejection_reason'],
                                'confirmed_by' => Auth::id(),
                                'confirmed_at' => now(),
                            ]);
                            $record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();
                        })
                        ->visible(fn (WarehouseConfirmation $record): bool => strtolower($record->status) === 'request'),
                    DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn($record) => match (strtolower($record->status)) {
                'confirmed' => 'bg-green-50',
                'partial_confirmed' => 'bg-yellow-50',
                'rejected' => 'bg-red-50',
                'request' => 'bg-blue-50',
                default => '',
            })
            ->description(new \Illuminate\Support\HtmlString(
                '<style>.fi-ta-header:has(.wc-legend){align-items:stretch}.wc-legend{width:100%;min-width:100%;max-width:none;box-sizing:border-box;}</style>' .
                '<div class="wc-legend space-y-4 mb-6 w-full min-w-full max-w-none" style="width:100%;min-width:100%;max-width:none;box-sizing:border-box;">' .
                '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full max-w-none" style="width:100%;max-width:none;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;transition:all 0.2s;">' .
                    '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;color:#374151;">' .
                        '<span class="flex items-center gap-2" style="display:flex;align-items:center;gap:8px;">' .
                        '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#3b82f6;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                        '</svg>' .
                        'Panduan Konfirmasi Gudang' .
                        '</span>' .
                        '<span class="transition group-open:rotate-180">' .
                        '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                        '</span>' .
                    '</summary>' .
                    '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top:12px;font-size:14px;color:#4b5563;padding-left:28px;border-left:2px solid rgba(59,130,246,0.3);display:flex;flex-direction:column;gap:8px;">' .
                    '<p><strong>Apa ini:</strong> Konfirmasi Gudang adalah proses validasi dari gudang terhadap Sales Order atau Manufacturing Order.</p>' .
                    '<p><strong>Flow:</strong> Request → Confirmed/Partial Confirmed/Rejected.</p>' .
                    '<p><strong>Actions:</strong> Gunakan <em style="color:#2563eb;font-weight:600;">Approve</em> atau <em style="color:#2563eb;font-weight:600;">Reject</em> untuk memproses request.</p>' .
                    '</div>' .
                '</details>' .
                '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full max-w-none" style="width:100%;max-width:none;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:8px;">' .
                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                    '</svg>' .
                    'Legenda Warna Status Baris Data' .
                    '</h4>' .
                    '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px;">' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display:flex;align-items:center;gap:12px;padding:8px 12px;border-radius:8px;background-color:rgba(219,234,254,0.4);border:1px solid rgba(191,219,254,0.8);">' .
                    '<div style="width:16px;height:16px;border-radius:4px;background-color:#3b82f6;box-shadow:0 1px 3px rgba(59,130,246,0.4);flex-shrink:0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display:block;font-size:11px;font-weight:700;color:#1e40af;">Biru (Request)</span><span class="text-[10px] text-gray-500" style="font-size:9px;color:#6b7280;">Menunggu konfirmasi</span></div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display:flex;align-items:center;gap:12px;padding:8px 12px;border-radius:8px;background-color:rgba(220,252,231,0.4);border:1px solid rgba(187,247,208,0.8);">' .
                    '<div style="width:16px;height:16px;border-radius:4px;background-color:#22c55e;box-shadow:0 1px 3px rgba(34,197,94,0.4);flex-shrink:0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display:block;font-size:11px;font-weight:700;color:#166534;">Hijau (Confirmed)</span><span class="text-[10px] text-gray-500" style="font-size:9px;color:#6b7280;">Sudah dikonfirmasi</span></div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display:flex;align-items:center;gap:12px;padding:8px 12px;border-radius:8px;background-color:rgba(254,243,199,0.4);border:1px solid rgba(253,230,138,0.8);">' .
                    '<div style="width:16px;height:16px;border-radius:4px;background-color:#eab308;box-shadow:0 1px 3px rgba(234,179,8,0.4);flex-shrink:0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display:block;font-size:11px;font-weight:700;color:#854d0e;">Kuning (Partial)</span><span class="text-[10px] text-gray-500" style="font-size:9px;color:#6b7280;">Dikonfirmasi sebagian</span></div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display:flex;align-items:center;gap:12px;padding:8px 12px;border-radius:8px;background-color:rgba(254,226,226,0.4);border:1px solid rgba(254,202,202,0.8);">' .
                    '<div style="width:16px;height:16px;border-radius:4px;background-color:#ef4444;box-shadow:0 1px 3px rgba(239,68,68,0.4);flex-shrink:0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display:block;font-size:11px;font-weight:700;color:#991b1b;">Merah (Rejected)</span><span class="text-[10px] text-gray-500" style="font-size:9px;color:#6b7280;">Ditolak gudang</span></div>' .
                    '</div>' .
                    '</div>' .
                '</div>' .
                '</div>'
            ));
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Set confirmed_by and confirmed_at when status is being changed from request
        if (isset($data['status']) && $data['status'] !== 'request') {
            $data['confirmed_by'] = Auth::id();
            $data['confirmed_at'] = now();
        }

        return $data;
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
            'index' => Pages\ListWarehouseConfirmations::route('/'),
            'create' => Pages\CreateWarehouseConfirmation::route('/create'),
            'view' => Pages\ViewWarehouseConfirmation::route('/{record}'),
            'edit' => Pages\EditWarehouseConfirmation::route('/{record}/edit'),
        ];
    }
}
