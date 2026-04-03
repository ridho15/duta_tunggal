<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use App\Models\MaterialIssue;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewMaterialIssue extends ViewRecord
{
    protected static string $resource = MaterialIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil')
                ->visible(fn(MaterialIssue $record) => in_array($record->status, ['draft', 'pending_approval'])),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Actions\Action::make('request_approval')
                ->label(fn (MaterialIssue $record) => $record->requiresWarehouseConfirmation() ? 'Request Konfirmasi Gudang' : 'Request Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn(MaterialIssue $record) => $record->isDraft() && !$record->approved_by)
                ->requiresConfirmation()
                ->modalHeading(fn (MaterialIssue $record) => $record->requiresWarehouseConfirmation() ? 'Request Konfirmasi Gudang' : 'Request Approval Material Issue')
                ->modalDescription(fn (MaterialIssue $record) => $record->requiresWarehouseConfirmation()
                    ? 'Konfirmasi gudang per item bahan akan dibuat atau diperbarui. Material Issue akan otomatis di-approve jika semua item disetujui dan akan ditolak jika ada item yang ditolak.'
                    : 'Apakah Anda yakin ingin mengirim request approval untuk Material Issue ini?')
                ->action(function (MaterialIssue $record) {
                    // Validate stock before request approval
                    $stockValidation = $this->validateStockAvailability($record);
                    if (!$stockValidation['valid']) {
                        Notification::make()
                            ->title('Tidak Dapat Request Approval')
                            ->body($stockValidation['message'])
                            ->danger()
                            ->duration(10000)
                            ->send();
                        return;
                    }

                    if ($record->requiresWarehouseConfirmation()) {
                        $warehouseConfirmation = $record->ensureWarehouseConfirmationRequest();

                        if (! $warehouseConfirmation) {
                            Notification::make()
                                ->title('Konfirmasi Gudang Gagal Dibuat')
                                ->body('Manufacturing Order terkait tidak ditemukan sehingga konfirmasi gudang tidak dapat dibuat.')
                                ->danger()
                                ->send();
                            return;
                        }

                        if ($record->hasConfirmedWarehouseConfirmation()) {
                            $record->approveFromWarehouseConfirmation($record->latestWarehouseConfirmation() ?? $warehouseConfirmation);

                            Notification::make()
                                ->title('Material Issue Di-approve Otomatis')
                                ->body("Material Issue {$record->issue_number} langsung di-approve karena konfirmasi gudang sudah confirmed.")
                                ->success()
                                ->send();
                            return;
                        }

                        $record->update([
                            'approved_by' => null,
                            'approved_at' => null,
                            'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
                        ]);

                        Notification::make()
                            ->title('Request Konfirmasi Gudang Terkirim')
                            ->body("Konfirmasi gudang per item untuk Material Issue {$record->issue_number} telah dibuat atau diperbarui. Material Issue akan otomatis di-approve jika semua item disetujui atau ditolak jika ada item yang ditolak.")
                            ->success()
                            ->send();
                        return;
                    }

                    $currentUser = Auth::user();
                    if ($currentUser && $currentUser->hasRole('Super Admin')) {
                        // Super Admin bisa approve dari semua cabang
                        $warehouseApprover = \App\Models\User::whereHas('permissions', function ($query) {
                                $query->where('name', 'approve warehouse');
                            })
                            ->where('cabang_id', $record->warehouse->cabang_id ?? null)
                            ->first();
                        
                        // Jika tidak ada di cabang yang sama, ambil Super Admin sebagai approver
                        if (!$warehouseApprover) {
                            $warehouseApprover = $currentUser;
                        }
                    } else {
                        // User biasa harus di cabang yang sama
                        $warehouseApprover = \App\Models\User::where('cabang_id', $record->warehouse->cabang_id ?? null)
                            ->whereHas('permissions', function ($query) {
                                $query->where('name', 'approve warehouse');
                            })
                            ->first();
                    }

                    if ($warehouseApprover) {
                        $record->update([
                            'approved_by' => $warehouseApprover->id,
                            'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
                            // JANGAN set approved_at di sini, biarkan null sampai di-approve
                            // 'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Request Approval Terkirim')
                            ->body("Material Issue {$record->issue_number} telah dikirim untuk approval gudang.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Tidak Ada Approver Gudang')
                            ->body('Tidak ditemukan approver gudang untuk cabang ini.')
                            ->warning()
                            ->send();
                    }
                }),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(function (MaterialIssue $record) {
                    if ($record->requiresWarehouseConfirmation()) return false;

                    $currentUser = Auth::user();
                    if (!$currentUser) return false;

                    // Super Admin can approve all pending approval records
                    if ($currentUser->hasRole('Super Admin')) {
                        return $record->isPendingApproval();
                    }

                    // Users with 'approve warehouse' permission can approve if they are assigned or if no one is assigned
                    return $record->isPendingApproval() &&
                           userHasPermission('approve warehouse') &&
                           (!$record->approved_by || $record->approved_by === $currentUser->id);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve Material Issue')
                ->modalDescription('Setelah di-approve, Material Issue dapat diproses menjadi Completed.')
                ->action(function (MaterialIssue $record) {
                    if ($message = $record->warehouseConfirmationBlockingMessage()) {
                        Notification::make()
                            ->title('Konfirmasi Gudang Diperlukan')
                            ->body($message)
                            ->warning()
                            ->send();
                        return;
                    }

                    // Validate stock before approval
                    $stockValidation = $this->validateStockAvailability($record);
                    if (!$stockValidation['valid']) {
                        Notification::make()
                            ->title('Tidak Dapat Menyetujui Material Issue')
                            ->body($stockValidation['message'])
                            ->danger()
                            ->duration(10000)
                            ->send();
                        return;
                    }

                    $record->update([
                        'approved_at' => now(),
                        'approved_by' => Auth::id(), // Set Super Admin sebagai approver jika belum ada
                        'status' => MaterialIssue::STATUS_APPROVED,
                    ]);

                    Notification::make()
                        ->title('Material Issue Di-approve')
                        ->body("Material Issue {$record->issue_number} telah di-approve dan siap untuk diproses.")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function (MaterialIssue $record) {
                    if ($record->requiresWarehouseConfirmation()) return false;

                    $currentUser = Auth::user();
                    if (!$currentUser) return false;

                    // Super Admin can reject all pending approval records
                    if ($currentUser->hasRole('Super Admin')) {
                        return $record->isPendingApproval();
                    }

                    // Users with 'approve warehouse' permission can reject if they are assigned or if no one is assigned
                    return $record->isPendingApproval() &&
                           userHasPermission('approve warehouse') &&
                           (!$record->approved_by || $record->approved_by === $currentUser->id);
                })
                ->requiresConfirmation()
                ->modalHeading('Reject Material Issue')
                ->modalDescription('Berikan alasan penolakan:')
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (MaterialIssue $record, array $data) {
                    $record->update([
                        'approved_by' => null,
                        'approved_at' => null,
                        'status' => MaterialIssue::STATUS_DRAFT,
                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                  "DITOLAK: {$data['rejection_reason']} - " . now()->format('Y-m-d H:i:s'),
                    ]);

                    Notification::make()
                        ->title('Material Issue Ditolak')
                        ->body("Material Issue {$record->issue_number} telah ditolak.")
                        ->warning()
                        ->send();
                }),
        ];
    }

    /**
     * Validate stock availability for material issue items
     */
    protected function validateStockAvailability(MaterialIssue $materialIssue): array
    {
        $stockReservationService = app(\App\Services\StockReservationService::class);
        return $stockReservationService->checkStockAvailabilityForMaterialIssue($materialIssue);
    }
}
