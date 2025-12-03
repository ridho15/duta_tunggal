<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\RakService;

class RakRelationManager extends RelationManager
{
    protected static string $relationship = 'rak';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->label('Kode Rak')
                    ->maxLength(255)
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('generateCode')
                            ->label('Generate')
                            ->icon('heroicon-o-arrow-path')
                            ->action(function (Forms\Set $set, $state, $context) {
                                $rakService = new RakService();
                                $warehouseId = $this->getOwnerRecord()->id;
                                $generatedCode = $rakService->generateKodeRak($warehouseId);
                                $set('code', $generatedCode);
                            })
                    ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Rak'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}