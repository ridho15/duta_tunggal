<?php

namespace App\Services;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu perubahan status Delivery Order (T2.3, flag `sales.stock.strict_dispatch`).
 *
 * Semua pintu (aksi DO, aksi jadwal, konfirmasi gudang, layanan) memanggil `to()`; efek samping (gerakan stok, konsumsi/pengembalian
 * reservasi, status item, log) dijalankan `DeliveryOrderObserver` sehingga hasilnya sama di semua pintu. `to()` menjamin:
 *   - transisi hanya yang ada di MATRIX (selain itu ditolak dengan pesan jelas);
 *   - baris DO dikunci + status dibaca ulang di dalam transaksi → klik ganda / dua proses = satu efek;
 *   - alasan wajib untuk batal / pengiriman gagal, tercatat di log;
 *   - stok fisik cukup saat Dikirim (D15) — kecuali pengecualian beralasan Owner/Super Admin;
 *   - atomik: bila efek gagal, status kembali seperti semula.
 */
class DeliveryOrderTransitions
{
    /** Peran yang boleh mengecualikan larangan stok negatif (D15). */
    public const OVERRIDE_ROLES = ['Owner', 'Super Admin'];

    /**
     * Matriks transisi (dari → daftar tujuan). Status lama `confirmed`/`partial`/`supplier` dipertahankan agar data lama tetap bisa berjalan.
     *
     * @var array<string, array<int, string>>
     */
    public const MATRIX = [
        'draft' => ['request_stock', 'request_approve', 'request_close'],
        'request_stock' => ['approved', 'reject', 'request_approve', 'request_close'],
        'request_approve' => ['approved', 'reject', 'request_close', 'request_stock'],
        'reject' => ['request_stock', 'request_approve', 'approved', 'draft'],
        'approved' => ['sent', 'closed', 'delivery_failed'],
        'confirmed' => ['sent', 'closed', 'delivery_failed'],
        'partial' => ['sent', 'closed', 'delivery_failed'],
        'supplier' => ['request_stock', 'approved', 'reject', 'closed'],
        'sent' => ['received', 'completed', 'delivery_failed'],
        'received' => ['completed'],
        'delivery_failed' => ['approved', 'sent', 'closed'],
        'request_close' => ['closed', 'request_approve', 'request_stock', 'draft'],
        'closed' => [],
        'completed' => [],
    ];

    /** Transisi (dari→ke) yang wajib menyertakan alasan. */
    private const REASON_REQUIRED = [
        'approved>closed', 'confirmed>closed', 'partial>closed', 'delivery_failed>closed',
        '*>delivery_failed',
    ];

    /** @var array<int, array<string, mixed>> tumpukan konteks transisi yang sedang berjalan */
    private static array $stack = [];

    public static function enabled(): bool
    {
        return (bool) config('sales.stock.strict_dispatch', false);
    }

    /** Konteks transisi yang sedang berjalan (dibaca observer untuk alasan/log), atau null bila status diubah di luar `to()`. */
    public static function context(): ?array
    {
        return self::$stack === [] ? null : self::$stack[array_key_last(self::$stack)];
    }

    public static function allows(?string $from, string $to): bool
    {
        return in_array($to, self::MATRIX[(string) $from] ?? [], true);
    }

    /** Tujuan yang sah dari status ini (untuk visibilitas tombol). */
    public static function targetsFrom(?string $from): array
    {
        return self::MATRIX[(string) $from] ?? [];
    }

    public static function mustHaveReason(?string $from, string $to): bool
    {
        return in_array("{$from}>{$to}", self::REASON_REQUIRED, true) || in_array("*>{$to}", self::REASON_REQUIRED, true);
    }

    public static function canOverrideNegativeStock(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::OVERRIDE_ROLES);
    }

    /**
     * @param  array{reason?: string|null, comments?: string|null, action?: string|null, received_by?: string|null, received_at?: mixed,
     *               override_negative_stock?: bool, actor?: User|null, source?: string|null}  $options
     *
     * @throws DeliveryOrderTransitionException
     */
    public function to(DeliveryOrder $deliveryOrder, string $status, array $options = []): DeliveryOrder
    {
        DB::transaction(function () use ($deliveryOrder, $status, $options) {
            $locked = DeliveryOrder::withoutGlobalScopes()->lockForUpdate()->find($deliveryOrder->getKey());
            if (! $locked) {
                throw new DeliveryOrderTransitionException('Delivery Order tidak ditemukan atau sudah dihapus.');
            }

            $from = (string) $locked->status;
            if ($from === $status) {
                return; // sudah di tujuan (klik ganda / proses lain sudah mengerjakannya): tanpa efek kedua
            }

            $actor = $options['actor'] ?? Auth::user();
            $reason = trim((string) ($options['reason'] ?? $options['comments'] ?? ''));

            $this->assertAllowed($locked, $from, $status);

            if (self::mustHaveReason($from, $status) && $reason === '') {
                throw new DeliveryOrderTransitionException($this->reasonMessage($from, $status));
            }

            $override = $this->assertPhysicalStock($locked, $status, $options, $actor, $reason);

            $attributes = ['status' => $status];
            if ($status === 'received') {
                $attributes['received_at'] = $options['received_at'] ?? now();
                $attributes['received_by_name'] = filled($options['received_by'] ?? null) ? trim((string) $options['received_by']) : null;
            }

            self::$stack[] = [
                'delivery_order_id' => $locked->id,
                'from' => $from,
                'to' => $status,
                'reason' => $reason !== '' ? $reason : null,
                'comments' => $options['comments'] ?? null,
                'action' => $options['action'] ?? null,
                'actor_id' => $actor?->getKey(),
                'source' => $options['source'] ?? null,
                'override' => $override,
                'validated' => true,
            ];

            try {
                $locked->forceFill($attributes)->save();
            } finally {
                array_pop(self::$stack);
            }
        });

        return $deliveryOrder->refresh();
    }

    /**
     * Selesaikan DO: dari Dikirim otomatis melewati Diterima (tercatat, D23) lalu Selesai (jurnal + invoice) dalam satu transaksi.
     *
     * @param  array<string, mixed>  $options  sama dengan to()
     */
    public function complete(DeliveryOrder $deliveryOrder, array $options = []): DeliveryOrder
    {
        DB::transaction(function () use ($deliveryOrder, $options) {
            $deliveryOrder->refresh();

            if ($deliveryOrder->status === 'sent') {
                $this->to($deliveryOrder, 'received', $options + ['comments' => 'Diterima otomatis saat DO diselesaikan']);
            }

            $this->to($deliveryOrder, 'completed', $options);
        });

        return $deliveryOrder->refresh();
    }

    /**
     * Validasi yang dipakai model saat status diubah TANPA lewat `to()` (mis. `$do->update(['status' => ...])` di kode lama):
     * matriks + stok fisik. Efek samping tetap dijalankan observer.
     *
     * @throws DeliveryOrderTransitionException
     */
    public static function guardModelChange(DeliveryOrder $deliveryOrder): void
    {
        if (! self::enabled() || ! $deliveryOrder->isDirty('status')) {
            return;
        }

        $context = self::context();
        if ($context && ($context['delivery_order_id'] ?? null) === $deliveryOrder->getKey() && ($context['validated'] ?? false)) {
            return;
        }

        $from = (string) $deliveryOrder->getOriginal('status');
        $to = (string) $deliveryOrder->status;
        $service = app(self::class);

        $service->assertAllowed($deliveryOrder, $from, $to);
        $service->assertPhysicalStock($deliveryOrder, $to, [], Auth::user(), '');
    }

    /** @throws DeliveryOrderTransitionException */
    public function assertAllowed(DeliveryOrder $deliveryOrder, string $from, string $to): void
    {
        if (self::allows($from, $to)) {
            return;
        }

        $number = $deliveryOrder->do_number ?: "#{$deliveryOrder->getKey()}";
        $options = self::targetsFrom($from);
        $hint = $options === []
            ? 'Status ini sudah final.'
            : 'Langkah yang diizinkan: '.implode(', ', array_map(fn ($s) => '"'.DeliveryOrder::statusLabel($s).'"', $options)).'.';

        throw new DeliveryOrderTransitionException(sprintf(
            'Delivery Order %s tidak dapat diubah dari "%s" ke "%s". %s',
            $number,
            DeliveryOrder::statusLabel($from),
            DeliveryOrder::statusLabel($to),
            $hint
        ));
    }

    /**
     * D15: DO tidak boleh dikirim bila stok FISIK tidak cukup. Owner/Super Admin dapat mengecualikan dengan alasan (tercatat di log).
     *
     * @return array<int, array>|null rincian kekurangan yang dikecualikan, atau null bila tidak ada kekurangan
     *
     * @throws DeliveryOrderTransitionException
     */
    protected function assertPhysicalStock(DeliveryOrder $deliveryOrder, string $to, array $options, ?User $actor, string $reason): ?array
    {
        if ($to !== 'sent') {
            return null;
        }

        $shortages = app(DeliveryShipments::class)->physicalShortages($deliveryOrder);
        if ($shortages === []) {
            return null;
        }

        $detail = DeliveryShipments::describeShortages($shortages);

        if (($options['override_negative_stock'] ?? false) === true) {
            if (! self::canOverrideNegativeStock($actor)) {
                throw new DeliveryOrderTransitionException("Stok fisik tidak cukup ({$detail}). Hanya Owner atau Super Admin yang dapat mengecualikan larangan stok negatif.", $shortages);
            }
            if ($reason === '') {
                throw new DeliveryOrderTransitionException("Stok fisik tidak cukup ({$detail}). Pengecualian stok negatif wajib disertai alasan.", $shortages);
            }

            return $shortages;
        }

        throw new DeliveryOrderTransitionException(
            "Delivery Order {$deliveryOrder->do_number} belum dapat dikirim — stok fisik tidak cukup ({$detail}). Tambah stok (mis. penerimaan/penyesuaian) atau kurangi kuantitas DO.",
            $shortages
        );
    }

    private function reasonMessage(string $from, string $to): string
    {
        return match ($to) {
            'delivery_failed' => 'Alasan pengiriman gagal wajib diisi (mis. customer tidak di tempat, alamat tidak ditemukan).',
            'closed' => 'Alasan pembatalan Delivery Order wajib diisi.',
            default => 'Alasan wajib diisi untuk perubahan status ini.',
        };
    }

    /** Catat baris log DO untuk perubahan status (dipanggil observer agar seragam di semua pintu). */
    public static function writeLog(DeliveryOrder $deliveryOrder, string $from, string $to): void
    {
        $context = self::context();
        $sameContext = $context && ($context['delivery_order_id'] ?? null) === $deliveryOrder->getKey();

        $comments = $sameContext ? ($context['reason'] ?? $context['comments'] ?? null) : null;
        $notes = null;
        if ($sameContext && ! empty($context['override'])) {
            $notes = 'PENGECUALIAN STOK NEGATIF: '.DeliveryShipments::describeShortages($context['override']);
        }
        if ($to === 'received' && filled($deliveryOrder->received_by_name)) {
            $notes = trim(($notes ? $notes.' | ' : '').'Diterima oleh '.$deliveryOrder->received_by_name);
        }

        $actorId = $sameContext ? ($context['actor_id'] ?? null) : null;
        $actorId ??= Auth::id();

        DeliveryOrderLog::create([
            'delivery_order_id' => $deliveryOrder->id,
            'status' => $to,
            'action' => $sameContext ? ($context['action'] ?? ($context['override'] ? 'negative_stock_override' : null)) : null,
            'confirmed_by' => $actorId ?? 0,   // 0 = sistem (proses terjadwal / konsol)
            'user_id' => $actorId,
            'comments' => $comments,
            'old_value' => $from,
            'new_value' => $to,
            'notes' => $notes,
        ]);
    }

    /** Status item DO yang mengikuti status DO; null = biarkan. */
    public static function itemStatusFor(string $status): ?string
    {
        return match ($status) {
            'request_stock' => 'requested',
            'approved', 'delivery_failed' => 'confirmed',
            'reject' => 'rejected',
            'partial' => 'partial',
            'sent' => 'sent',
            'received', 'completed' => 'received',
            default => null,
        };
    }
}
