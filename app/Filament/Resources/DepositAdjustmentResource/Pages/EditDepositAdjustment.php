<?php

namespace App\Filament\Resources\DepositAdjustmentResource\Pages;

use App\Filament\Resources\DepositAdjustmentResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDepositAdjustment extends EditRecord
{
    protected static string $resource = DepositAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-calculate remaining amount
        $data['remaining_amount'] = $data['amount'] - ($data['used_amount'] ?? 0);

        return $data;
    }

    protected function afterSave(): void
    {
        // Log the edit
        $this->record->depositLogRef()->create([
            'deposit_id' => $this->record->id,
            'type' => 'edit',
            'amount' => 0,
            'note' => 'Deposit edited by Finance: ' . ($this->record->note ?? 'No additional notes'),
            'created_by' => Auth::id()
        ]);

        HelperController::sendNotification(
            isSuccess: true, 
            title: 'Success', 
            message: "Deposit successfully updated"
        );
    }

    public function getTitle(): string
    {
        return 'Edit Deposit (Finance)';
    }
}
