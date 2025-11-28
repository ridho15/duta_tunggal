<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Quotation;
use App\Models\SaleOrder;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Sales Order')
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(function ($record) {
                                return $record ? Str::upper($record->status) : '-';
                            }),
                        Select::make('quotation_id')
                            ->label('Quotation')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $quotation = Quotation::find($state);
                                foreach ($quotation->quotationItem as $item) {
                                    array_push($items, [
                                        'product_id' => $item->product_id,
                                        'quantity' => $item->quantity,
                                        'unit_price' => $item->unit_price,
                                        'discount' => $item->discount,
                                        'tax' => $item->tax,
                                        'notes' => $item->notes
                                    ]);
                                }
                                $set('total_amount', $quotation->total_amount);
                                $set('saleOrderItem', $items);
                            })
                            ->visible(function ($get) {
                                return $get('options_form') == 2;
                            })
                            ->options(Quotation::where('status', 'approve')->select(['id', 'customer_id', 'quotation_number'])->get()->pluck('quotation_number', 'id'))
                            ->required(),
                        Select::make('sale_order_id')
                            ->label('Sales Order')
                            ->preload()
                            ->loadingMessage('Loading ...')
                            ->reactive()
                            ->searchable()
                            ->visible(function ($get) {
                                return $get('options_form') == 1;
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $items = [];
                                $saleOrder = SaleOrder::find($state);
                                foreach ($saleOrder->saleOrderItem as $item) {
                                    array_push($items, [
                                        'product_id' => $item->product_id,
                                        'unit_price' => $item->unit_price,
                                        'quantity' => $item->quantity,
                                        'discount' => $item->discount,
                                        'tax' => $item->tax,
                                        'notes' => $item->notes,
                                    ]);
                                }
                                $set('total_amount', $saleOrder->total_amount);
                                $set('saleOrderItem', $items);
                            })
                            ->options(SaleOrder::select(['id', 'so_number', 'customer_id'])->get()->pluck('so_number', 'id'))
                            ->required(),
                        TextInput::make('so_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DatePicker::make('order_date')
                            ->required(),
                        DatePicker::make('delivery_date'),
                        TextInput::make('shipped_to')
                            ->label('Shipped To')
                            ->nullable(),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->indonesianMoney()
                            ->required()
                            ->disabled()
                            ->reactive()
                            ->default(0)
                            ->numeric(),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('so_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'process' => 'warning',
                            'completed' => 'success',
                            'approved' => 'success',
                            'canceled' => 'danger',
                            'request_approve' => 'primary',
                            'request_close' => 'warning',
                            'closed' => 'danger',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('shipped_to')
                    ->label('Shipped To')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('titip_saldo')
                    ->label('Titip Saldo')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('requestApproveBy.name')
                    ->label('Request Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Request Approve At'),
                TextColumn::make('requestCloseBy.name')
                    ->label('Request Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_close_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Request Approve At'),
                TextColumn::make('approveBy.name')
                    ->label('Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Approve At'),
                TextColumn::make('closeBy.name')
                    ->label('Close By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Close At'),
                TextColumn::make('rejectBy.name')
                    ->label('Reject By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reject_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Reject At'),
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
                //
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->color('primary')
            ])
            ->bulkActions([]);
    }
}
