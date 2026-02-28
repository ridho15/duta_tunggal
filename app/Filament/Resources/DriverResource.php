<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\Pages\ViewDriver;
use App\Filament\Resources\DriverResource\RelationManagers\DeliveryOrderRelationManager;
use App\Models\Cabang;
use App\Models\Driver;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Driver')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Nama driver tidak boleh kosong',
                                'max' => 'Nama driver terlalu panjang'
                            ]),
                        TextInput::make('phone')
                            ->tel()->tel() // HTML input type "tel"
                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                            ->maxLength(15)
                            ->default(null)
                            ->validationMessages([
                                'regex' => 'Nomor telepon tidak valid (harus dimulai dengan 08)',
                                'max'   => 'Nomor telepon terlalu panjang',
                            ]),
                        TextInput::make('license')
                            ->maxLength(255)
                            ->default(null)
                            ->validationMessages([
                                'max' => 'Nomor SIM terlalu panjang'
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->validationMessages([
                                'required' => 'Cabang harus dipilih',
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('license')
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),
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
            'index' => Pages\ListDrivers::route('/'),
            // 'create' => Pages\CreateDriver::route('/create'),
            // 'view' => ViewDriver::route('/{record}'),
            // 'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
