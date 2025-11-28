<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DepreciationEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'depreciationEntries';
    
    protected static ?string $title = 'History Penyusutan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('depreciation_date')
                    ->label('Tanggal Penyusutan')
                    ->required()
                    ->native(false),
                
                Forms\Components\TextInput::make('period_month')
                    ->label('Bulan Ke-')
                    ->numeric()
                    ->required(),
                
                Forms\Components\TextInput::make('period_year')
                    ->label('Tahun')
                    ->numeric()
                    ->required()
                    ->default(now()->year),
                
                Forms\Components\TextInput::make('amount')
                    ->label('Nilai Penyusutan')
                    ->numeric()
                    ->indonesianMoney()
                    ->required(),
                
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'recorded' => 'Tercatat',
                        'reversed' => 'Dibatalkan',
                    ])
                    ->default('recorded')
                    ->required(),
                
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('depreciation_date')
            ->columns([
                Tables\Columns\TextColumn::make('depreciation_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('period_month')
                    ->label('Periode')
                    ->formatStateUsing(fn ($record) => 
                        'Bulan ke-' . $record->period_month . ' (' . $record->period_year . ')'
                    ),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nilai Penyusutan')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('accumulated_total')
                    ->label('Total Akumulasi')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('book_value')
                    ->label('Nilai Buku')
                    ->money('IDR')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'recorded',
                        'danger' => 'reversed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'recorded' => 'Tercatat',
                        'reversed' => 'Dibatalkan',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'recorded' => 'Tercatat',
                        'reversed' => 'Dibatalkan',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Penyusutan')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $asset = $livewire->getOwnerRecord();
                        
                        // Calculate accumulated total
                        $previousTotal = $asset->depreciationEntries()
                            ->where('status', 'recorded')
                            ->sum('amount');
                        
                        $data['accumulated_total'] = $previousTotal + $data['amount'];
                        $data['book_value'] = $asset->purchase_cost - $data['accumulated_total'];
                        
                        return $data;
                    })
                    ->after(function (RelationManager $livewire) {
                        $asset = $livewire->getOwnerRecord();
                        $asset->updateAccumulatedDepreciation();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (RelationManager $livewire) {
                        $asset = $livewire->getOwnerRecord();
                        $asset->updateAccumulatedDepreciation();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('depreciation_date', 'desc');
    }
}
