<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'approved',
                        'date_approved' => Carbon::now(),
                        'approved_by' => Auth::user()->id,
                    ]);
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
            Action::make('request_approval')
                ->label('Request Approval')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_approval', 'closed', 'completed', 'approved']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'request_approval'
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
                ->hidden(function ($record) {
                    return in_array($record->status, ['draft', 'closed', 'request_close', 'request_approval']);
                })
                ->openUrlInNewTab()
                ->url(function ($record) {
                    return route('purchase-order.cetak', ['id' => $record->id]);
                })
        ];
    }
}
