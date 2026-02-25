<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Filament\Resources\StockOpnameResource\RelationManagers;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'Stock Opname';

    protected static ?string $pluralLabel = 'Stock Opnames';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Informasi Stock Opname')
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('Nomor Opname')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Nomor opname harus diisi',
                                'unique' => 'Nomor opname sudah digunakan'
                            ]),

                        DatePicker::make('opname_date')
                            ->label('Tanggal Opname')
                            ->required()
                            ->default(now())
                            ->validationMessages([
                                'required' => 'Tanggal opname harus diisi'
                            ]),

                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1);
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('perusahaan', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->validationMessages([
                                'required' => 'Warehouse harus dipilih'
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'in_progress' => 'Sedang Berlangsung',
                                'completed' => 'Selesai',
                                'approved' => 'Disetujui',
                            ])
                            ->default('draft')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),

                        // Hidden fields for tracking
                        Forms\Components\Hidden::make('created_by')
                            ->default(Auth::id()),

                        // Read-only fields for approved data
                        TextInput::make('approved_by')
                            ->label('Disetujui Oleh')
                            ->formatStateUsing(function ($state) {
                                if ($state) {
                                    $user = \App\Models\User::find($state);
                                    return $user ? $user->name : '-';
                                }
                                return '-';
                            })
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(function ($context, $record) {
                                return $context === 'edit' && $record && $record->approved_by;
                            }),

                        DatePicker::make('approved_at')
                            ->label('Tanggal Persetujuan')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(function ($context, $record) {
                                return $context === 'edit' && $record && $record->approved_at;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('opname_number')
                    ->label('Nomor Opname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('opname_date')
                    ->label('Tanggal Opname')
                    ->date()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->formatStateUsing(function ($state, $record) {
                        return "({$record->warehouse->kode}) {$record->warehouse->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function (Builder $query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('kode', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'in_progress' => 'warning',
                            'completed' => 'info',
                            'approved' => 'success',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => 'Draft',
                            'in_progress' => 'Sedang Berlangsung',
                            'completed' => 'Selesai',
                            'approved' => 'Disetujui',
                            default => '-'
                        };
                    })
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('Disetujui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'Sedang Berlangsung',
                        'completed' => 'Selesai',
                        'approved' => 'Disetujui',
                    ])
                    ->multiple(),

                SelectFilter::make('warehouse')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    }),

                Tables\Filters\Filter::make('opname_date')
                    ->form([
                        DatePicker::make('opname_date_from')
                            ->label('Tanggal Opname Dari'),
                        DatePicker::make('opname_date_to')
                            ->label('Tanggal Opname Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['opname_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('opname_date', '>=', $date),
                            )
                            ->when(
                                $data['opname_date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('opname_date', '<=', $date),
                            );
                    })
                    ->label('Tanggal Opname'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                    ->color('info'),
                Tables\Actions\EditAction::make()
                    ->color('warning'),
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function ($record) {
                        return $record->status === 'completed';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Stock Opname')
                    ->modalDescription('Apakah Anda yakin ingin menyetujui stock opname ini?')
                    ->modalSubmitActionLabel('Ya, Setujui')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),
                ])
                ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Stock Opname</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Stock Opname adalah proses pengecekan fisik stok inventory untuk memastikan akurasi data sistem dengan kondisi sebenarnya.</li>' .
                            '<li><strong>Status Flow:</strong> Draft → In Progress → Completed → Approved. Setiap status menandai tahap proses opname.</li>' .
                            '<li><strong>Items Opname:</strong> Untuk setiap produk, sistem menampilkan quantity sistem vs quantity fisik hasil opname, dengan perhitungan selisih otomatis.</li>' .
                            '<li><strong>Cost Calculation:</strong> Sistem menghitung nilai inventory berdasarkan average cost dari history pembelian untuk setiap produk.</li>' .
                            '<li><strong>Warehouse Specific:</strong> Opname dilakukan per warehouse untuk memastikan akurasi stok di setiap lokasi penyimpanan.</li>' .
                            '<li><strong>Adjustment Generation:</strong> Setelah approved, sistem dapat generate stock adjustment untuk menyelaraskan quantity sistem dengan hasil opname.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockOpnameItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
