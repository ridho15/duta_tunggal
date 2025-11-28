<?php

namespace App\Filament\Resources\CustomerReceiptResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerReceiptItemRelationManager extends RelationManager
{
    protected static string $relationship = 'customerReceiptItem';
    
    protected static ?string $title = 'Detail Payment Items';
    
    // Auto expand the relation manager by default
    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Invoice')
                    ->relationship('invoice', 'invoice_number')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah Pembayaran')
                    ->numeric()
                    ->indonesianMoney()
                    ->required(),
                Forms\Components\Select::make('method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank' => 'Bank Transfer',
                        'Credit' => 'Credit',
                        'Deposit' => 'Deposit',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Tanggal Pembayaran')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('No. Invoice')
                    ->sortable()
                    ->searchable()
                    ->placeholder('No invoice linked')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->invoice) {
                            return 'Invoice not found (ID: ' . ($record->invoice_id ?? 'NULL') . ')';
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah Pembayaran')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash' => 'success',
                        'Bank Transfer' => 'info',
                        'Bank' => 'info',
                        'Credit' => 'warning',
                        'Deposit' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal Pembayaran')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank' => 'Bank Transfer', 
                        'Credit' => 'Credit',
                        'Deposit' => 'Deposit',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('payment_date', 'desc')
            ->emptyStateHeading('No Payment Items')
            ->emptyStateDescription('No payment items have been created for this customer receipt yet.');
    }
}
