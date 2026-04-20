<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['permissions', 'roles.permissions', 'cabang', 'warehouse']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Informasi User')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('username')
                                    ->label('Username'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email'),
                                Infolists\Components\TextEntry::make('first_name')
                                    ->label('Nama Depan'),
                                Infolists\Components\TextEntry::make('last_name')
                                    ->label('Nama Belakang')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('telepon')
                                    ->label('Telepon')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('kode_user')
                                    ->label('Kode User'),
                                Infolists\Components\TextEntry::make('posisi')
                                    ->label('Posisi'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => (bool) $state ? 'Aktif' : 'Tidak Aktif')
                                    ->color(fn ($state): string => (bool) $state ? 'success' : 'danger'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Akses dan Penugasan')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('level')
                                    ->label('Level')
                                    ->state(fn () => $this->record->getRoleNames()->values()->implode(', ') ?: '-'),
                                Infolists\Components\TextEntry::make('manage_type')
                                    ->label('Kelola')
                                    ->state(fn () => collect($this->record->manage_type ?? [])
                                        ->map(fn (string $value) => match ($value) {
                                            'all' => 'Semua Cabang / Gudang',
                                            'cabang' => 'Cabang',
                                            'warehouse' => 'Gudang',
                                            default => Str::headline($value),
                                        })
                                        ->filter()
                                        ->values()
                                        ->implode(', ') ?: '-')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('cabang_id')
                                    ->label('Cabang')
                                    ->state(fn () => $this->record->cabang?->kode && $this->record->cabang?->nama
                                        ? '(' . $this->record->cabang->kode . ') ' . $this->record->cabang->nama
                                        : '-')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('warehouse_id')
                                    ->label('Gudang')
                                    ->state(fn () => $this->record->warehouse?->kode && $this->record->warehouse?->name
                                        ? '(' . $this->record->warehouse->kode . ') ' . $this->record->warehouse->name
                                        : '-')
                                    ->placeholder('-'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Kemampuan Berdasarkan Permission')
                    ->schema([
                        Infolists\Components\ViewEntry::make('permission_summary')
                            ->label('')
                            ->view('filament.infolists.user-permission-summary')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // $data['manage_type'] is already an array due to accessor
        return $data;
    }

    public function getPermissionRows(): array
    {
        $permissionDescriptions = HelperController::permissionDescriptions();
        $permissionModules = collect(HelperController::listPermission())
            ->flatMap(function (array $actions, string $module): array {
                return collect($actions)
                    ->mapWithKeys(fn (string $action): array => [trim($action . ' ' . $module) => $module])
                    ->all();
            })
            ->all();

        $directPermissionNames = $this->record->permissions->pluck('name')->all();

        return $this->record->getAllPermissions()
            ->sortBy('name')
            ->values()
            ->map(function ($permission) use ($permissionDescriptions, $permissionModules, $directPermissionNames): array {
                $name = $permission->name;

                return [
                    'name' => $name,
                    'module' => $permissionModules[$name] ?? '-',
                    'description' => $permission->description
                        ?: ($permissionDescriptions[$name] ?? Str::headline($name)),
                    'source' => in_array($name, $directPermissionNames, true) ? 'Langsung' : 'Dari Role',
                ];
            })
            ->all();
    }
}
