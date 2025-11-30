<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;

class TestNotifications extends Page
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Test Notifications';

    protected static string $view = 'filament.resources.user-resource.pages.test-notifications';

    protected function getActions(): array
    {
        return [
            Actions\Action::make('test_notification')
                ->label('Test Notification')
                ->action(function () {
                    Notification::make()
                        ->title('Test Notification dengan Icon')
                        ->body('Ini adalah test notification dengan icon bell')
                        ->icon('heroicon-o-bell')
                        ->iconColor('success')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('Lihat')
                                ->url('/admin')
                        ])
                        ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                }),
        ];
    }
}
