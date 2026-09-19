<?php

namespace App\Filament\Resources\PaymentRequestResource\Pages;

use App\Filament\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPaymentRequest extends EditRecord
{
    protected static string $resource = PaymentRequestResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->record->status !== PaymentRequest::STATUS_DRAFT) {
            Notification::make()
                ->title('Payment Request Terkunci')
                ->body('Payment Request dengan status ' . ($this->record->status_label ?? $this->record->status) . ' tidak dapat diubah.')
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === PaymentRequest::STATUS_DRAFT),
        ];
    }
}
