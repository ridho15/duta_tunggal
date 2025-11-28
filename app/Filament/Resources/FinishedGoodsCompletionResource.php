<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinishedGoodsCompletionResource\Pages;
use App\Models\FinishedGoodsCompletion;
use App\Models\ProductionPlan;
use App\Models\Rak;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FinishedGoodsCompletionResource extends Resource
{
    protected static ?string $model = FinishedGoodsCompletion::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?string $navigationLabel = 'Penyelesaian Barang Jadi';

    protected static ?string $modelLabel = 'Penyelesaian Barang Jadi';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Penyelesaian')
                    ->schema([
                        Forms\Components\TextInput::make('completion_number')
                            ->label('Nomor Penyelesaian')
                            ->required()
                            ->maxLength(255)
                            ->default(function () {
                                return self::generateCompletionNumber();
                            })
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('production_plan_id')
                            ->label('Nomor Rencana Kerja')
                            ->options(function () {
                                return ProductionPlan::with(['product', 'billOfMaterial'])
                                    ->whereIn('status', ['in_progress', 'scheduled'])
                                    ->get()
                                    ->mapWithKeys(function ($plan) {
                                        return [$plan->id => "{$plan->plan_number} - {$plan->name} ({$plan->product->name})"];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $state) {
                                if ($state) {
                                    $plan = ProductionPlan::with(['product', 'uom'])->find($state);
                                    if ($plan) {
                                        $set('product_id', $plan->product_id);
                                        $set('uom_id', $plan->uom_id);
                                        $set('quantity', $plan->quantity);
                                        
                                        // Calculate WIP cost
                                        $completion = new FinishedGoodsCompletion(['production_plan_id' => $state]);
                                        $wipCost = $completion->calculateWipCost();
                                        $set('total_cost', $wipCost);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('product_id')
                            ->label('Produk yang Selesai')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Recalculate cost per unit if needed
                                $totalCost = $get('total_cost') ?? 0;
                                if ($state > 0) {
                                    $set('cost_per_unit', $totalCost / $state);
                                }
                            }),

                        Forms\Components\Select::make('uom_id')
                            ->label('Satuan')
                            ->relationship('uom', 'name')
                            ->required()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Biaya (dari WIP)')
                            ->required()
                            ->numeric()
                            ->indonesianMoney()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Otomatis dihitung dari total biaya material yang diambil'),

                        Forms\Components\DatePicker::make('completion_date')
                            ->label('Tanggal Penyelesaian')
                            ->required()
                            ->default(now())
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Lokasi Penyimpanan')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Gudang')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('rak_id', null)),

                        Forms\Components\Select::make('rak_id')
                            ->label('Rak')
                            ->options(function ($get) {
                                $warehouseId = $get('warehouse_id');
                                if (!$warehouseId) {
                                    return [];
                                }
                                return Rak::where('warehouse_id', $warehouseId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($get) => !$get('warehouse_id')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Catatan')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('completion_number')
                    ->label('Nomor Penyelesaian')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('productionPlan.plan_number')
                    ->label('No. Rencana Kerja')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return "({$record->product->sku}) {$record->product->name}";
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return number_format($record->quantity, 2) . ' ' . $record->uom->name;
                    }),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completion_date')
                    ->label('Tanggal Selesai')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => 'Draft',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),

                Tables\Filters\Filter::make('completion_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completion_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completion_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('complete')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (FinishedGoodsCompletion $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (FinishedGoodsCompletion $record) {
                        $journalService = app(\App\Services\ManufacturingJournalService::class);
                        $journalService->createFinishedGoodsCompletionJournal($record);

                        $record->update([
                            'status' => 'completed',
                            'completed_by' => \Illuminate\Support\Facades\Auth::id(),
                            'completed_at' => now(),
                        ]);

                        // Update inventory stock
                        self::updateInventoryStock($record);

                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: true,
                            title: "Berhasil",
                            message: "Penyelesaian barang jadi berhasil diproses"
                        );
                    }),
                Tables\Actions\Action::make('generate_journal_completion')
                    ->label('Generate Journal Completion')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->visible(function (FinishedGoodsCompletion $record) {
                        // Show only when completed and no journal exists yet
                        return $record->status === 'completed' && !\App\Models\JournalEntry::where('source_type', FinishedGoodsCompletion::class)->where('source_id', $record->id)->exists();
                    })
                    ->requiresConfirmation()
                    ->action(function (FinishedGoodsCompletion $record) {
                        try {
                            $service = app(\App\Services\ManufacturingJournalService::class);
                            // Use the dedicated FGC journal if available, else fallback to production completion
                            if (method_exists($service, 'createFinishedGoodsCompletionJournal')) {
                                $service->createFinishedGoodsCompletionJournal($record);
                            } else {
                                // Fallback: require a Production model linkage if needed
                                throw new \Exception('FG Completion journal method not available.');
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal Berhasil Dibuat')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Gagal Membuat Jurnal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListFinishedGoodsCompletions::route('/'),
            'create' => Pages\CreateFinishedGoodsCompletion::route('/create'),
            'edit' => Pages\EditFinishedGoodsCompletion::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Generate completion number
     */
    public static function generateCompletionNumber(): string
    {
        $date = now()->format('Ymd');
        
        $last = FinishedGoodsCompletion::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($last) {
            $lastNumber = intval(substr($last->completion_number, -4));
            $number = $lastNumber + 1;
        }

        return 'FGC-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Update inventory stock when goods are completed
     */
    protected static function updateInventoryStock(FinishedGoodsCompletion $record): void
    {
        if ($record->warehouse_id) {
            $inventoryStock = \App\Models\InventoryStock::firstOrCreate([
                'product_id' => $record->product_id,
                'warehouse_id' => $record->warehouse_id,
                'rak_id' => $record->rak_id,
            ], [
                'qty_available' => 0,
                'qty_reserved' => 0,
            ]);

            // Rely on StockMovement + Observer to adjust qty_available consistently
            \App\Models\StockMovement::create([
                'product_id' => $record->product_id,
                'warehouse_id' => $record->warehouse_id,
                'rak_id' => $record->rak_id,
                'type' => 'manufacture_in',
                'quantity' => $record->quantity,
                'value' => $record->total_cost,
                'from_model_type' => FinishedGoodsCompletion::class,
                'from_model_id' => $record->id,
                'date' => now(),
                'notes' => "Penyelesaian barang jadi: {$record->completion_number}",
                'meta' => array_filter([
                    'source' => 'finished_goods_completion',
                    'completion_id' => $record->id,
                    'completion_number' => $record->completion_number,
                    'production_plan_id' => $record->production_plan_id,
                ]),
            ]);
        }
    }
}
