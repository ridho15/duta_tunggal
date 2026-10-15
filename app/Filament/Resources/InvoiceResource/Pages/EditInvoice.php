<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    private function isDraft($record): bool
    {
        return strtolower((string) $record->status) === \App\Models\Invoice::STATUS_DRAFT;
    }

    /**
     * Invoice non-draft (sudah diposting) terkunci mutlak demi integritas jurnal; mencegah bypass via URL langsung.
     * Koreksi finansial lewat Credit Note.
     */
    public function authorizeAccess(): void
    {
        $record = $this->getRecord();

        if (! $this->isDraft($record)) {
            \Filament\Notifications\Notification::make()
                ->title('Invoice tidak dapat diedit')
                ->body('Invoice dengan status "' . (\App\Models\Invoice::STATUS_LABELS[$record->status] ?? $record->status) . '" telah diposting dan terkunci demi integritas jurnal. Koreksi finansial harus dilakukan melalui Credit Note.')
                ->danger()
                ->send();

            $this->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
            return;
        }

        parent::authorizeAccess();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->isDraft($this->record)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Invoice yang sudah diposting terkunci dan tidak dapat diedit. Gunakan Credit Note untuk koreksi.');
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => $this->isDraft($this->record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Guard against other_fee stored as integer 0 in DB (non-JSON value)
        // When DB stores 0, the Repeater must receive [] not int(0)
        $rawOtherFee = $this->record->getAttributes()['other_fee'] ?? null;
        $decoded = ($rawOtherFee !== null && $rawOtherFee !== '') ? @json_decode($rawOtherFee, true) : null;
        $data['other_fee'] = is_array($decoded) ? $decoded : [];

        return $data;
    }
}
