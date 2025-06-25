<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPaymentResource\Pages;
use App\Models\Invoice;
use App\Models\VendorPayment;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Vendor Payment')
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $invoice = Invoice::find($state);
                                $set('supplier_id', $invoice->fromModel->supplier_id);
                            })
                            ->relationship('invoice', 'invoice_number', function (Builder $query) {
                                $query->where('from_model_type', 'App\Models\PurchaseOrder');
                            })
                            ->required(),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('supplier', 'name')
                            ->required(),
                        DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->required(),
                        TextInput::make('ntpn')
                            ->label('NTPN')
                            ->maxLength(255)
                            ->default(null),
                        TextInput::make('total_payment')
                            ->required()
                            ->prefix('Rp.')
                            ->default(0)
                            ->numeric(),
                        TextInput::make('diskon')
                            ->label('Diskon')
                            ->default(0)
                            ->numeric()
                            ->prefix('Rp'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->string(),
                        Radio::make('status')
                            ->label('Status')
                            ->inline()
                            ->options([
                                'Draft' => 'Draft',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid'
                            ])
                            ->required(),
                        Repeater::make('vendorPaymentDetail')
                            ->relationship()
                            ->addAction(function (Action $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle');
                            })
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Pembayaran')
                            ->columns(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                                Select::make('coa_id')
                                    ->label('COA')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('coa', 'code')
                                    ->required(),
                                Radio::make('method')
                                    ->inline()
                                    ->label("Payment Method")
                                    ->required()
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'Credit' => 'Credit',
                                        'Deposit' => 'Deposit'
                                    ]),
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('ntpn')
                    ->searchable(),
                TextColumn::make('total_payment')
                    ->label('Total Payment')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
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
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListVendorPayments::route('/'),
            'create' => Pages\CreateVendorPayment::route('/create'),
            'edit' => Pages\EditVendorPayment::route('/{record}/edit'),
        ];
    }
}
