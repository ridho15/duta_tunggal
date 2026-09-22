<?php

namespace App\Filament\Support;

use App\Filament\Resources\SaleOrderResource;
use App\Models\SaleOrder;
use App\Services\ApprovalControlService;
use App\Services\SaleOrderReservationSynchronizer;
use App\Services\SalesOrderService;
use App\Services\StockAvailability;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Aksi stok pada Sales Order (T2.5) — dipakai bersama tabel dan halaman View SO:
 *  - "Setujui sebagai Backorder" (D2, flag block_short_approval): persetujuan walau stok kurang, alasan wajib, tercatat.
 *  - "Coba reservasi ulang" (D21, flag reserve_on_so_approve): isi ulang reservasi yang masih kurang.
 */
class SaleOrderStockActions
{
    /** Pemeriksaan stok: memakai hasil batch daftar bila ada (tanpa N+1), selain itu per SO. */
    private static function check(SaleOrder $record, ?object $livewire): array
    {
        if ($livewire !== null && method_exists($livewire, 'getTableRecords')) {
            return SaleOrderResource::stockCheckForList($livewire, $record);
        }

        return app(StockAvailability::class)->check($record);
    }

    public static function backorder($action)
    {
        return $action
            ->label('Setujui sebagai Backorder')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->modalHeading('Setujui sebagai Backorder')
            ->modalDescription(function (SaleOrder $record): string {
                $lines = app(StockAvailability::class)->describeShortages(app(StockAvailability::class)->check($record));

                return 'Stok belum mencukupi: '.implode('; ', $lines).'. SO disetujui sebagai BACKORDER — stok yang ada ditahan sebagian dan sisanya diisi otomatis saat stok masuk. Alasan wajib dan tercatat.';
            })
            ->modalSubmitActionLabel('Ya, Setujui Backorder')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->form(fn (SaleOrder $record): array => array_merge([
                Textarea::make('reason')->label('Alasan backorder')->required()->rows(3)
                    ->placeholder('Mis. barang dalam perjalanan dari supplier, PO sudah terbit...'),
            ], ApprovalActions::saleOrderForm($record)))
            ->visible(function (SaleOrder $record, $livewire = null): bool {
                if (! config('sales.stock.block_short_approval', false) || $record->status !== 'request_approve') {
                    return false;
                }

                if (! app(ApprovalControlService::class)->canApproveSaleOrder(Auth::user(), $record)['allowed']) {
                    return false;
                }

                return self::check($record, $livewire)['has_shortage'];
            })
            ->action(function (SaleOrder $record, array $data): void {
                try {
                    app(SalesOrderService::class)->approveAsBackorder($record, $data['reason'] ?? null, $data['override_reason'] ?? null, $data['credit_override_reason'] ?? null);
                    Notification::make()->success()->title('Disetujui sebagai Backorder')
                        ->body("SO {$record->so_number} disetujui. Stok yang tersedia ditahan; sisanya diisi otomatis saat stok masuk.")->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Gagal Menyetujui Backorder')->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send();
                }
            });
    }

    public static function retryReservation($action)
    {
        return $action
            ->label('Coba reservasi ulang')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Coba reservasi ulang')
            ->modalDescription('Menahan stok bebas yang kini tersedia untuk item SO ini yang belum tertahan penuh (backorder).')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->visible(fn (SaleOrder $record): bool => SaleOrderReservationSynchronizer::enabled()
                && in_array($record->status, SaleOrderReservationSynchronizer::ACTIVE_STATUSES, true)
                && (bool) Auth::user()?->hasPermissionTo('response sales order'))
            ->action(function (SaleOrder $record): void {
                $summary = app(SaleOrderReservationSynchronizer::class)->sync($record->id, "Coba reservasi ulang SO {$record->so_number}");
                $short = collect($summary)->sum('shortage');

                $notification = Notification::make()->title('Reservasi disusun ulang');
                $short > 0.00001
                    ? $notification->warning()->body('Masih ada kekurangan stok '.rtrim(rtrim(number_format($short, 2, ',', '.'), '0'), ',').' unit — akan diisi otomatis saat stok masuk.')
                    : $notification->success()->body('Semua kebutuhan SO sudah tertahan.');
                $notification->send();
            });
    }
}
