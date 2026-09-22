<?php

namespace App\Services;

use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Notifikasi ke finance saat invoice (pembelian/hutang maupun penjualan/piutang) baru berubah status menjadi
 * Terlambat (Isu 8). Dikirim ke SEMUA pengguna yang berizin melihat dokumennya (`view account payable` /
 * `view account receivable`) lewat notifikasi database Filament (lonceng di panel admin) — bukan email/lain,
 * sehingga tidak butuh konfigurasi tambahan dan langsung terlihat saat mereka login.
 */
class OverdueInvoiceNotifier
{
    /**
     * @param  Collection<int, Invoice>  $invoices  invoice yang BARU SAJA berubah status menjadi Terlambat pada jalankan ini
     */
    public function notify(Collection $invoices): void
    {
        if ($invoices->isEmpty()) {
            return;
        }

        $payable = $invoices->filter(fn (Invoice $invoice) => (bool) $invoice->accountPayable?->exists);
        $receivable = $invoices->filter(fn (Invoice $invoice) => (bool) $invoice->accountReceivable?->exists);

        $this->notifyGroup($payable, 'view account payable', 'Hutang', 'filament.admin.resources.account-payables.index');
        $this->notifyGroup($receivable, 'view account receivable', 'Piutang', 'filament.admin.resources.account-receivables.index');
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function notifyGroup(Collection $invoices, string $permission, string $label, string $routeName): void
    {
        if ($invoices->isEmpty()) {
            return;
        }

        $recipients = User::permission($permission)->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $total = $invoices->sum(fn (Invoice $invoice) => $invoice->getRemainingAmount());
        $numbers = $invoices->pluck('invoice_number')->filter()->values();
        $shown = $numbers->take(5)->implode(', ');
        $extra = $numbers->count() > 5 ? ' dan '.($numbers->count() - 5).' lainnya' : '';

        $notification = Notification::make()
            ->title($invoices->count() === 1
                ? "1 invoice {$label} baru saja Terlambat"
                : "{$invoices->count()} invoice {$label} baru saja Terlambat")
            ->body("Nomor: {$shown}{$extra}. Total sisa: ".MoneyHelper::rupiah($total).".")
            ->warning()
            ->icon('heroicon-o-exclamation-triangle');

        if (Route::has($routeName)) {
            $notification->actions([Action::make('view')->label('Lihat Daftar')->url(route($routeName))->markAsRead()]);
        }

        $notification->sendToDatabase($recipients);
    }
}
