<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup Surat Jalan: terbit → (dikunci) → batal → terbit ulang.
 * Semua jalur (halaman Buat, aksi tabel/halaman Lihat, terbit ulang) memakai aturan yang sama di sini.
 */
class SuratJalanService
{
    public const MIN_CANCEL_REASON_LENGTH = 5;

    /** SJ-YYYYMMDD-0001, berurutan per hari (dihitung dari nomor terbesar, bukan acak). */
    public function generateCode(?int $cabangId = null): string
    {
        // T4.1 (flag central_numbering): penomoran terpusat SJ-{KODECABANG}-{YYMM}-{SEQ4}, atomik.
        if (DocumentNumberService::enabled()) {
            return app(DocumentNumberService::class)->next('surat_jalan', $cabangId);
        }

        return SequentialNumberGenerator::generate('surat_jalans', 'sj_number', 'SJ-', 4, 'Ymd');
    }

    /**
     * Delivery Order yang layak dibuatkan Surat Jalan:
     *  - berstatus approved,
     *  - berasal dari satu cabang,
     *  - belum tercantum di Surat Jalan lain yang masih berlaku (Draft/Terbit).
     *
     * @param  Collection<int, DeliveryOrder>  $deliveryOrders
     * @param  int|null  $ignoreSuratJalanId  Surat Jalan yang sedang dikoreksi (tidak dihitung sebagai "lain")
     *
     * @throws ValidationException
     */
    public function assertDeliveryOrdersUsable(Collection $deliveryOrders, ?int $ignoreSuratJalanId = null, string $field = 'deliveryOrder'): void
    {
        if ($deliveryOrders->isEmpty()) {
            throw ValidationException::withMessages([$field => 'Pilih minimal satu Delivery Order.']);
        }

        $invalid = $deliveryOrders->where('status', '!=', 'approved');
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'Surat Jalan hanya dapat dibuat dari Delivery Order berstatus approved. Tidak memenuhi: '
                    . $invalid->pluck('do_number')->implode(', ') . '.',
            ]);
        }

        $cabangIds = $deliveryOrders->pluck('cabang_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($cabangIds->count() > 1) {
            throw ValidationException::withMessages([
                $field => 'Semua Delivery Order yang dipilih harus berasal dari cabang yang sama.',
            ]);
        }

        $taken = DB::table('surat_jalan_delivery_orders as pivot')
            ->join('surat_jalans as sj', 'sj.id', '=', 'pivot.surat_jalan_id')
            ->whereIn('pivot.delivery_order_id', $deliveryOrders->pluck('id'))
            ->whereNull('sj.deleted_at')
            ->whereIn('sj.status', SuratJalan::ACTIVE_STATUSES)
            ->when($ignoreSuratJalanId, fn ($q) => $q->where('sj.id', '!=', $ignoreSuratJalanId))
            ->select('pivot.delivery_order_id', 'sj.sj_number')
            ->get();

        if ($taken->isNotEmpty()) {
            $doNumbers = $deliveryOrders->keyBy('id');
            $lines = $taken->map(fn ($row) => ($doNumbers[$row->delivery_order_id]->do_number ?? $row->delivery_order_id) . " (sudah di {$row->sj_number})");

            throw ValidationException::withMessages([
                $field => 'Delivery Order sudah tercantum di Surat Jalan lain yang masih berlaku: ' . $lines->implode(', ')
                    . '. Batalkan Surat Jalan tersebut lebih dulu bila perlu diterbitkan ulang.',
            ]);
        }
    }

    /** Draft → Terbit. */
    public function issue(SuratJalan $suratJalan): SuratJalan
    {
        if (! $suratJalan->isDraft()) {
            throw ValidationException::withMessages(['status' => "Surat Jalan {$suratJalan->sj_number} bukan Draft sehingga tidak dapat diterbitkan."]);
        }

        $deliveryOrders = $suratJalan->deliveryOrder()->get();
        $this->assertDeliveryOrdersUsable($deliveryOrders, $suratJalan->id, 'status');

        $suratJalan->update(['status' => SuratJalan::STATUS_ISSUED, 'issued_at' => $suratJalan->issued_at ?? now()]);

        return $suratJalan->refresh();
    }

    /**
     * Batalkan Surat Jalan yang sudah terbit (alasan wajib, tercatat siapa & kapan).
     * Ditolak bila masih dipakai Jadwal Pengiriman yang berjalan/selesai: keluarkan dulu dari jadwal.
     *
     * @throws ValidationException
     */
    public function cancel(SuratJalan $suratJalan, string $reason, ?int $userId = null): SuratJalan
    {
        $reason = trim($reason);

        if (! $suratJalan->isIssued()) {
            throw ValidationException::withMessages([
                'status' => $suratJalan->isCancelled()
                    ? "Surat Jalan {$suratJalan->sj_number} sudah dibatalkan."
                    : "Surat Jalan {$suratJalan->sj_number} masih Draft; hapus saja bila tidak diperlukan.",
            ]);
        }

        if (mb_strlen($reason) < self::MIN_CANCEL_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'cancel_reason' => 'Alasan pembatalan wajib diisi (minimal ' . self::MIN_CANCEL_REASON_LENGTH . ' karakter).',
            ]);
        }

        $schedules = $suratJalan->blockingDeliverySchedules();
        if ($schedules->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => "Surat Jalan {$suratJalan->sj_number} masih dipakai Jadwal Pengiriman "
                    . $schedules->map(fn ($s) => "{$s->schedule_number} ({$s->status_label})")->implode(', ')
                    . '. Keluarkan dari jadwal (atau batalkan jadwal) terlebih dahulu.',
            ]);
        }

        $suratJalan->update([
            'status' => SuratJalan::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $userId ?? Auth::id(),
            'cancel_reason' => $reason,
        ]);

        return $suratJalan->refresh();
    }

    /**
     * Terbitkan ulang Surat Jalan yang dibatalkan: dokumen baru (nomor baru, terbit) untuk DO yang sama.
     * DO harus masih approved dan belum tercantum di Surat Jalan lain.
     *
     * @throws ValidationException
     */
    public function reissue(SuratJalan $cancelled): SuratJalan
    {
        if (! $cancelled->isCancelled()) {
            throw ValidationException::withMessages(['status' => "Hanya Surat Jalan yang dibatalkan yang dapat diterbitkan ulang."]);
        }

        $deliveryOrders = $cancelled->deliveryOrder()->get();
        $this->assertDeliveryOrdersUsable($deliveryOrders, null, 'status');

        return DB::transaction(function () use ($cancelled, $deliveryOrders) {
            $new = SuratJalan::create([
                'sj_number' => $this->generateCode(),
                'issued_at' => now(),
                'status' => SuratJalan::STATUS_ISSUED,
                'created_by' => Auth::id() ?? $cancelled->created_by,
                'cabang_id' => $cancelled->cabang_id,
            ]);
            $new->deliveryOrder()->sync($deliveryOrders->pluck('id')->all());

            activity()
                ->causedBy(Auth::user())
                ->performedOn($new)
                ->log("Surat Jalan diterbitkan ulang menggantikan {$cancelled->sj_number}.");

            return $new;
        });
    }
}
