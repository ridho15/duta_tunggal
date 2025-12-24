<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RakResource\Pages;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\RakService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RakResource extends Resource
{
    protected static ?string $model = Rak::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 7;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->label('Kode Rak')
                    ->maxLength(255)
                    ->suffixAction(Action::make('generateCode')
                        ->icon('heroicon-m-arrow-path')
                        ->tooltip('Generate Kode Rak')
                        ->action(function ($set, $get, $state) {
                            $rakService = app(RakService::class);
                            $warehouseId = $get('warehouse_id');
                            $set('code', $rakService->generateKodeRak($warehouseId));
                        })),
                Select::make('warehouse_id')
                    ->label('Gudang')
                    ->options(function () {
                        $user = Auth::user();
                        $manageType = $user?->manage_type ?? [];
                        if ($user && is_array($manageType) && in_array('all', $manageType)) {
                            // User dengan akses semua bisa lihat semua warehouse
                            return Warehouse::all()->mapWithKeys(function ($warehouse) {
                                return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                            });
                        } else {
                            // User biasa hanya lihat warehouse di cabang mereka
                            return Warehouse::where('cabang_id', $user?->cabang_id)->get()->mapWithKeys(function ($warehouse) {
                                return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kode Rak')
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
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('warehouse', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
            ])
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
            'index' => Pages\ListRaks::route('/'),
            // 'create' => Pages\CreateRak::route('/create'),
            // 'edit' => Pages\EditRak::route('/{record}/edit'),
        ];
    }
}
