<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\Auth;

class ViewMaterialIssue extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MaterialIssueResource::class;
    protected static string $view = 'filament.resources.material-issue-resource.pages.view-material-issue';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['items.product', 'items.warehouse', 'items.rak', 'warehouse', 'productionPlan', 'manufacturingOrder', 'journalEntries.coa']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Informasi Material Issue')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('issue_number')
                                    ->label('Nomor Issue'),
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Tipe')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'issue' => 'Ambil Barang',
                                        'return' => 'Retur Barang',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'pending_approval' => 'warning',
                                        'approved' => 'info',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('issue_date')
                                    ->label('Tanggal')
                                    ->date('d/m/Y'),
                            ]),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Gudang')
                            ->state(fn () => $this->record->warehouse ? '(' . $this->record->warehouse->kode . ') ' . $this->record->warehouse->name : '-'),
                        Infolists\Components\TextEntry::make('production_plan_display')
                            ->label('Rencana Produksi')
                            ->state(fn () => $this->record->productionPlan ? $this->record->productionPlan->plan_number . ' - ' . $this->record->productionPlan->name : '-'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Jurnal Hasil')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('journal_count')
                                    ->label('Baris Jurnal')
                                    ->state(fn () => $this->record->journalEntries()->count()),
                                Infolists\Components\TextEntry::make('journal_total_debit')
                                    ->label('Total Debit')
                                    ->state(fn () => $this->record->journalEntries()->sum('debit'))
                                    ->rupiah(),
                                Infolists\Components\TextEntry::make('journal_total_credit')
                                    ->label('Total Credit')
                                    ->state(fn () => $this->record->journalEntries()->sum('credit'))
                                    ->rupiah(),
                            ]),

                        Infolists\Components\TextEntry::make('journal_balance')
                            ->label('Selisih')
                            ->state(fn () => $this->record->journalEntries()->sum('debit') - $this->record->journalEntries()->sum('credit'))
                            ->rupiah()
                            ->color(fn (float $state): string => abs($state) < 0.01 ? 'success' : 'danger')
                            ->weight('bold'),
                    ]),

                Infolists\Components\Section::make('Rincian Bahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('material_items_summary')
                            ->label('Ringkasan Item')
                            ->state(function () {
                                $items = $this->record->items;
                                $totalQuantity = $items->sum('quantity');

                                return $items->count() . ' item / qty ' . number_format((float) $totalQuantity, 2, ',', '.');
                            })
                            ->columnSpanFull(),

                        Infolists\Components\ViewEntry::make('material_items_display')
                            ->label('Daftar Kebutuhan Bahan')
                            ->view('filament.infolists.material-issue-items-table')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JournalEntry::query()
                    ->where('source_type', MaterialIssue::class)
                    ->where('source_id', $this->record->id)
                    ->with('coa')
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referensi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('coa.name')
                    ->label('Akun')
                    ->getStateUsing(fn ($record) => $record->coa ? '(' . $record->coa->code . ') ' . $record->coa->name : '-'),
                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->rupiah()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->rupiah()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap(),
                Tables\Columns\TextColumn::make('journal_type')
                    ->label('Tipe')
                    ->badge(),
            ])
            ->defaultSort('date', 'asc')
            ->paginated(false)
            ->striped();
    }

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
                            $record->refresh();

                            Notification::make()
                                ->title('Material Issue Diselesaikan Otomatis')
                                ->body("Material Issue {$record->issue_number} langsung selesai karena konfirmasi gudang sudah confirmed.")
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
