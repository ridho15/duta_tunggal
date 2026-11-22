<?php

namespace App\Services;

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Scopes\CabangScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * VOID dokumen sumber dengan JURNAL PEMBALIK — pengganti `->journalEntries()->delete()`.
 *
 * Menghapus jurnal lewat query builder melewati event per model JournalEntry, sehingga kunci periode
 * (AccountingPeriod::ensureDateIsOpen) tidak berjalan dan jurnal di periode tertutup ikut terhapus.
 * Servis ini membiarkan jurnal asal tetap utuh (jejak audit) dan memposting jurnal pembalik
 * bertanggal periode TERBUKA, sehingga saldo bersih dokumen = 0 tanpa mengubah periode tertutup.
 *
 * Cara pakai di modul lain (observer / controller / service), di dalam DB::transaction yang sama dengan
 * perubahan status dokumen:
 *
 *     app(JournalReversalService::class)->reverseSource($payment, 'Pembayaran dibatalkan: salah rekening');
 *     // atau tanggal pembalik eksplisit (harus di periode terbuka):
 *     app(JournalReversalService::class)->reverseSource($payment, 'Koreksi', '2026-11-30');
 *
 *  - Tanggal default: tanggal jurnal asal bila periodenya masih terbuka; bila sudah tertutup → hari ini.
 *  - Tanggal pembalik di periode tertutup → ClosedPeriodException (pesan berisi sebab & solusi).
 *  - Idempoten: saldo bersih per akun dihitung dari jurnal asal + pembalik sebelumnya; dipanggil ulang
 *    setelah dokumen sudah di-void tidak memposting apa pun (mengembalikan koleksi kosong).
 *  - Baris pembalik memakai source_type/source_id dokumen yang sama, is_reversal = true,
 *    reversal_of_transaction_id = referensi jurnal asal, journal_type = journal_type asal.
 */
class JournalReversalService
{
    /**
     * @return Collection<int, JournalEntry> baris jurnal pembalik yang dibuat
     */
    public function reverseSource(Model $source, string $reason, string|CarbonInterface|null $reversalDate = null): Collection
    {
        return DB::transaction(function () use ($source, $reason, $reversalDate) {
            $entries = JournalEntry::withoutGlobalScope(CabangScope::class)
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $originals = $entries->where('is_reversal', false);
            if ($originals->isEmpty()) {
                return collect();
            }

            $netLines = $entries
                ->groupBy(fn (JournalEntry $e) => $e->coa_id . '|' . ($e->cabang_id ?? '') . '|' . ($e->journal_type ?? ''))
                ->map(function (Collection $group) {
                    $first = $group->first();

                    return [
                        'coa_id' => $first->coa_id,
                        'cabang_id' => $first->cabang_id,
                        'department_id' => $first->department_id,
                        'project_id' => $first->project_id,
                        'journal_type' => $first->journal_type,
                        'net' => round((float) $group->sum('debit') - (float) $group->sum('credit'), 2),
                    ];
                })
                ->filter(fn (array $line) => abs($line['net']) >= 0.005)
                ->values();

            if ($netLines->isEmpty()) {
                return collect();
            }

            $originalReference = (string) ($originals->first()->reference ?: class_basename($source) . '-' . $source->getKey());
            $date = $this->resolveReversalDate($originals, $reversalDate);

            foreach ($netLines->pluck('cabang_id')->unique() as $cabangId) {
                $this->ensureReversalDateOpen($date, $cabangId, $originalReference);
            }

            $reference = $this->uniqueReference('VOID-' . $originalReference);
            $description = '[VOID] ' . ($originals->first()->description ?: $originalReference) . ' — ' . $reason;

            return $netLines->map(fn (array $line) => JournalEntry::create([
                'coa_id' => $line['coa_id'],
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => $line['net'] < 0 ? abs($line['net']) : 0,
                'credit' => $line['net'] > 0 ? $line['net'] : 0,
                'journal_type' => $line['journal_type'],
                'cabang_id' => $line['cabang_id'],
                'department_id' => $line['department_id'],
                'project_id' => $line['project_id'],
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'is_reversal' => true,
                'reversal_of_transaction_id' => $originalReference,
                'created_by' => Auth::id(),
            ]));
        });
    }

    private function resolveReversalDate(Collection $originals, string|CarbonInterface|null $reversalDate): string
    {
        if ($reversalDate !== null) {
            return Carbon::parse($reversalDate)->toDateString();
        }

        $anyClosed = $originals->contains(
            fn (JournalEntry $e) => AccountingPeriod::closedPeriodCovering(Carbon::parse($e->date), $e->cabang_id) !== null
        );

        return $anyClosed
            ? now()->toDateString()
            : Carbon::parse($originals->max('date'))->toDateString();
    }

    private function ensureReversalDateOpen(string $date, ?int $cabangId, string $originalReference): void
    {
        if (AccountingPeriod::closedPeriodCovering(Carbon::parse($date), $cabangId) !== null) {
            throw new ClosedPeriodException(
                "Jurnal pembalik untuk {$originalReference} tidak dapat dibuat: tanggal {$date} berada di periode akuntansi yang sudah ditutup. "
                . 'Pilih tanggal pembalik pada periode yang masih terbuka, atau minta akuntansi membuka periode tersebut.'
            );
        }
    }

    private function uniqueReference(string $base): string
    {
        $base = mb_substr($base, 0, 240);
        $reference = $base;
        $suffix = 2;
        while (JournalEntry::withoutGlobalScopes()->where('reference', $reference)->exists()) {
            $reference = $base . '-' . $suffix++;
        }

        return $reference;
    }
}
