<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratJalanResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Models\Customer;
use App\Models\Cabang;

class SuratJalanResource extends Resource
{
    protected static ?string $model = SuratJalan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Surat Jalan')
                    ->schema([
                        TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->required()
                            ->reactive()
                            ->suffixAction(ActionsAction::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode')
                                ->action(function ($set, $get, $state) {
                                    $suratJalanService = app(SuratJalanService::class);
                                    $set('sj_number', $suratJalanService->generateCode());
                                }))
                            ->validationMessages([
                                'required' => "Surat Jalan Number tidak boleh kosong",
                                'unique' => 'Surat Jalan number sudah digunakan'
                            ])
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DateTimePicker::make('issued_at')
                            ->label('Issue At')
                            ->validationMessages([
                                'required' => 'Tanggal Surat jalan harus dibuat'
                            ])
                            ->helperText('Tanggal surat jalan dibuat')
                            ->required(),
                        Select::make('deliveryOrder')
                            ->label('Delivery Order')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->relationship('deliveryOrder', 'do_number', function (Builder $query, $get) {
                                // J1: create => only approved DOs, edit => allow linked sent/received for compatibility.
                                $isCreatePage = str_ends_with((string) request()->path(), 'surat-jalans/create');
                                if ($isCreatePage) {
                                    $query->where('status', 'approved');
                                } else {
                                    $query->whereIn('status', ['approved', 'sent', 'received']);
                                }
                            })
                            ->multiple()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $ids = is_array($state) ? $state : (empty($state) ? [] : [$state]);
                                $deliveryOrders = DeliveryOrder::whereIn('id', $ids)->get();
                                if ($deliveryOrders->isNotEmpty()) {
                                    $set('cabang_id', $deliveryOrders->first()->cabang_id);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Delivery Order harus dipilih'
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->searchable()
                            ->preload()
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->helperText('Diisi otomatis dari Delivery Order. Dapat diubah bila perlu.')
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih'
                            ]),
                        FileUpload::make('document_path')
                            ->label('Upload Document')
                            ->directory('surat-jalan-documents')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload dokumen surat jalan (PDF, JPG, PNG, max 5MB)')
                            ->validationMessages([
                                'acceptedFileTypes' => 'File harus berupa PDF, JPG, atau PNG',
                                'maxSize' => 'Ukuran file maksimal 5MB'
                            ]),
                        Hidden::make('status')
                            ->default(1), // J2: auto-terbit, tidak perlu approval
                        Hidden::make('created_by')
                            ->default(fn () => Auth::id())
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sj_number')
                    ->label('Surat Jalan Number')
                    ->searchable(),
                TextColumn::make('delivery_orders_count')
                    ->label('Jumlah DO')
                    ->getStateUsing(function (SuratJalan $record): int {
                        return $record->deliveryOrder->count();
                    })
                    ->badge()
                    ->color('primary'),
                TextColumn::make('deliveryOrder.do_number')
                    ->searchable()
                    ->label('Delivery Order')
                    ->formatStateUsing(function (SuratJalan $record): string {
                        return $record->deliveryOrder->pluck('do_number')->implode(', ');
                    })
                    ->limit(50)
                    ->tooltip(function (SuratJalan $record): string {
                        return $record->deliveryOrder->pluck('do_number')->implode(', ');
                    }),
                TextColumn::make('customers')
                    ->label('Customer')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $customers = collect();
                        foreach ($record->deliveryOrder as $deliveryOrder) {
                            foreach ($deliveryOrder->salesOrders as $salesOrder) {
                                if ($salesOrder->customer) {
                                    $customers->push("({$salesOrder->customer->code}) {$salesOrder->customer->name}");
                                }
                            }
                        }
                        return $customers->unique()->implode(', ');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('deliveryOrder.salesOrders.customer', function (Builder $query) use ($search) {
                            $query->where('perusahaan', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('driver_info')
                    ->label('Driver')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $drivers = $record->deliveryOrder->map(function ($deliveryOrder) {
                            $driver = $deliveryOrder->driver;
                            if ($driver) {
                                $code = $driver->license ? "({$driver->license}) " : '';
                                return $code . $driver->name;
                            }
                            return null;
                        })->filter()->unique();

                        return $drivers->implode(', ') ?: '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vehicle_info')
                    ->label('Kendaraan')
                    ->getStateUsing(function (SuratJalan $record): string {
                        $vehicles = $record->deliveryOrder->map(function ($deliveryOrder) {
                            if ($deliveryOrder->vehicle) {
                                $plate = $deliveryOrder->vehicle->plate ?? $deliveryOrder->vehicle->license_plate ?? null;
                                $type = $deliveryOrder->vehicle->type ?? $deliveryOrder->vehicle->vehicle_type ?? null;
                                if ($plate && $type) {
                                    return "{$plate} ({$type})";
                                }
                                return $plate ?? $type;
                            }
                            return null;
                        })->filter()->unique();
                        return $vehicles->implode(', ') ?: '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->description('Tanggal Surat Jalan dibuat')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable(),
                TextColumn::make('signedBy.name')
                    ->label('Signed By')
                    ->searchable(),
                IconColumn::make('status')
                    ->label('Terbit')
                    ->boolean(),
                TextColumn::make('document_path')
                    ->label('Document')
                    ->getStateUsing(function (SuratJalan $record): string {
                        return $record->document_path ? 'Ada' : 'Tidak Ada';
                    })
                    ->badge()
                    ->color(function (SuratJalan $record): string {
                        return $record->document_path ? 'success' : 'danger';
                    }),
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
            ->filters([
                SelectFilter::make('customer')
                    ->label('Filter Customer')
                    ->relationship('deliveryOrder.salesOrders.customer', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Customer $record): string {
                        return "({$record->code}) {$record->name}";
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->whereHas('deliveryOrder.salesOrders', function (Builder $query) use ($data) {
                            $query->where('customer_id', $data['value']);
                        });
                    }),
                    
                SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        '1' => 'Terbit'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->where('status', $data['value']);
                    }),
                    
                Filter::make('issued_date_range')
                    ->label('Filter Tanggal Terbit')
                    ->form([
                        DatePicker::make('issued_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('issued_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '<=', $date),
                            );
                    }),
                    
            ])
            ->headerActions([])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modal()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('download_document')
                        ->label('Download Document')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->visible(function ($record) {
                            return !empty($record->document_path);
                        })
                        ->action(function ($record) {
                            return response()->download(storage_path('app/public/' . $record->document_path));
                        }),
                    Action::make('download_surat_jalan')
                        ->label('Preview / Download PDF')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->visible(fn ($record) => $record->status == 1)
                        ->url(fn ($record) => route('pdf-stream', ['type' => 'surat-jalan', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                ])
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn($record) => $record->terbit ? 'bg-blue-50' : 'bg-gray-50')
            ->description(new \Illuminate\Support\HtmlString(
                '<style>
                    .fi-ta-header:has(.sj-legend){align-items:stretch!important}
                    .sj-legend{width:100%;min-width:100%;max-width:none;box-sizing:border-box}
                    .sj-legend+.fi-ta-header,.fi-ta-description+.fi-ta-header{margin-top:16px!important}
                </style>' .
                '<div class="sj-legend space-y-4 mb-4" style="width:100%;min-width:100%;max-width:none;box-sizing:border-box;margin-bottom:16px;">' .
                '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;color:#374151;">' .
                        '<span class="flex items-center gap-2" style="display:flex;align-items:center;gap:8px;">' .
                        '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#3b82f6;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                        '</svg>' .
                        'Panduan Surat Jalan' .
                        '</span>' .
                        '<span class="transition group-open:rotate-180">' .
                        '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                        '</span>' .
                    '</summary>' .
                    '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top:12px;font-size:14px;color:#4b5563;padding-left:28px;border-left:2px solid rgba(59,130,246,0.3);">' .
                    '<ul class="list-disc pl-0" style="list-style:none;padding-left:0;">' .
                    '<li><strong>Apa ini:</strong> Surat Jalan adalah dokumen resmi pengiriman barang yang mengelompokkan beberapa Delivery Order.</li>' .
                    '<li><strong>Status:</strong> <em>Terbit</em> (otomatis saat dibuat).</li>' .
                    '<li><strong>PDF:</strong> Download PDF tersedia untuk keperluan pengiriman.</li>' .
                    '</ul>' .
                    '</div>' .
                '</details>' .
                '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:8px;">' .
                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                    '</svg>' .
                    'Legenda Warna Status Baris Data' .
                    '</h4>' .
                    '<div class="grid grid-cols-2 gap-3" style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px;">' .
                    '<div class="flex items-center gap-2 p-2 rounded-lg" style="display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;background-color:#f9fafb;border:1px solid #e5e7eb;">' .
                    '<div style="width:14px;height:14px;border-radius:3px;border:1.5px solid #9ca3af;background-color:#ffffff;flex-shrink:0;"></div>' .
                    '<span class="text-xs font-medium" style="font-size:11px;font-weight:500;">Abu (Draft)</span>' .
                    '</div>' .
                    '<div class="flex items-center gap-2 p-2 rounded-lg" style="display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;background-color:rgba(219,234,254,0.4);border:1px solid rgba(191,219,254,0.8);">' .
                    '<div style="width:14px;height:14px;border-radius:3px;background-color:#3b82f6;flex-shrink:0;"></div>' .
                    '<span class="text-xs font-medium" style="font-size:11px;font-weight:500;">Biru (Terbit)</span>' .
                    '</div>' .
                    '</div>' .
                '</div>' .
                '</div>'
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('deliveryOrder', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
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
            'index' => Pages\ListSuratJalans::route('/'),
            'create' => Pages\CreateSuratJalan::route('/create'),
            'edit' => Pages\EditSuratJalan::route('/{record}/edit'),
            'view' => Pages\ViewSuratJalan::route('/{record}'),
        ];
    }
}
