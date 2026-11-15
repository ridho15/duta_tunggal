<?php

namespace App\Console\Commands;

use App\Models\PurchaseReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Membersihkan Retur Pembelian QC yang terduplikasi dari data lama (sebelum guard duplikat di
 * QualityControlService dipasang). Satu QC / satu penerimaan seharusnya hanya punya SATU retur.
 *
 * AMAN secara default (dry-run). Yang dihapus HANYA duplikat berstatus draft/menunggu — retur yang
 * sudah disetujui (punya jurnal & potong hutang) TIDAK PERNAH disentuh; grup yang punya >1 retur
 * approved hanya dilaporkan untuk ditinjau manual, tidak diubah.
 */
class PurgeDuplicateQcPurchaseReturns extends Command
{
    protected $signature = 'purchase-returns:dedup-qc
        {--apply : Soft-delete duplikat draft. Tanpa flag ini hanya simulasi.}
        {--no-csv : Jangan tulis berkas CSV audit.}';

    protected $description = 'Deteksi & bersihkan Retur Pembelian QC duplikat (dry-run kecuali --apply).';

    private const SAFE_TO_DELETE = ['draft', 'pending_approval', 'rejected', 'cancelled'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? '== MODE TERAPKAN (data akan diubah) ==' : '== MODE SIMULASI (dry-run, tidak ada perubahan) ==');

        // Kunci pengelompokan: QC yang sama, atau penerimaan yang sama (untuk retur QC tanpa quality_control_id).
        $returns = PurchaseReturn::query()
            ->whereNotNull('quality_control_id')
            ->orderBy('id')
            ->get(['id', 'nota_retur', 'quality_control_id', 'purchase_receipt_id', 'status', 'created_at']);

        $groups = $returns->groupBy('quality_control_id')->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Tidak ada Retur Pembelian QC yang terduplikasi. Tidak ada tindakan.');

            return self::SUCCESS;
        }

        $toDelete = [];
        $needsReview = [];
        $rows = [];

        foreach ($groups as $qcId => $group) {
            $approved = $group->filter(fn ($r) => ! in_array(strtolower((string) $r->status), self::SAFE_TO_DELETE, true));

            // Yang dipertahankan: retur approved pertama bila ada; selain itu retur tertua.
            $keep = $approved->first() ?? $group->first();

            if ($approved->count() > 1) {
                // Lebih dari satu retur yang sudah berdampak akuntansi — JANGAN otomatis hapus.
                $needsReview[$qcId] = $group;
                foreach ($group as $r) {
                    $rows[] = [$qcId, $r->nota_retur, $r->status, $r->id === $keep->id ? 'DIPERTAHANKAN' : 'TINJAU MANUAL (approved ganda)', $r->created_at];
                }

                continue;
            }

            foreach ($group as $r) {
                if ($r->id === $keep->id) {
                    $rows[] = [$qcId, $r->nota_retur, $r->status, 'DIPERTAHANKAN', $r->created_at];

                    continue;
                }
                if (in_array(strtolower((string) $r->status), self::SAFE_TO_DELETE, true)) {
                    $toDelete[] = $r;
                    $rows[] = [$qcId, $r->nota_retur, $r->status, $apply ? 'DIHAPUS' : 'AKAN DIHAPUS', $r->created_at];
                } else {
                    $rows[] = [$qcId, $r->nota_retur, $r->status, 'DILEWATI (sudah approved, tidak disentuh)', $r->created_at];
                }
            }
        }

        $this->table(['QC ID', 'Nota Retur', 'Status', 'Tindakan', 'Dibuat'], $rows);
        $this->line(sprintf('Grup duplikat: %d | Akan dihapus: %d | Perlu tinjau manual: %d',
            $groups->count(), count($toDelete), count($needsReview)));

        if (! $this->option('no-csv')) {
            $csv = "qc_id,nota_retur,status,tindakan,dibuat\n";
            foreach ($rows as $r) {
                $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r))."\n";
            }
            $path = 'audits/dedup-qc-purchase-returns-'.now()->format('Ymd-His').'.csv';
            Storage::disk('local')->put($path, $csv);
            $this->info('CSV audit: storage/app/'.$path);
        }

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply untuk menghapus duplikat draft.');

            return self::SUCCESS;
        }

        if (empty($toDelete)) {
            $this->info('Tidak ada duplikat draft yang bisa dihapus.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toDelete) {
            foreach ($toDelete as $r) {
                $r->delete(); // soft delete (model pakai SoftDeletes); item ikut terhapus via hook deleting.
            }
        });

        $this->info(count($toDelete).' Retur Pembelian duplikat (draft) berhasil di-soft-delete.');

        return self::SUCCESS;
    }
}
