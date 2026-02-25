<?php

namespace App\Filament\Resources\PaymentRequestResource\Pages;

use App\Filament\Resources\PaymentRequestResource;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPaymentRequest extends ViewRecord
{
    protected static string $resource = PaymentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'draft'),

            Actions\Action::make('submit')
                ->label('Ajukan Persetujuan')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'pending_approval',
                        'requested_by' => Auth::id(),
                    ]);
                    Notification::make()->title('Payment Request diajukan untuk persetujuan')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending_approval')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Catatan Persetujuan')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                        'approval_notes' => $data['approval_notes'] ?? null,
                    ]);
                    Notification::make()->title('Payment Request disetujui')->success()->send();
                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                }),

            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending_approval')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                        'approval_notes' => $data['approval_notes'],
                    ]);
                    Notification::make()->title('Payment Request ditolak')->warning()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('create_payment')
                ->label('Buat Vendor Payment')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn () => $this->record->status === 'approved' && !$this->record->vendor_payment_id)
                ->url(fn () => route('filament.admin.resources.vendor-payments.create', [
                    'payment_request_id' => $this->record->id,
                    'supplier_id' => $this->record->supplier_id,
                ])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Informasi Payment Request')
                ->columns(3)
                ->schema([
                    TextEntry::make('request_number')->label('Nomor PR'),
                    TextEntry::make('supplier.perusahaan')->label('Vendor'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn ($state) => PaymentRequest::STATUS_COLORS[$state] ?? 'gray')
                        ->formatStateUsing(fn ($state) => PaymentRequest::STATUS_LABELS[$state] ?? $state),
                    TextEntry::make('request_date')->label('Tanggal Request')->date('d/m/Y'),
                    TextEntry::make('payment_date')->label('Tgl Bayar Diminta')->date('d/m/Y'),
                    TextEntry::make('total_amount')->label('Total (Rp)')->money('IDR', locale: 'id'),
                    TextEntry::make('requestedBy.name')->label('Diminta Oleh'),
                    TextEntry::make('approvedBy.name')->label('Disetujui/Ditolak Oleh')->default('-'),
                    TextEntry::make('approved_at')->label('Waktu Approval')->dateTime('d/m/Y H:i')->default('-'),
                ]),

            Section::make('Catatan')
                ->schema([
                    TextEntry::make('notes')->label('Catatan Permintaan')->default('-'),
                    TextEntry::make('approval_notes')->label('Catatan Approval')->default('-'),
                ]),

            Section::make('Invoice yang Termasuk')
                ->schema([
                    TextEntry::make('selected_invoices')
                        ->label('Invoice')
                        ->formatStateUsing(function ($state, $record) {
                            $ids = $record->selected_invoices ?? [];
                            if (empty($ids)) return 'Tidak ada invoice dipilih';
                            $invoices = Invoice::whereIn('id', $ids)->get();
                            return $invoices->map(fn ($i) => "{$i->invoice_number} - Rp " . number_format($i->total, 0, ',', '.'))->join(', ');
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
