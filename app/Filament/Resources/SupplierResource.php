<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Filament\Resources\SupplierResource\RelationManagers\PurchaseOrderRelationManager;
use App\Models\Supplier;
use App\Services\SupplierService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
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

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Supplier')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Kode supplier tidak boleh kosong',
                                'unique' => 'Kode supplier sudah digunakan !'
                            ])->required()
                            ->suffixAction(Action::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Supplier')
                                ->action(function ($set, $get, $state) {
                                    $supplierService = app(SupplierService::class);
                                    $set('code', $supplierService->generateCode());
                                })),
                        TextInput::make('name')
                            ->required()
                            ->label('Nama')
                            ->validationMessages([
                                'required' => 'Nama tidak boleh kosong'
                            ])
                            ->maxLength(255),
                        TextInput::make('address')
                            ->required()
                            ->label('Alamat')
                            ->validationMessages([
                                'required' => 'Alamat tidak boleh kosong'
                            ])
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->label('Nomor Handphone')
                            ->validationMessages([
                                'required' => 'Nomor Handphone tidak boleh kosong',
                                'regex' => 'Nomor handphone tidak valid !'
                            ])
                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->validationMessages([
                                'required' => 'Email tidak boleh kosong',
                                'email' => 'Email tidak valid !'
                            ])
                            ->required()
                            ->maxLength(255),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Nomor Handphone')
                    ->searchable(),
                TextColumn::make('email')
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
            PurchaseOrderRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
