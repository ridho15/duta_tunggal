<?php

namespace App\Filament\Resources\DepositAdjustmentResource\Pages;

use App\Filament\Resources\DepositAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class ViewDepositAdjustment extends ViewRecord
{
    protected static string $resource = DepositAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Deposit Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('fromModel')
                                    ->label('Entity')
                                    ->formatStateUsing(function ($state) {
                                        return "({$state->code}) {$state->name}";
                                    }),
                                    
                                TextEntry::make('from_model_type')
                                    ->label('Entity Type')
                                    ->formatStateUsing(function ($state) {
                                        return $state == 'App\Models\Supplier' ? 'Supplier' : 'Customer';
                                    }),
                            ]),
                            
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Total Deposit')
                                    ->money('idr'),
                                    
                                TextEntry::make('used_amount')
                                    ->label('Used Amount')
                                    ->money('idr'),
                                    
                                TextEntry::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->money('idr'),
                            ]),
                            
                        TextEntry::make('coa')
                            ->label('Chart of Account')
                            ->formatStateUsing(function ($state) {
                                return "({$state->code}) {$state->name}";
                            }),
                            
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'closed' => 'danger',
                            }),
                            
                        TextEntry::make('note')
                            ->label('Notes')
                            ->markdown(),
                    ]),
                    
                Section::make('Audit Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('createdBy.name')
                                    ->label('Created By'),
                                    
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                    
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public function getTitle(): string
    {
        return 'View Deposit (Finance)';
    }
}
