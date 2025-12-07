<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Cabang;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Forms\Components\Checkbox;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Saade\FilamentAutograph\Forms\Components\SignaturePad as ComponentsSignaturePad;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    // Use the user-friendly group name
    protected static ?string $navigationGroup = 'User Roles Management';

    // Order similarly with other role/permission resources
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form User')
                    ->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Username wajib diisi',
                                'unique' => 'Username sudah digunakan'
                            ]),
                        TextInput::make('telepon')
                            ->label('Telepon')
                            ->tel()
                            ->validationMessages([
                                'tel' => 'Format telepon tidak valid'
                            ]),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->same('konfirmasi_password')
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Password wajib diisi',
                                'same' => 'Password tidak sama'
                            ])
                            ->revealable()
                            ->required(fn(string $context): bool => $context === 'create'),
                        TextInput::make('konfirmasi_password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->revealable()
                            ->same('password')
                            ->validationMessages([
                                'required' => 'Password tidak boleh kosong',
                                'same' => 'Password tidak sama'
                            ])
                            ->required(fn(string $context): bool => $context === 'create'),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Email wajib diisi',
                                'email' => 'Format email tidak valid',
                                'unique' => 'Email sudah digunakan',
                                'max' => 'Email maksimal 255 karakter'
                            ]),
                        Select::make('roles')
                            ->label('Level')
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->relationship('roles', 'name'),
                        Select::make('permissions')
                            ->label('Permissions')
                            ->preload()
                            ->searchable()
                            ->multiple()
                            ->relationship('permissions', 'name'),
                        Select::make('manage_type')
                            ->label('Kelola')
                            ->options([
                                'all' => 'Semua Cabang / Gudang',
                                'cabang' => 'Cabang',
                                'warehouse' => 'Gudang'
                            ])
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Kelola wajib dipilih'
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->preload()
                            ->searchable()
                            ->helperText("Untuk mengaktifkan cabang silahkan pilih cabang pada kelola")
                            ->reactive()
                            ->disabled(function ($set, $get) {
                                if (in_array('all', $get('manage_type'))) {
                                    return true;
                                } elseif (in_array('cabang', $get('manage_type'))) {
                                    return false;
                                }

                                return true;
                            })
                            ->relationship('cabang', 'nama')
                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                return "({$cabang->kode}) {$cabang->nama}";
                            })
                            ->nullable(),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->preload()
                            ->helperText("Untuk mengaktifkan gudang silahkan pilih gudang pada kelola")
                            ->searchable()
                            ->reactive()
                            ->disabled(function ($set, $get) {
                                if (in_array('all', $get('manage_type'))) {
                                    return true;
                                } elseif (in_array('warehouse', $get('manage_type'))) {
                                    return false;
                                }

                                return true;
                            })
                            ->relationship('warehouse', 'name', function (Builder $query, $get) {
                                $query->where('cabang_id', $get('cabang_id'));
                            })
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            })
                            ->nullable(),
                        TextInput::make('first_name')
                            ->label('Nama Depan')
                            ->string()
                            ->maxLength(50)
                            ->required()
                            ->validationMessages([
                                'required' => 'Nama depan wajib diisi',
                                'max' => 'Nama depan maksimal 50 karakter'
                            ]),
                        TextInput::make('last_name')
                            ->label('Nama Belakang')
                            ->maxLength(50)
                            ->string()
                            ->nullable()
                            ->validationMessages([
                                'max' => 'Nama belakang maksimal 50 karakter'
                            ]),
                        TextInput::make('kode_user')
                            ->label('Kode User')
                            ->maxLength(50)
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Kode user wajib diisi',
                                'unique' => 'Kode user sudah digunakan',
                                'max' => 'Kode user maksimal 50 karakter'
                            ]),
                        TextInput::make('posisi')
                            ->label('Posisi')
                            ->string()
                            ->maxLength(50)
                            ->required()
                            ->validationMessages([
                                'required' => 'Posisi wajib diisi',
                                'max' => 'Posisi maksimal 50 karakter'
                            ]),
                        ComponentsSignaturePad::make('signature')
                            ->label(__('Sign here'))
                            ->dotSize(2.0)
                            ->lineMinWidth(0.5)
                            ->lineMaxWidth(2.5)
                            ->throttle(16)
                            ->minDistance(5)
                            ->velocityFilterWeight(0.7)
                            ->maxWidth(100),
                        Checkbox::make('status')
                            ->label('Status User (Aktif / Tidak Aktif)')
                            ->default(true)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')->sortable()->searchable(),
                TextColumn::make('first_name')
                    ->label('Nama Depan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Nama Belakang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Level')
                    ->sortable(),
                TextColumn::make('manage_type')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->label('Kelola'),
                ImageColumn::make('signature')
                    ->label('Tanda Tangan'),
                IconColumn::make('status')
                    ->boolean()
                    ->label('Status'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'test-notifications' => Pages\TestNotifications::route('/test-notifications'),
        ];
    }
}
