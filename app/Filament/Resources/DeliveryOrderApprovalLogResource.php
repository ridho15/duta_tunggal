<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderApprovalLogResource\Pages;
use App\Filament\Resources\DeliveryOrderApprovalLogResource\RelationManagers;
use App\Models\DeliveryOrderApprovalLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryOrderApprovalLogResource extends Resource
{
    protected static ?string $model = DeliveryOrderApprovalLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Approval Logs';
    protected static ?string $modelLabel = 'Approval Log';
    protected static ?string $pluralModelLabel = 'Approval Logs';
    protected static ?string $navigationGroup = 'Delivery Order';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deliveryOrder.do_number')
                    ->label('DO Number')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                
                BadgeColumn::make('action')
                    ->label('Action')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'secondary' => 'cancelled',
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
                
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeliveryOrderApprovalLogs::route('/'),
        ];
    }
}
