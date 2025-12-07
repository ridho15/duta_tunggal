<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\PurchaseReturnService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn($record) => $record->status === 'draft')
                ->action(function ($record) {
                    $service = app(PurchaseReturnService::class);
                    $service->submitForApproval($record);
                    \Filament\Notifications\Notification::make()
                        ->title('Purchase Return submitted for approval')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->status === 'pending_approval')
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Approval Notes')
                        ->nullable(),
                ])
                ->action(function ($record, array $data) {
                    $service = app(PurchaseReturnService::class);
                    $service->approve($record, $data);
                    \Filament\Notifications\Notification::make()
                        ->title('Purchase Return approved')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->status === 'pending_approval')
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_notes')
                        ->label('Rejection Notes')
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $service = app(PurchaseReturnService::class);
                    $service->reject($record, $data);
                    \Filament\Notifications\Notification::make()
                        ->title('Purchase Return rejected')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
