<?php

namespace App\Filament\Resources\DepositAdjustmentResource\Pages;

use App\Filament\Resources\DepositAdjustmentResource;
use App\Http\Controllers\HelperController;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDepositAdjustment extends CreateRecord
{
    protected static string $resource = DepositAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-calculate remaining amount
        $data['remaining_amount'] = $data['amount'] - ($data['used_amount'] ?? 0);
        $data['created_by'] = Auth::id();
        $data['status'] = 'active';

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create deposit log entry
        $this->record->depositLogRef()->create([
            'deposit_id' => $this->record->id,
            'type' => 'create',
            'amount' => $this->record->amount,
            'note' => 'Initial deposit created by Finance: ' . ($this->record->note ?? 'No additional notes'),
            'created_by' => Auth::id()
        ]);

        HelperController::sendNotification(
            isSuccess: true, 
            title: 'Success', 
            message: "Deposit successfully created for " . $this->record->fromModel->name
        );
    }

    public function getTitle(): string
    {
        return 'Create New Deposit (Finance)';
    }
}
