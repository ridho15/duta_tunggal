<?php

namespace App\Filament\Resources\DeliveryOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ApprovalLogsRelationManager extends RelationManager
{
    // Use the existing delivery order logs table for approval history
    protected static string $relationship = 'log';
    
    protected static ?string $title = 'Approval History';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('confirmedBy.name')
                    ->label('User')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Action')
                    ->colors([
                        'warning' => 'request_stock',
                        'primary' => 'request_approve',
                        'success' => 'approved',
                        'danger' => 'reject',
                    ])
                    ->sortable(),
                
                TextColumn::make('comments')
                    ->label('Comments')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
                
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
