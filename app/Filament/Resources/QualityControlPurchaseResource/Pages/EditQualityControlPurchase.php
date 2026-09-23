<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditQualityControlPurchase extends EditRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    /**
     * FIX #8: Blokir akses halaman edit jika QC sudah selesai diproses.
     * Mencegah bypass via URL langsung (mis. /admin/quality-control-purchases/5/edit).
     */
    public function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if ((int) ($this->getRecord()->status ?? 0) === 1) {
            \Filament\Notifications\Notification::make()
                ->title('QC Pembelian sudah selesai')
                ->body('Quality Control yang sudah selesai diproses tidak dapat diubah.')
                ->danger()
                ->send();

            $this->redirect(QualityControlPurchaseResource::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => (int) ($this->record->status ?? 0) !== 1),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['inspected_by'] = $this->record->inspected_by;

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['product_name'] = $this->record->product->name ?? '';
        $data['sku'] = $this->record->product->sku ?? '';
        $data['quantity_received'] = $this->record->quantity_received ?? 0;
        $data['uom'] = $this->record->product->uom->name ?? '';
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EditQualityControlPurchase handleRecordUpdate failed', [
                'quality_control_id' => $record->id,
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Memperbarui QC Pembelian',
                $exception,
                'Perubahan QC pembelian belum berhasil disimpan. Periksa kembali hasil inspeksi dan data gudang lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
