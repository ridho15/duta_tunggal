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
            $details = $invalid->map(function ($do) {
                $statusLabel = DeliveryOrder::statusLabel($do->status);
                return "{$do->do_number} (Status: {$statusLabel})";
            })->implode(', ');

            throw ValidationException::withMessages([
                $field => "Surat Jalan hanya dapat dibuat dari Delivery Order berstatus approved (disetujui). DO berikut belum memenuhi syarat: {$details}.",
            ]);
        }

        $cabangIds = $deliveryOrders->pluck('cabang_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($cabangIds->count() > 1) {
            throw ValidationException::withMessages([
                $field => 'Semua Delivery Order yang dipilih harus berasal dari cabang yang sama.',
            ]);
        }

        // Multi-DO Consolidation Rule: Semua DO yang digabungkan harus berasal dari customer yang sama
        $customerIds = $deliveryOrders->flatMap(function (DeliveryOrder $do) {
            $do->loadMissing('salesOrders.customer');
            return $do->salesOrders->pluck('customer_id');
        })->filter()->unique()->values();

        if ($customerIds->count() > 1) {
            throw ValidationException::withMessages([
                $field => 'Semua Delivery Order yang dipilih harus berasal dari customer yang sama untuk dapat digabungkan dalam satu Surat Jalan.',
            ]);
        }

        // B20: DO yang pernah diretur tidak ikut Surat Jalan — yang dikirim/ditagih adalah DO pengganti (…C)
        $returned = $deliveryOrders->filter(fn (DeliveryOrder $do) => $do->returned_at !== null);
        if ($returned->isNotEmpty()) {
            $details = $returned->map(function (DeliveryOrder $do) {
                $replacements = $do->replacements()->pluck('do_number')->implode(', ');

                return $do->do_number.($replacements !== '' ? " (gunakan DO pengganti {$replacements})" : ' (belum ada DO pengganti)');
            })->implode('; ');

            throw ValidationException::withMessages([
                $field => "Delivery Order yang pernah diretur tidak dapat dibuatkan Surat Jalan; gunakan DO pengganti (…C): {$details}.",
            ]);
        }

        // B19: DO yang digabung dalam satu Surat Jalan harus berasal dari Sales Order yang SAMA
        $saleOrderSets = $deliveryOrders->map(function (DeliveryOrder $do) {
            $do->loadMissing('salesOrders');

            return $do->salesOrders->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->implode(',');
        })->unique();
        if ($saleOrderSets->count() > 1) {
            $numbers = $deliveryOrders->map(fn (DeliveryOrder $do) => $do->do_number.' ('.($do->salesOrders->pluck('so_number')->implode(' + ') ?: 'tanpa SO').')')->implode(', ');

            throw ValidationException::withMessages([
                $field => "Delivery Order dalam satu Surat Jalan harus berasal dari Sales Order yang sama. DO yang dipilih berasal dari SO berbeda: {$numbers}.",
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

    /**
     * B21 (UAT 13 Okt 2026): terbitnya Surat Jalan otomatis menandai DO-nya "Diterima" dalam SATU langkah — DO melewati Dikirim lalu
     * Diterima (tercatat di log DO, penerima = nomor SJ). Tidak perlu tombol Konfirmasi Diterima terpisah. DO yang sudah lebih jauh
     * (Dikirim/Diterima/Selesai) dibiarkan. Aturan "SJ wajib terbit sebelum DO dikirim" (B22) terpenuhi karena SJ sudah terbit.
     *
     * @throws \App\Exceptions\DeliveryOrderTransitionException
     */
    public function markDeliveryOrdersReceived(SuratJalan $suratJalan): void
    {
        $transitions = app(DeliveryOrderTransitions::class);
        $note = "Surat Jalan {$suratJalan->sj_number} terbit";

        foreach ($suratJalan->deliveryOrder()->get() as $deliveryOrder) {
            $deliveryOrder->refresh();

            if (in_array($deliveryOrder->status, ['approved', 'confirmed', 'partial'], true)) {
                $transitions->to($deliveryOrder, 'sent', ['comments' => "Dikirim otomatis: {$note}", 'source' => 'surat_jalan']);
            }

            if ($deliveryOrder->refresh()->status === 'sent') {
                $transitions->to($deliveryOrder, 'received', [
                    'comments' => "Diterima otomatis: {$note}",
                    'received_by' => "Surat Jalan {$suratJalan->sj_number}",
                    'source' => 'surat_jalan',
                ]);
            }
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

        DB::transaction(function () use ($suratJalan) {
            $suratJalan->update(['status' => SuratJalan::STATUS_ISSUED, 'issued_at' => $suratJalan->issued_at ?? now()]);
            $this->markDeliveryOrdersReceived($suratJalan);
        });

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

        $advanced = $suratJalan->deliveryOrder()->get()->filter(fn (DeliveryOrder $do) => in_array($do->status, ['sent', 'received', 'completed'], true));
        if ($advanced->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => "Surat Jalan {$suratJalan->sj_number} tidak dapat dibatalkan: DO "
                    .$advanced->map(fn ($do) => $do->do_number.' ('.DeliveryOrder::statusLabel($do->status).')')->implode(', ')
                    .' sudah ditandai Diterima saat Surat Jalan terbit. Koreksi barang lewat Retur Delivery Order / Return Product.',
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
            $this->markDeliveryOrdersReceived($new);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($new)
                ->log("Surat Jalan diterbitkan ulang menggantikan {$cancelled->sj_number}.");

            return $new;
        });
    }
}
