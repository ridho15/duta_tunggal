<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\PurchaseOrderService;
use App\Support\ProcurementFailureNotifier;
use App\Support\CurrencyConversionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Ubah Pembelian';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function ($record) {
                    try {
                        $record->update([
                            'status' => 'approved',
                            'date_approved' => Carbon::now(),
                            'approved_by' => Auth::user()->id,
                        ]);
                        Notification::make()
                            ->title('Purchase Order Dikonfirmasi')
                            ->body('PO ' . $record->po_number . ' berhasil disetujui.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('EditPurchaseOrder konfirmasi failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'status' => $record->status,
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Gagal Mengkonfirmasi PO')
                            ->body(ProcurementFailureNotifier::message($e, 'Purchase order belum dapat dikonfirmasi. Silakan coba lagi.'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('tolak')
                ->label('Tolak')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function ($record) {
                    try {
                        $record->update([
                            'status' => 'draft'
                        ]);
                        Notification::make()
                            ->title('Purchase Order Ditolak')
                            ->body('PO dikembalikan ke status Draft.')
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('EditPurchaseOrder tolak failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'status' => $record->status,
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Gagal Menolak PO')
                            ->body(ProcurementFailureNotifier::message($e, 'Purchase order belum dapat dikembalikan ke draft. Silakan coba lagi.'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('request_close')
                ->label('Request Close')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_close', 'closed', 'completed', 'approved']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function ($record) {
                    try {
                        $record->update([
                            'status' => 'request_close'
                        ]);
                        Notification::make()
                            ->title('Permintaan Penutupan Diajukan')
                            ->body('Permintaan penutupan PO menunggu konfirmasi Manager.')
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('EditPurchaseOrder request_close failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'status' => $record->status,
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Gagal Request Close')
                            ->body(ProcurementFailureNotifier::message($e, 'Permintaan penutupan purchase order belum berhasil diajukan. Silakan coba lagi.'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('cetak_pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-document-check')
                ->color('danger')
                ->visible(function ($record) {
                    return $record->status != 'draft' && $record->status != 'closed';
                })
                ->action(function ($record) {
                    $pdf = Pdf::loadView('pdf.purchase-order', [
                        'purchaseOrder' => $record
                    ])->setPaper('A4', 'portrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Pembelian_' . $record->po_number . '.pdf');
                }),
        ];
    }

    protected function afterSave()
    {
        try {
            $purchaseOrderService = app(PurchaseOrderService::class);
            $purchaseOrderService->updateTotalAmount($this->getRecord());
        } catch (Throwable $exception) {
            Log::error('EditPurchaseOrder afterSave failed', [
                'purchase_order_id' => $this->getRecord()?->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::warning(
                'Purchase Order Tersimpan Dengan Catatan',
                $exception,
                'Perubahan purchase order berhasil disimpan, tetapi total belum berhasil disinkronkan. Periksa kembali data totalnya.'
            );
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EditPurchaseOrder handleRecordUpdate failed', [
                'purchase_order_id' => $record->id,
                'po_number' => $record->po_number,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Memperbarui Purchase Order',
                $exception,
                'Perubahan purchase order belum berhasil disimpan. Periksa kembali data pembelian lalu coba lagi.'
            );

            throw $exception;
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $total = 0;

        if ($record) {
            $record->loadMissing('purchaseOrderCurrency');
            $poCurrencies = $record->purchaseOrderCurrency->keyBy('currency_id');

            if (! empty($data['purchaseOrderItem']) && is_array($data['purchaseOrderItem'])) {
                foreach ($data['purchaseOrderItem'] as &$item) {
                    $item['tipe_pajak'] = \App\Filament\Resources\PurchaseOrderResource::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
                    $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
                    $preview = \App\Filament\Resources\PurchaseOrderResource::calculateCurrencyPreview(
                        (float) ($item['quantity'] ?? 0),
                        (float) ($item['unit_price'] ?? 0),
                        (float) ($item['discount'] ?? 0),
                        (float) ($item['tax'] ?? 0),
                        $item['tipe_pajak'],
                        $currencyId
                    );
                    $item['subtotal'] = \App\Filament\Resources\PurchaseOrderResource::formatCurrencyPreviewState($preview['subtotal'], $currencyId);
                }
                unset($item);
            }

            foreach ($record->purchaseOrderItem as $item) {
                $subtotal = HelperController::hitungSubtotal(
                    (float) $item->quantity,
                    (float) $item->unit_price,
                    (float) $item->discount,
                    (float) $item->tax,
                    $item->tipe_pajak
                );
                $poCurrency = $poCurrencies->get($item->currency_id);
                $rate = ($poCurrency && (float) $poCurrency->nominal > 0)
                    ? (float) $poCurrency->nominal
                    : CurrencyConversionResolver::resolveRate(is_numeric($item->currency_id) ? (int) $item->currency_id : null);

                $total += $subtotal * $rate;
            }

            foreach ($record->purchaseOrderBiaya as $biaya) {
                $poCurrency = $poCurrencies->get($biaya->currency_id);
                $rate = ($poCurrency && (float) $poCurrency->nominal > 0)
                    ? (float) $poCurrency->nominal
                    : CurrencyConversionResolver::resolveRate(is_numeric($biaya->currency_id) ? (int) $biaya->currency_id : null);
                $biayaAmount = (float) $biaya->total * $rate;
                $total += $biayaAmount;
            }
        }

        $data['total_amount'] = $total;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PurchaseOrderResource::syncPurchaseOrderCurrencyData($data);
    }
}
