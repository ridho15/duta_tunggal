<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    // Rename Roles group to the requested label
    protected static ?string $navigationGroup = 'User Roles Management';

    // Put roles near the end as requested
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'Nama role wajib diisi',
                        'max' => 'Nama role maksimal 255 karakter'
                    ]),
                Select::make('id_user')
                    ->label('User')
                    ->searchable()
                    ->preload()
                    ->relationship('users', 'name')
                    ->multiple(),
                Select::make('permissions')
                    ->label('Permissions')
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->relationship('permissions', 'name')
                    ->multiple(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('guard_name')
                    ->searchable(),
                TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->searchable(),
                TextColumn::make('users.name')
                    ->label('Users')
                    ->badge()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
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
            'index' => Pages\ListRoles::route('/'),
            // 'create' => Pages\CreateRole::route('/create'),
            // 'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
