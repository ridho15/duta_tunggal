<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\RelationManagers\DeliveryOrderRelationManager;
use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\Pages\ViewVehicle;
use App\Models\Vehicle;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Kendaraan';

    protected static ?string $pluralModelLabel = 'Kendaraan';

    protected static ?int $navigationSort = 26;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Vehicle')
                    ->schema([
                        TextInput::make('plate')
                            ->label('Plat Nomor / Nomor Polisi')
                            ->placeholder('Contoh: B 1234 ABC')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Plat nomor tidak boleh kosong',
                                'unique' => 'Plat nomor sudah terdaftar'
                            ])
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Jenis Kendaraan')
                            ->options([
                                'Truck' => 'Truck',
                                'Pickup' => 'Pickup',
                                'Van' => 'Van',
                                'Motor' => 'Motor',
                                'Mobil Box' => 'Mobil Box',
                                'Container' => 'Container',
                                'Trailer' => 'Trailer',
                                'Lainnya' => 'Lainnya'
                            ])
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required' => 'Jenis kendaraan harus dipilih'
                            ]),
                        TextInput::make('capacity')
                            ->label('Kapasitas')
                            ->placeholder('Contoh: 5 Ton, 1000 kg, 20 m³')
                            ->required()
                            ->maxLength(255),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plate')
                    ->label('Plat Nomor')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Plat nomor disalin!')
                    ->copyMessageDuration(1500),
                TextColumn::make('type')
                    ->label('Jenis Kendaraan')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Truck' => 'success',
                        'Pickup' => 'warning', 
                        'Van' => 'info',
                        'Motor' => 'gray',
                        'Mobil Box' => 'primary',
                        'Container' => 'danger',
                        'Trailer' => 'secondary',
                        default => 'gray',
                    }),
                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->searchable()
                    ->sortable(),
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
                SelectFilter::make('type')
                    ->label('Jenis Kendaraan')
                    ->options([
                        'Truck' => 'Truck',
                        'Pickup' => 'Pickup',
                        'Van' => 'Van',
                        'Motor' => 'Motor',
                        'Mobil Box' => 'Mobil Box',
                        'Container' => 'Container',
                        'Trailer' => 'Trailer',
                        'Lainnya' => 'Lainnya'
                    ])
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
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
            DeliveryOrderRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
