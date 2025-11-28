<?php

namespace App\Filament\Resources\ChartOfAccountResource\RelationManagers;

use App\Models\CustomerReceiptItem;
use App\Models\VendorPaymentDetail;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class JournalEntryRelationManager extends RelationManager
{
    protected static string $relationship = 'journalEntries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form')
                    ->schema([
                        Section::make('source')
                            ->description("Sumber journal entry")
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                Radio::make('source_type')
                                    ->label('Source Type')
                                    ->reactive()
                                    ->options([
                                        'App\Models\CustomerReceiptItem' => 'Customer Receipt',
                                        'App\Models\VendorPaymentDetail' => 'Vendor Payment'
                                    ]),
                                Select::make('source_id')
                                    ->label(function ($get) {
                                        if ($get('source_type') == 'App\Models\CustomerReceiptItem') {
                                            return "Source Customer Receipt";
                                        } elseif ($get('source_type') == 'App\Models\VendorPaymentDetail') {
                                            return 'Source Vendor Payment';
                                        }
                                        return 'Source';
                                    })->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->options(function ($get) {
                                        if ($get('source_type') == 'App\Models\CustomerReceiptItem') {
                                            return CustomerReceiptItem::join('customer_receipts', 'customer_receipt_items.customer_receipt_id', '=', 'customer_receipts.id')
                                                ->select([
                                                    'customer_receipt_items.id',
                                                    DB::raw("CONCAT(customer_receipt_items.id, ' - ', customer_receipt_items.amount) as label")
                                                ])
                                                ->get()
                                                ->pluck('label', 'id');
                                        } elseif ($get('source_type') == 'App\Models\VendorPaymentDetail') {
                                            return VendorPaymentDetail::join('vendor_payments', 'vendor_payment_details.vendor_payment_id', '=', 'vendor_payments.id')
                                                ->select([
                                                    'vendor_payment_details.id',
                                                    DB::raw("CONCAT(vendor_payment_details.id, ' - ', vendor_payment_details.amount) as label")
                                                ])
                                                ->get()
                                                ->pluck('label', 'id');
                                        }

                                        return [];
                                    })
                                    ->required()
                            ]),
                        DatePicker::make('date')
                            ->label('Tanggal'),
                        TextInput::make('reference')
                            ->label('Reference'),
                        Textarea::make('description')
                            ->label('Deskripsi'),
                        TextInput::make('debit')
                            ->label('Debit')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('credit')
                            ->label('Kredit')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('journal_type')
                            ->label('Tipe Jurnal')
                            ->maxLength(255),

                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->sortable(),
                TextColumn::make('coa.name')->label('Akun'),
                TextColumn::make('debit')->money('IDR'),
                TextColumn::make('credit')->money('IDR'),
                TextColumn::make('reference'),
                TextColumn::make('journal_type')->label('Tipe'),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
