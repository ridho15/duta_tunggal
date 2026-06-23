<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions\EditAction;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['permissions', 'users']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Informasi Role')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Nama Role'),
                                Infolists\Components\TextEntry::make('guard_name')
                                    ->label('Guard'),
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Jumlah User')
                                    ->state(fn () => $this->record->users->count()),
                                Infolists\Components\TextEntry::make('permissions_count')
                                    ->label('Jumlah Permission')
                                    ->state(fn () => $this->record->permissions->count()),
                            ]),
                    ]),

                Infolists\Components\Section::make('Hak Akses Role')
                    ->schema([
                        Infolists\Components\ViewEntry::make('permission_summary')
                            ->label('')
                            ->view('filament.infolists.role-permission-summary')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}