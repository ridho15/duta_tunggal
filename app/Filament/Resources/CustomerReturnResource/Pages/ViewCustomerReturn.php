<?php

namespace App\Filament\Resources\CustomerReturnResource\Pages;

use App\Filament\Resources\CustomerReturnResource;
use App\Models\CustomerReturn;
use App\Services\CustomerReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewCustomerReturn extends ViewRecord
{
    protected static string $resource = CustomerReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),

            // ── Workflow transition actions ───────────────────────────

            Actions\Action::make('mark_received')
                ->label('Tandai Diterima')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Penerimaan Barang')
                ->modalDescription('Konfirmasi bahwa barang return sudah diterima oleh tim DT?')
                ->visible(fn () => $this->record->status === CustomerReturn::STATUS_PENDING)
                ->action(function () {
                    $this->record->update([
                        'status'      => CustomerReturn::STATUS_RECEIVED,
                        'received_by' => Auth::id(),
                        'received_at' => now(),
                    ]);
                    $this->refreshFormData(['status']);
                    \Filament\Notifications\Notification::make()
                        ->title('Barang Return Diterima')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('start_qc')
                ->label('Mulai QC')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Mulai Inspeksi QC')
                ->visible(fn () => $this->record->status === CustomerReturn::STATUS_RECEIVED)
                ->action(function () {
                    $this->record->update([
                        'status'          => CustomerReturn::STATUS_QC_INSPECTION,
                        'qc_inspected_by' => Auth::id(),
                        'qc_inspected_at' => now(),
                    ]);
                    $this->refreshFormData(['status']);
                    \Filament\Notifications\Notification::make()
                        ->title('Inspeksi QC Dimulai')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('approve')
                ->label('Setujui Return')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui Customer Return')
                ->visible(fn () => $this->record->status === CustomerReturn::STATUS_QC_INSPECTION)
                ->action(function () {
                    $this->record->update([
                        'status'      => CustomerReturn::STATUS_APPROVED,
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);
                    $this->refreshFormData(['status']);
                    \Filament\Notifications\Notification::make()
                        ->title('Customer Return Disetujui')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reject')
                ->label('Tolak Return')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Tolak Customer Return')
                ->visible(fn () => $this->record->status === CustomerReturn::STATUS_QC_INSPECTION)
                ->action(function () {
                    $this->record->update([
                        'status'      => CustomerReturn::STATUS_REJECTED,
                        'rejected_by' => Auth::id(),
                        'rejected_at' => now(),
                    ]);
                    $this->refreshFormData(['status']);
                    \Filament\Notifications\Notification::make()
                        ->title('Customer Return Ditolak')
                        ->danger()
                        ->send();
                }),

            Actions\Action::make('complete')
                ->label('Selesaikan')
                ->icon('heroicon-o-flag')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Selesaikan Customer Return')
                ->modalDescription('Proses ini akan mengembalikan stok barang (keputusan Perbaikan/Penggantian) ke gudang penerima dan membuat jurnal akuntansi. Tidak dapat dibatalkan.')
                ->visible(fn () => $this->record->status === CustomerReturn::STATUS_APPROVED
                    && ! $this->record->stock_restored_at)
                ->action(function () {
                    try {
                        app(CustomerReturnService::class)->processCompletion($this->record);
                        $this->refreshFormData(['status', 'stock_restored_at', 'completed_at']);
                        \Filament\Notifications\Notification::make()
                            ->title('Customer Return Selesai')
                            ->body('Stok barang berhasil dikembalikan ke gudang.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal Menyelesaikan Return')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // ── PDF Print ─────────────────────────────────────────────
            Actions\Action::make('print_customer_return')
                ->label('Cetak Form Return')
                ->icon('heroicon-o-document-text')
                ->color('secondary')
                ->action(function () {
                    $record = $this->record->loadMissing([
                        'invoice',
                        'customer',
                        'cabang',
                        'customerReturnItems.product',
                        'receivedBy',
                        'qcInspectedBy',
                        'approvedBy',
                    ]);

                    $pdf = Pdf::loadView('pdf.customer-return', [
                        'return' => $record,
                    ])->setPaper('A4', 'portrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'CustomerReturn_' . $record->return_number . '.pdf');
                }),
        ];
    }
}
