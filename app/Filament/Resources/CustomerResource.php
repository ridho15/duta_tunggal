<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\SalesRelationManager;
use App\Models\Customer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Customer')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Customer')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->label('Nama Customer')
                            ->maxLength(255),
                        TextInput::make('perusahaan')
                            ->label('Perusahaan')
                            ->required(),
                        TextInput::make('nik_npwp')
                            ->label('NIK / NPWP')
                            ->required()
                            ->numeric(),
                        TextInput::make('address')
                            ->required()
                            ->label('Alamat')
                            ->maxLength(255),
                        TextInput::make('telephone')
                            ->label('Telepon')
                            ->tel()
                            ->placeholder('Contoh: 0211234567')
                            ->regex('/^0[2-9][0-9]{1,3}[0-9]{5,8}$/')
                            ->helperText('Hanya nomor telepon rumah/kantor, bukan nomor HP.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Handphone')
                            ->tel()
                            ->maxLength(15)
                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('fax')
                            ->label('Fax')
                            ->required(),
                        TextInput::make('tempo_kredit')
                            ->numeric()
                            ->label('Tempo Kredit (Hari)')
                            ->helperText('Hari')
                            ->required()
                            ->default(0),
                        TextInput::make('kredit_limit')
                            ->label('Kredit Limit (Rp.)')
                            ->default(0)
                            ->required()
                            ->numeric()
                            ->prefix('Rp.'),
                        Radio::make('tipe_pembayaran')
                            ->label('Tipe Bayar Customer')
                            ->inlineLabel()
                            ->options([
                                'Bebas' => 'Bebas',
                                'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                'Kredit' => 'Kredit (Bayar Kredit)'
                            ])->required(),
                        Radio::make('tipe')
                            ->label('Tipe Customer')
                            ->inlineLabel()
                            ->options([
                                'PKP' => 'PKP',
                                'PRI' => 'PRI'
                            ])
                            ->required(),
                        Checkbox::make('isSpecial')
                            ->label('Spesial (Ya / Tidak)'),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->label('Kode Customer')
                    ->label('Code'),
                TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable(),
                TextColumn::make('perusahaan')
                    ->label('Nama Perusahaan')
                    ->searchable(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable(),
                TextColumn::make('telephone')
                    ->label('Telepon')
                    ->searchable(),
                IconColumn::make('isSpecial')
                    ->label('Spesial')
                    ->boolean(),
                TextColumn::make('tempo_kredit')
                    ->label('Tempo Kredit')
                    ->formatStateUsing(function ($state) {
                        return "{$state} hari";
                    })
                    ->sortable(),
                TextColumn::make('tipe_pembayaran')
                    ->label('Tipe Bayar'),
                TextColumn::make('nik_npwp')
                    ->label('NIK / NPWP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Handphone')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            SalesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
