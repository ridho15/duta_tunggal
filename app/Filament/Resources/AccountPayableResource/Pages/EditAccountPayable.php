<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountPayable extends EditRecord
{
    protected static string $resource = AccountPayableResource::class;

    protected function authorizeAccess(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Akses Ditolak')
            ->body('Buku pembantu Utang Usaha tidak dapat diubah secara manual.')
            ->danger()
            ->send();

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
