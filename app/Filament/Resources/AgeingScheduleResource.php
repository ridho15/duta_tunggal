<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgeingScheduleResource\Pages;
use App\Filament\Resources\AgeingScheduleResource\RelationManagers;
use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\ActionsPosition;

class AgeingScheduleResource extends Resource
{
    protected static ?string $model = AgeingSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Ageing Schedule')
                    ->schema([
                        Select::make('account_payable_id')
                            ->label('Account Payable')
                            ->preload()
                            ->searchable()
                            ->relationship('accountPayable', 'id')
                            ->getOptionLabelFromRecordUsing(function (AccountPayable $accountPayable) {
                                return "{$accountPayable->invoice->invoice_number} {$accountPayable->supplier->name}";
                            })
                            ->required(),
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
                        TextInput::make('bucket')
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('accountPayable')
                    ->label('Account Payable')
                    ->formatStateUsing(function ($state) {
                        return "{$state->invoice->invoice_number} {$state->supplier->name}";
                    }),
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
                TextColumn::make('bucket'),
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
            ->actionsColumnLabel('Action')
            ->actions([
                ViewAction::make()
                    ->color('primary')
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
            // 'create' => Pages\CreateAgeingSchedule::route('/create'),
            // 'edit' => Pages\EditAgeingSchedule::route('/{record}/edit'),
        ];
    }
}
