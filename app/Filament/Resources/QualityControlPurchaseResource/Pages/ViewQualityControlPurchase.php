<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseReturn;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewQualityControlPurchase extends ViewRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('Complete')
                ->color('success')
                ->label('Complete QC')
                ->icon('heroicon-o-check-badge')
                ->hidden(function ($record) {
                    return $record->status == 1 || ((float)($record->passed_quantity ?? 0) <= 0 && (float)($record->rejected_quantity ?? 0) <= 0);
                })
                ->modalHeading('Selesaikan Quality Control')
                ->modalDescription(function ($record) {
                    if ($record->items()->exists()) {
                        $itemCount = $record->items()->count();
                        $passed = number_format((float) ($record->passed_quantity ?? 0), 0, ',', '.');
                        $rejected = number_format((float) ($record->rejected_quantity ?? 0), 0, ',', '.');
                        return "Multi-Item QC ({$itemCount} item) | Total Lulus: {$passed} | Total Ditolak: {$rejected}.";
                    }

                    $passed = number_format((float) ($record->passed_quantity ?? 0), 0, ',', '.');
                    $rejected = number_format((float) ($record->rejected_quantity ?? 0), 0, ',', '.');
                    $prodName = optional($record->product)->name ?? 'Produk';
                    return "Item: {$prodName} | Qty Lulus: {$passed} | Qty Ditolak: {$rejected}.";
                })
                ->form(function ($record) {
                    if ($record->items()->exists()) {
                        return [];
                    }

                    if ((float) ($record->rejected_quantity ?? 0) <= 0) {
                        return [];
                    }

                    return [
                        Radio::make('failed_qc_action')
                            ->label('Tindak Lanjut Barang Ditolak (Rejected)')
                            ->options([
                                PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY => 'Tunggu Pengganti (PO tetap terbuka untuk pengiriman ulang supplier)',
                                PurchaseReturn::QC_ACTION_RETURN_SUPPLIER    => 'Retur ke Supplier (Buat dokumen nota retur ke supplier)',
                                PurchaseReturn::QC_ACTION_REDUCE_STOCK       => 'Batalkan Sisa PO (Kurangi kuantitas PO & tutup sesuai jumlah yang diterima)',
                            ])
                            ->default(PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY)
                            ->required()
                            ->helperText('Tentukan tindakan untuk barang yang tidak lolos QC agar PO tidak menggantung selamanya.'),
                    ];
                })
                ->modalSubmitActionLabel('Selesaikan QC')
                ->action(function ($record, array $data) {
                    try {
                        $qualityControlService = app(QualityControlService::class);

                        if ($record->items()->exists()) {
                            $qualityControlService->completeQualityControl($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "QC Selesai", message: "Multi-Item Quality Control Selesai dan Penerimaan Barang (GRN) telah diterbitkan.");
                            return;
                        }

                        $purchaseReturnService = app(PurchaseReturnService::class);

                        if ((float) ($record->rejected_quantity ?? 0) > 0 && ! empty($data['failed_qc_action'])) {
                            $action = $data['failed_qc_action'];

                            $purchaseReturn = $purchaseReturnService->createFromQualityControl($record, $action);

                            if ($action === PurchaseReturn::QC_ACTION_REDUCE_STOCK) {
                                $purchaseReturnService->executeQcResolution($purchaseReturn);
                            }
                        }

                        $qualityControlService->completeQualityControl($record, $data);

                        if ($record->from_model_type === 'App\Models\PurchaseReceiptItem') {
                            $qualityControlService->checkPenerimaanBarang($record);
                        }

                        $msg = "Quality Control Purchase Completed.";
                        if ((float) ($record->rejected_quantity ?? 0) > 0) {
                            $labels = [
                                PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY => 'PO tetap terbuka menunggu pengganti supplier.',
                                PurchaseReturn::QC_ACTION_RETURN_SUPPLIER    => 'Dokumen retur telah dibuat untuk pengembalian ke supplier.',
                                PurchaseReturn::QC_ACTION_REDUCE_STOCK       => 'Kuantitas PO telah disesuaikan dengan jumlah diterima.',
                            ];
                            $actionLabel = $labels[$data['failed_qc_action'] ?? ''] ?? '';
                            $msg .= " Tindak lanjut reject: {$actionLabel}";
                        }

                        HelperController::sendNotification(isSuccess: true, title: "QC Selesai", message: $msg);
                    } catch (Throwable $exception) {
                        Log::error('ViewQualityControlPurchase complete action failed', [
                            'quality_control_id' => $record->id,
                            'error' => $exception->getMessage(),
                        ]);

                        ProcurementFailureNotifier::danger(
                            'Gagal Menyelesaikan QC Pembelian',
                            $exception,
                            'QC pembelian belum berhasil diselesaikan. Periksa hasil QC, gudang return, dan data item yang diproses lalu coba lagi.'
                        );
                    }
                })
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate product information fields
        $data['product_name'] = $this->record->product?->name ?? ($this->record->items()->exists() ? 'Multi-Item Purchase Order' : '');
        $data['sku'] = $this->record->product?->sku ?? ($this->record->items()->exists() ? '-' : '');
        $data['quantity_received'] = $this->record->quantity_received ?? 0;
        $data['uom'] = $this->record->product?->uom?->name ?? ($this->record->items()->exists() ? 'item(s)' : '');
        return $data;
    }
}