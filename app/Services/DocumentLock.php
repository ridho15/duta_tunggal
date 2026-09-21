<?php

namespace App\Services;

use App\Models\CustomerReceipt;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use Illuminate\Database\Eloquent\Model;

/**
 * Sumber TUNGGAL kunci dokumen penjualan (T3.3, D8): policy, visibilitas aksi Filament, dan API bertanya ke sini.
 * Koreksi dokumen terkunci lewat JALUR RESMI (revisi, batalkan, nota kredit), bukan "buka kunci".
 *
 * Aksi: `update` (ubah isi) dan `delete`. Matriks (status yang MASIH boleh):
 *   Quotation      update/delete: draft, reject                 → koreksi: Buat Revisi
 *   Sales Order    update: draft, request_approve · delete: draft → koreksi: Tutup / Batalkan SO
 *   Delivery Order update: draft, request_stock, request_approve, request_close · delete: draft → koreksi: Batalkan DO / Pengiriman Gagal
 *   Surat Jalan    update/delete: draft                          → koreksi: Batalkan Surat Jalan
 *   Jadwal         update: pending · delete: pending, cancelled, failed → koreksi: Batalkan jadwal
 *   Invoice        update/delete: draft                          → koreksi: Nota Kredit / pembatalan (T5)
 *   Penerimaan     update/delete: Draft (belum berjurnal)        → koreksi: Batalkan Penerimaan (jurnal balik)
 *   Retur customer update: pending, received, qc_inspection · delete: pending → koreksi: proses ulang lewat alur retur
 */
class DocumentLock
{
    /** @var array<class-string, array{update: array<int, string>, delete: array<int, string>, correction: string, label: string}> */
    private const MATRIX = [
        DeliveryOrder::class => [
            'label' => 'Delivery Order', 'update' => ['draft', 'request_stock', 'request_approve', 'request_close'], 'delete' => ['draft'],
            'correction' => 'gunakan aksi Batalkan DO atau Pengiriman Gagal',
        ],
        DeliverySchedule::class => [
            'label' => 'Jadwal Pengiriman', 'update' => ['pending'], 'delete' => ['pending', 'cancelled', 'failed'],
            'correction' => 'batalkan jadwal (Batalkan) atau buat jadwal baru',
        ],
        SaleOrder::class => [
            'label' => 'Sales Order', 'update' => ['draft', 'request_approve'], 'delete' => ['draft'],
            'correction' => 'gunakan Close atau Batalkan SO',
        ],
        Quotation::class => [
            'label' => 'Quotation', 'update' => ['draft', 'reject'], 'delete' => ['draft', 'reject'],
            'correction' => 'gunakan Buat Revisi',
        ],
        SuratJalan::class => [
            'label' => 'Surat Jalan', 'update' => ['draft'], 'delete' => ['draft'],
            'correction' => 'batalkan Surat Jalan lalu terbitkan yang baru',
        ],
        Invoice::class => [
            'label' => 'Invoice', 'update' => ['draft'], 'delete' => ['draft'],
            'correction' => 'gunakan koreksi resmi (Nota Kredit / pembatalan invoice)',
        ],
        CustomerReturn::class => [
            'label' => 'Retur Customer', 'update' => ['pending', 'received', 'qc_inspection'], 'delete' => ['pending'],
            'correction' => 'proses koreksi lewat alur retur',
        ],
    ];

    public static function enabled(): bool
    {
        return (bool) config('sales.controls.doc_lock', false);
    }

    /**
     * @return array{locked: bool, reason: string|null, correction: string|null}
     */
    public function check(Model $document, string $action = 'update'): array
    {
        $open = ['locked' => false, 'reason' => null, 'correction' => null];

        if ($document instanceof CustomerReceipt) {
            return $this->checkReceipt($document, $action);
        }

        $rule = self::MATRIX[$document::class] ?? null;
        if ($rule === null || ! isset($rule[$action])) {
            return $open;
        }

        // Surat Jalan menyimpan status sebagai angka (0 draf, 1 terbit, 2 dibatalkan)
        $status = $document instanceof SuratJalan
            ? match ((int) $document->getAttribute('status')) {
                SuratJalan::STATUS_DRAFT => 'draft', SuratJalan::STATUS_ISSUED => 'issued', default => 'cancelled',
            }
        : strtolower((string) $document->getAttribute('status'));
        if (in_array($status, $rule[$action], true)) {
            return $open;
        }

        return $this->locked($rule['label'], $status, $action, $rule['correction']);
    }

    public function isLocked(Model $document, string $action = 'update'): bool
    {
        return $this->check($document, $action)['locked'];
    }

    /** Dipakai policy: flag mati → tidak pernah terkunci (perilaku lama); flag hidup → mengikuti matriks. */
    public static function blocks(Model $document, string $action = 'update'): bool
    {
        return self::enabled() && app(self::class)->isLocked($document, $action);
    }

    /** Pesan tunggal untuk penolakan (mis. di API/aksi). */
    public function message(Model $document, string $action = 'update'): ?string
    {
        $check = $this->check($document, $action);

        return $check['locked'] ? $check['reason'].' '.ucfirst((string) $check['correction']).'.' : null;
    }

    /** Penerimaan: hanya Draft (belum berjurnal) yang boleh diubah/dihapus; berjurnal → Batalkan Penerimaan. */
    private function checkReceipt(CustomerReceipt $receipt, string $action): array
    {
        $status = strtolower((string) $receipt->status);
        $hasJournal = $receipt->exists && $receipt->journalEntries()->exists();

        if ($status === 'draft' && ! $hasJournal) {
            return ['locked' => false, 'reason' => null, 'correction' => null];
        }

        if ($status === 'cancelled') {
            return $this->locked('Penerimaan', $status, $action, 'penerimaan yang dibatalkan tidak dapat diubah; catat penerimaan baru bila perlu');
        }

        return $this->locked('Penerimaan', $status ?: 'berjurnal', $action, 'gunakan aksi Batalkan Penerimaan (jurnal dibalik, piutang dikembalikan)');
    }

    private function locked(string $label, string $status, string $action, string $correction): array
    {
        $verb = $action === 'delete' ? 'dihapus' : 'diubah';

        return [
            'locked' => true,
            'reason' => "{$label} berstatus \"{$status}\" sudah diproses dan tidak dapat {$verb}.",
            'correction' => $correction,
        ];
    }
}
