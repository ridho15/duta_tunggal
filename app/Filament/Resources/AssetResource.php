<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource\RelationManagers;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use Filament\Tables\Enums\ActionsPosition;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationGroup = 'Finance - Akuntansi';
    
    protected static ?string $navigationLabel = 'Aset Tetap';
    
    protected static ?string $modelLabel = 'Aset Tetap';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Aset')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Beli')
                            ->required()
                            ->default(now())
                            ->native(false),
                        
                        Forms\Components\DatePicker::make('usage_date')
                            ->label('Tanggal Pakai')
                            ->required()
                            ->default(now())
                            ->native(false),
                        
                        Forms\Components\TextInput::make('purchase_cost')
                            ->label('Biaya Aset (Rp)')
                            ->required()
                            ->numeric()
                            ->indonesianMoney()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('salvage_value')
                            ->label('Nilai Sisa (Rp)')
                            ->numeric()
                            ->indonesianMoney()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('useful_life_years')
                            ->label('Umur Manfaat Aset (Tahun)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(5)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Chart of Accounts')
                    ->schema([
                        Forms\Components\Select::make('asset_coa_id')
                            ->label('Aset')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '1210.01', '1210.02', '1210.03', '1210.04'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Aset (1210.01 - 1210.04)'),
                        
                        Forms\Components\Select::make('accumulated_depreciation_coa_id')
                            ->label('Akumulasi Penyusutan')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '1220.01', '1220.02', '1220.03', '1220.04'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Akumulasi Penyusutan (1220.01 - 1220.04)'),
                        
                        Forms\Components\Select::make('depreciation_expense_coa_id')
                            ->label('Beban Penyusutan')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '6311', '6312', '6313', '6314'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Beban Penyusutan (6311 - 6314)'),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Perhitungan Penyusutan')
                    ->schema([
                        Forms\Components\Placeholder::make('depreciable_amount')
                            ->label('Nilai yang Dapat Disusutkan')
                            ->content(function (Get $get) {
                                $purchaseCost = (float) str_replace(',', '', $get('purchase_cost') ?? 0);
                                $salvageValue = (float) str_replace(',', '', $get('salvage_value') ?? 0);
                                $depreciable = $purchaseCost - $salvageValue;
                                return 'Rp ' . number_format($depreciable, 2, ',', '.');
                            }),
                        
                        Forms\Components\Placeholder::make('annual_depreciation_display')
                            ->label('Penyusutan Per Tahun')
                            ->content(function (Get $get) {
                                $annual = (float) str_replace(',', '', $get('annual_depreciation') ?? 0);
                                return 'Rp ' . number_format($annual, 2, ',', '.');
                            }),
                        
                        Forms\Components\Placeholder::make('monthly_depreciation_display')
                            ->label('Penyusutan Per Bulan')
                            ->content(function (Get $get) {
                                $monthly = (float) str_replace(',', '', $get('monthly_depreciation') ?? 0);
                                return 'Rp ' . number_format($monthly, 2, ',', '.');
                            }),
                        
                        Forms\Components\Hidden::make('annual_depreciation'),
                        Forms\Components\Hidden::make('monthly_depreciation'),
                        Forms\Components\Hidden::make('book_value'),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Status & Catatan')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'disposed' => 'Dijual/Dihapus',
                                'fully_depreciated' => 'Sudah Disusutkan Penuh',
                            ])
                            ->default('active')
                            ->required(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Forms\Components\Select::make('product_id')
                    ->label('Product Master')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->disabledOn('edit')
                    ->visibleOn('view'),
            ]);
    }
    
    protected static function calculateDepreciation(Get $get, Set $set): void
    {
        $purchaseCost = (float) str_replace(',', '', $get('purchase_cost') ?? 0);
        $salvageValue = (float) str_replace(',', '', $get('salvage_value') ?? 0);
        $usefulLife = (float) $get('useful_life_years') ?? 1;
        
        if ($purchaseCost > 0 && $usefulLife > 0) {
            $depreciableAmount = $purchaseCost - $salvageValue;
            $annualDepreciation = $depreciableAmount / $usefulLife;
            $monthlyDepreciation = $annualDepreciation / 12;
            
            $set('annual_depreciation', number_format($annualDepreciation, 2, '.', ''));
            $set('monthly_depreciation', number_format($monthlyDepreciation, 2, '.', ''));
            $set('book_value', number_format($purchaseCost, 2, '.', ''));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('assetCoa.name')
                    ->label('Kategori Aset')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Tgl Beli')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('usage_date')
                    ->label('Tgl Pakai')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('purchase_cost')
                    ->label('Biaya Aset')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('useful_life_years')
                    ->label('Umur (Thn)')
                    ->suffix(' tahun')
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('monthly_depreciation')
                    ->label('Penyusutan/Bulan')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('accumulated_depreciation')
                    ->label('Akum. Penyusutan')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('book_value')
                    ->label('Nilai Buku')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'disposed',
                        'warning' => 'fully_depreciated',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'disposed' => 'Dijual/Dihapus',
                        'fully_depreciated' => 'Disusutkan Penuh',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product Master')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'disposed' => 'Dijual/Dihapus',
                        'fully_depreciated' => 'Sudah Disusutkan Penuh',
                    ]),
                
                Tables\Filters\SelectFilter::make('asset_coa_id')
                    ->label('Kategori Aset')
                    ->relationship('assetCoa', 'name'),
                
                Tables\Filters\Filter::make('purchase_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    }),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make()->color('success'),
                    Tables\Actions\Action::make('calculate_depreciation')
                    ->color('warning')
                        ->label('Hitung Penyusutan')
                        ->icon('heroicon-o-calculator')
                        ->action(function (Asset $record) {
                            $record->calculateDepreciation();
                            \Filament\Notifications\Notification::make()
                                ->title('Penyusutan berhasil dihitung')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('post_asset_journal')
                        ->color('info')
                        ->label('Post Jurnal Akuisisi')
                        ->icon('heroicon-o-document-plus')
                        ->visible(fn (Asset $record) => !$record->hasPostedJournals())
                        ->action(function (Asset $record) {
                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetAcquisitionJournal($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal akuisisi aset berhasil dipost')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('post_depreciation_journal')
                        ->color('purple')
                        ->label('Post Jurnal Penyusutan')
                        ->icon('heroicon-o-chart-bar')
                        ->action(function (Asset $record) {
                            $currentMonth = now()->format('Y-m');
                            $depreciationAmount = $record->monthlyDepreciation ?? 0;
                            
                            if ($depreciationAmount <= 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak ada penyusutan untuk dipost')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetDepreciationJournal($record, $depreciationAmount, $currentMonth);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal penyusutan berhasil dipost')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('view_asset_journals')
                        ->color('gray')
                        ->label('Lihat Jurnal')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Asset $record) => route('filament.admin.resources.assets.view', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DepreciationEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
