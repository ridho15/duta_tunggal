<?php

namespace App\Filament\Support;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Services\DeliveryOrderTransitions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Aksi status Delivery Order alur ketat (T2.3) — dipakai bersama oleh tabel DO dan halaman View DO.
 * Semua aksi tersembunyi bila `sales.stock.strict_dispatch` mati dan hanya lewat DeliveryOrderTransitions.
 * Parameter `$action` boleh Filament\Tables\Actions\Action maupun Filament\Actions\Action (rantai metodenya sama).
 */
class DeliveryOrderActions
{
    private const SHIPPABLE = ['approved', 'confirmed', 'partial', 'delivery_failed'];

    private static function can(): bool
    {
        return (bool) Auth::user()?->hasPermissionTo('response delivery order');
    }

    private static function strictAnd(callable $check): \Closure
    {
        return fn ($record) => DeliveryOrderTransitions::enabled() && self::can() && $check($record);
    }

    /** Jalankan transisi; galat matriks/stok ditampilkan sebagai notifikasi (tanpa halaman galat). */
    public static function run(callable $callback, string $successTitle, string $successBody): void
    {
        try {
            $callback();
        } catch (DeliveryOrderTransitionException $e) {
            Notification::make()->danger()->title('Tidak dapat diproses')->body($e->getMessage())->persistent()->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->body($successBody)->send();
    }

    public static function dispatch($action)
    {
        return $action
            ->label('Kirim')
            ->icon('heroicon-o-truck')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Kirim Delivery Order')
            ->modalDescription('Barang akan keluar dari gudang: stok fisik berkurang dan reservasi dikonsumsi. Pastikan barang benar-benar berangkat.')
            ->modalSubmitActionLabel('Ya, Kirim')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->visible(self::strictAnd(fn ($record) => in_array($record->status, self::SHIPPABLE, true)))
            ->form(fn () => DeliveryOrderTransitions::canOverrideNegativeStock(Auth::user()) ? [
                Toggle::make('override_negative_stock')
                    ->label('Kecualikan larangan stok negatif (Owner/Super Admin)')
                    ->helperText('Hanya bila stok fisik di sistem memang belum tercatat. Tercatat di log DO beserta alasan.')
                    ->live(),
                Textarea::make('reason')
                    ->label('Alasan pengecualian')
                    ->required(fn ($get) => (bool) $get('override_negative_stock'))
                    ->visible(fn ($get) => (bool) $get('override_negative_stock')),
            ] : [])
            ->action(function ($record, array $data) {
                self::run(
                    fn () => app(DeliveryOrderTransitions::class)->to($record, 'sent', [
                        'override_negative_stock' => (bool) ($data['override_negative_stock'] ?? false),
                        'reason' => $data['reason'] ?? null,
                        'source' => 'action',
                    ]),
                    'Delivery Order Dikirim',
                    "DO {$record->do_number} berstatus Dikirim. Stok gudang sudah dikurangi."
                );
            });
    }

    public static function receive($action)
    {
        return $action
            ->label('Konfirmasi Diterima')
            ->icon('heroicon-o-hand-thumb-up')
            ->color('info')
            ->modalHeading('Konfirmasi barang diterima customer')
            ->modalSubmitActionLabel('Simpan')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->visible(self::strictAnd(fn ($record) => $record->status === 'sent'))
            ->form([
                DateTimePicker::make('received_at')->label('Diterima pada')->default(now())->required()->seconds(false),
                TextInput::make('received_by')->label('Nama penerima')->required()->maxLength(150)
                    ->helperText('Nama orang yang menandatangani/menerima barang di tempat customer.'),
            ])
            ->action(function ($record, array $data) {
                self::run(
                    fn () => app(DeliveryOrderTransitions::class)->to($record, 'received', [
                        'received_at' => $data['received_at'] ?? now(),
                        'received_by' => $data['received_by'] ?? null,
                        'source' => 'action',
                    ]),
                    'Penerimaan Dicatat',
                    "DO {$record->do_number} dicatat diterima oleh {$data['received_by']}."
                );
            });
    }

    public static function complete($action)
    {
        return $action
            ->label('Selesaikan')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Selesaikan Delivery Order')
            ->modalDescription('Dokumen pengiriman selesai: jurnal DO dan invoice otomatis diterbitkan. Bila belum dicatat diterima, sistem mencatat penerimaan otomatis.')
            ->modalSubmitActionLabel('Ya, Selesaikan')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->visible(self::strictAnd(fn ($record) => in_array($record->status, ['sent', 'received'], true)))
            ->action(function ($record) {
                self::run(
                    fn () => app(DeliveryOrderTransitions::class)->complete($record, ['source' => 'action']),
                    'Delivery Order Selesai',
                    "DO {$record->do_number} selesai. Invoice diterbitkan otomatis bila belum ada."
                );
            });
    }

    public static function cancel($action)
    {
        return $action
            ->label('Batalkan DO')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Batalkan Delivery Order')
            ->modalDescription('Reservasi stok DO dilepas dan kuantitasnya kembali menjadi sisa kirim Sales Order. DO yang dibatalkan tidak dapat dibuka lagi.')
            ->modalSubmitActionLabel('Ya, Batalkan DO')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->visible(self::strictAnd(fn ($record) => in_array($record->status, ['approved', 'confirmed', 'partial', 'delivery_failed'], true)))
            ->form([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->rows(3),
            ])
            ->action(function ($record, array $data) {
                self::run(
                    fn () => app(DeliveryOrderTransitions::class)->to($record, 'closed', ['reason' => $data['reason'], 'action' => 'cancelled', 'source' => 'action']),
                    'Delivery Order Dibatalkan',
                    "DO {$record->do_number} dibatalkan; reservasi dilepas."
                );
            });
    }

    /** Pengiriman Gagal: alur ketat mewajibkan alasan dan mengembalikan stok bila barang sudah berangkat (D18). */
    public static function failed($action)
    {
        return $action
            ->form(fn () => DeliveryOrderTransitions::enabled()
                ? [Textarea::make('reason')->label('Alasan pengiriman gagal')->required()->rows(3)
                    ->helperText('Bila barang sudah berangkat, stok dikembalikan ke gudang dan DO dapat dijadwalkan ulang.')]
                : [])
            ->action(function ($record, array $data) {
                if (! DeliveryOrderTransitions::enabled()) {
                    $record->update(['status' => 'delivery_failed']);
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: 'Information', message: 'Delivery Order ditandai sebagai Pengiriman Gagal. Proses selanjutnya: Segera koordinasikan dengan Tim Sales dan jadwalkan ulang pengiriman ke customer.');

                    return;
                }

                self::run(
                    fn () => app(DeliveryOrderTransitions::class)->to($record, 'delivery_failed', ['reason' => $data['reason'] ?? '', 'action' => 'delivery_failed', 'source' => 'action']),
                    'Pengiriman Gagal Dicatat',
                    'Delivery Order ditandai Pengiriman Gagal. Stok yang sudah keluar dikembalikan; jadwalkan ulang pengiriman ke customer.'
                );
            });
    }
}
