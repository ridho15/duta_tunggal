<?php

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Services\DepositNumberGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDeposit extends EditRecord
{
    protected static string $resource = DepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('regenerate_deposit_number')
                ->label('Regenerate Nomor')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    try {
                        $number = app(DepositNumberGenerator::class)->generate();
                        $this->record->deposit_number = $number;
                        $this->record->save();

                        Notification::make()
                            ->title('Nomor deposit diubah: ' . $number)
                            ->success()
                            ->send();

                        // Refill the form so updated deposit_number appears
                        $this->form->fill($this->record->toArray());
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->getCode() == 23000) { // Integrity constraint violation
                            Notification::make()
                                ->title('Gagal regenerate nomor deposit')
                                ->body('Nomor deposit sudah digunakan. Silakan coba lagi.')
                                ->danger()
                                ->send();
                        } else {
                            throw $e;
                        }
                    }
                }),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert status string to boolean for form display
        $data['status'] = $data['status'] === 'active';
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-calculate remaining amount
        $data['remaining_amount'] = $data['amount'] - ($data['used_amount'] ?? 0);
        
        // Convert status boolean to string for database
        $data['status'] = $data['status'] ? 'active' : 'closed';

        return $data;
    }

    protected function afterSave(): void
    {
        // Log the edit
        $this->record->depositLogRef()->create([
            'deposit_id' => $this->record->id,
            'type' => 'edit',
            'amount' => 0, // No amount change, just edit
            'note' => 'Deposit information updated: ' . ($this->record->note ?? 'No additional notes'),
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
        return 'Edit Deposit';
    }
}
