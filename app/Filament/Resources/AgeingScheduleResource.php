<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgeingScheduleResource\Pages;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

class AgeingScheduleResource extends Resource
{
    protected static ?string $model = AgeingSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Finance - Akuntansi';

    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Ageing Schedule')
                    ->schema([
                        Section::make('reference')
                            ->description('Referensi untuk membuat Ageing Schedule,tidaks boleh di abaikan')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Radio::make('from_model_type')
                                    ->label('From')
                                    ->options([
                                        'App\Models\AccountPayable' => 'Payable',
                                        'App\Models\AccountReceivable' => 'Receivable'
                                    ])
                                    ->required()
                                    ->inlineLabel()
                                    ->reactive(),
                                Select::make('from_model_id')
                                    ->reactive()
                                    ->label(function ($get, $set) {
                                        if ($get('from_model_type') == 'App\Models\AccountPayable') {
                                            return "From Payable";
                                        } elseif ($get('from_model_type') == 'App\Models\AccountReceivable') {
                                            return "From Receivable";
                                        }
                                        return "From";
                                    })->required()
                                    ->preload()
                                    ->searchable()
                                    ->options(function ($get, $set) {
                                        if ($get('from_model_type') == 'App\Models\AccountPayable') {
                                            $listAccountPayable = AccountPayable::join('invoices', 'account_payables.invoice_id', '=', 'invoices.id')
                                                ->join('suppliers', 'account_payables.supplier_id', "=", 'suppliers.id')
                                                ->selectRaw('account_payables.id, CONCAT(invoices.invoice_number, " - ", suppliers.name) as label')
                                                ->pluck('label', 'account_payables.id');

                                            return $listAccountPayable;
                                        } elseif ($get('from_model_type') == 'App\Models\AccountReceivable') {
                                            $listAccountReceivable = AccountReceivable::join('invoices', 'account_receivables.invoice_id', '=', 'invoices.id')
                                                ->join('customers', 'account_receivables.customer_id', '=', 'customers.id')
                                                ->selectRaw('account_receivables.id, CONCAT(invoices.invoice_number, " - ", customers.name) as label')
                                                ->pluck('label', 'account_receivables.id');

                                            return $listAccountReceivable;
                                        }
                                    }),
                            ]),
                        DatePicker::make('invoice_date')
                            ->label('Invoice Date')
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->required(),
                        TextInput::make('days_outstanding')
                            ->label('Days Outstanding')
                            ->required()
                            ->prefix("Days")
                            ->numeric(),
                        Radio::make('bucket')
                            ->inline()
                            ->options(function () {
                                return [
                                    'Current' => 'Current',
                                    '31–60' => '31–60',
                                    '61–90' => '61–90',
                                    '>90' => '>90'
                                ];
                            })
                            ->default('Current')
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromModel.invoice.invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('from_model_type')
                    ->label('From')
                    ->formatStateUsing(function ($state) {
                        switch ($state) {
                            case 'App\Models\AccountPayable':
                                return "Payable";
                                break;
                            case 'App\Models\AccountReceivable':
                                return "Receivable";
                                break;
                            default:
                                # code...
                                break;
                        }
                    })
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('days_outstanding')
                    ->numeric()
                    ->suffix(' Days')
                    ->sortable(),
                TextColumn::make('bucket')
                    ->searchable(),
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
                SelectFilter::make('from_model_type')
                    ->label('Type')
                    ->options([
                        'App\Models\AccountPayable' => 'Payable',
                        'App\Models\AccountReceivable' => 'Receivable',
                    ]),
                SelectFilter::make('bucket')
                    ->label('Bucket')
                    ->options([
                        'Current' => 'Current',
                        '31–60' => '31–60',
                        '61–90' => '61–90',
                        '>90' => '>90',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                ])->button()
                    ->label('Action')
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([]);
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
            'index' => Pages\ListAgeingSchedules::route('/'),
            'create' => Pages\CreateAgeingSchedule::route('/create'),
            'edit' => Pages\EditAgeingSchedule::route('/{record}/edit'),
        ];
    }
}
