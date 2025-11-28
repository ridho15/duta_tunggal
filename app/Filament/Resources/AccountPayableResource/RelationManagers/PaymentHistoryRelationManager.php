<?php

namespace App\Filament\Resources\AccountPayableResource\RelationManagers;

use App\Models\VendorPaymentDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorPaymentDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['vendorPayment.coa', 'coa']);
            })
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('vendorPayment.payment_date')
                    ->label('Payment Date')
                    ->date('M j, Y'),
                Tables\Columns\TextColumn::make('vendorPayment.payment_number')
                    ->label('Payment Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount Paid')
                    ->money('IDR')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Paid')
                    ]),
                Tables\Columns\TextColumn::make('vendorPayment.payment_method')
                    ->label('Payment Method'),
                Tables\Columns\TextColumn::make('vendorPayment.coa.name')
                    ->label('COA'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('M j, Y H:i'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}