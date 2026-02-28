<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxSettingResource\Pages;
use App\Models\TaxSetting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class TaxSettingResource extends Resource
{
    protected static ?string $model = TaxSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Tax Setting')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->validationMessages([
                                'required' => 'Nama tidak boleh kosong'
                            ])
                            ->maxLength(255),
                        TextInput::make('rate')
                            ->label('Rate')
                            ->required()
                            ->suffix('%')
                            ->validationMessages([
                                'required' => 'Rate tidak boleh kosong',
                                'numeric' => 'Rate tidak valid !'
                            ])
                            ->numeric()
                            ->default(0.00),
                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->required(),
                        Toggle::make('status')
                            ->label('Aktif / Tidak Aktif')
                            ->required(),
                        Radio::make('type')
                            ->label('Type')
                            ->options([
                                'PPN' => 'PPN',
                                'PPH' => 'PPH',
                                'CUSTOM' => 'CUSTOM'
                            ])
                            ->validationMessages([
                                'required' => 'Type belum dipilih'
                            ])
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('rate')
                    ->numeric()
                    ->label('Rate')
                    ->sortable(),
                TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('status')
                    ->label('Aktif / Tidak Aktif')
                    ->boolean(),
                TextColumn::make('type')
                    ->label('Type')
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
                EditAction::make(),
                DeleteAction::make()
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
            'index' => Pages\ListTaxSettings::route('/'),
            'create' => Pages\CreateTaxSetting::route('/create'),
            'edit' => Pages\EditTaxSetting::route('/{record}/edit'),
        ];
    }
}
