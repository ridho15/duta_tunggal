<?php

namespace App\Filament\Resources\DepositResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class DepositLogRelationManager extends RelationManager
{
    protected static string $relationship = 'depositLog';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('reference_type')
                    ->label('Reference Type')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'App\Models\Customer') {
                            return "Customer";
                        } elseif ($state == 'App\Models\Supplier') {
                            return 'Supplier';
                        }

                        return '-';
                    }),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'create' => 'primary',
                            'use' => 'success',
                            'return' => 'warning',
                            'cancel' => 'danger'
                        };
                    })->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    }),
                TextColumn::make('Amount')
                    ->label('Amount')
                    ->sortable()
                    ->money('idr'),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
