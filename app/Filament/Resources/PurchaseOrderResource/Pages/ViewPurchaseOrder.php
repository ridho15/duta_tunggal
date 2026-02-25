<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use App\Models\Asset;
use App\Models\ChartOfAccount;
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

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),

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
                        Notification::make()
                            ->title('Failed to Complete Purchase Order')
                            ->body($e->getMessage())
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
                    if ($record->status == 'request_close') {
                        $record->update([
                            'close_reason' => $data['close_reason'],
                            'status' => 'closed',
                            'closed_at' => Carbon::now(),
                            'closed_by' => Auth::user()->id,
                        ]);
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
                    $record->update([
                        'status' => 'draft'
                    ]);
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
                    $record->update([
                        'status' => 'request_close'
                    ]);
                }),
            Action::make('cetak_pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-document-check')
                ->color('danger')
                ->visible(function ($record) {
                    return $record->status != 'draft' && $record->status != 'closed';
                })
                ->action(function ($record) {
                    $record->load(['assets.assetCoa', 'assets.accumulatedDepreciationCoa', 'assets.depreciationExpenseCoa']);
                    $pdf = Pdf::loadView('pdf.purchase-order', [
                        'purchaseOrder' => $record
                    ])->setPaper('A4', 'potrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Pembelian_' . $record->po_number . '.pdf');
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $total = 0;

        if ($record) {
            foreach ($record->purchaseOrderItem as $item) {
                $total += HelperController::hitungSubtotal((int)$item->quantity, (int)$item->unit_price, (int)$item->discount, (int)$item->tax, $item->tipe_pajak);
            }

            foreach ($record->purchaseOrderBiaya as $biaya) {
                $biayaAmount = $biaya->total * ($biaya->currency->to_rupiah ?? 1);
                $total += $biayaAmount;
            }
        }

        $data['total_amount'] = $total;
        return $data;
    }
}
