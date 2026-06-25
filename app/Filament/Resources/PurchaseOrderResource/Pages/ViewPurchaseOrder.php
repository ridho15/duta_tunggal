<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Support\CurrencyConversionResolver;
use App\Support\ProcurementFailureNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            // Setujui PO dari status draft; juga update fulfilled_quantity di Order Request terkait
            Action::make('approve_po')
                ->label('Setujui PO')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => Auth::user()->can('response purchase order') && $record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Setujui Purchase Order')
                ->modalDescription('Apakah Anda yakin ingin menyetujui PO ini? Kuantitas pada Order Request terkait akan berkurang sesuai qty PO saat ini.')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->action(function ($record) {
                    try {
                        app(\App\Services\PurchaseOrderService::class)->approvePo($record);
                        Notification::make()
                            ->title('Purchase Order Disetujui')
                            ->body('PO ' . $record->po_number . ' berhasil disetujui.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('ViewPurchaseOrder approve_po failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Gagal Menyetujui PO')
                            ->body(ProcurementFailureNotifier::message($e, 'Purchase order belum dapat disetujui. Silakan coba lagi.'))
                            ->danger()
                            ->send();
                    }
                }),

            // Langkah berikutnya setelah PO: buat Quality Control Purchase
            Action::make('buat_qc')
                ->label('Buat Quality Control')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->color('warning')
                ->url(fn ($record) => '/admin/quality-control-purchases/create?purchase_order_id=' . $record->id)
                ->visible(fn ($record) => in_array($record->status, ['approved', 'partially_received'])),

            Action::make('complete')
                ->label('Complete Purchase Order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Complete Purchase Order')
                ->modalDescription('Are you sure you want to mark this Purchase Order as completed? This action will finalize all receipts and update the PO status.')
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('update purchase order') && $record->canBeCompleted();
                })
                ->action(function ($record) {
                    try {
                        $record->manualComplete(Auth::id());
                        
                        Notification::make()
                            ->title('Purchase Order Completed')
                            ->body('PO ' . $record->po_number . ' has been successfully completed.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('ViewPurchaseOrder complete failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'user_id' => Auth::id(),
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Failed to Complete Purchase Order')
                            ->body(ProcurementFailureNotifier::message($e, 'Purchase order belum dapat diselesaikan. Silakan coba lagi.'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->visible(function ($record) {
                    // Only allow confirmation for request_close (approval flow removed; OR handles approvals)
                    return Auth::user()->hasPermissionTo('response purchase order') && ($record->status == 'request_close');
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->modalHeading('Konfirmasi Purchase Order')
                ->modalWidth('lg')
                ->form(function ($record) {
                    // Only support request_close confirmation via this action
                    if ($record->status == 'request_close') {
                        return [
                            Textarea::make('close_reason')
                                ->label('Close Reason')
                                ->required()
                                ->string()
                        ];
                    }

                    return null;
                })
                ->action(function (array $data, $record) {
                    try {
                        if ($record->status == 'request_close') {
                            $record->update([
                                'close_reason' => $data['close_reason'],
                                'status' => 'closed',
                                'closed_at' => Carbon::now(),
                                'closed_by' => Auth::user()->id,
                            ]);
                        }
                        Notification::make()
                            ->title('Purchase Order Dikonfirmasi')
                            ->body('Status Purchase Order berhasil diperbarui.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('ViewPurchaseOrder konfirmasi failed', [
                            'purchase_order_id' => $record->id,
                            'po_number' => $record->po_number,
                            'status' => $record->status,
                            'user_id' => Auth::id(),
                            'payload' => $data,
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Gagal Mengkonfirmasi')
                            ->body(ProcurementFailureNotifier::message($e, 'Status purchase order belum dapat diperbarui. Silakan coba lagi.'))
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
                            ->body('PO ' . $record->po_number . ' dikembalikan ke status Draft.')
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('ViewPurchaseOrder tolak failed', [
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
                            ->body('PO ' . $record->po_number . ' menunggu konfirmasi penutupan.')
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('ViewPurchaseOrder request_close failed', [
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
                ->label('Preview PDF')
                ->icon('heroicon-o-document-check')
                ->color('gray')
                ->visible(fn ($record) => $record->status !== 'draft' && $record->status !== 'closed')
                ->url(fn ($record) => route('pdf-stream', ['type' => 'purchase-order', 'id' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $total = 0;

        if ($record) {
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

            $record->loadMissing('purchaseOrderCurrency');
            $poCurrencies = $record->purchaseOrderCurrency->keyBy('currency_id');

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
}
