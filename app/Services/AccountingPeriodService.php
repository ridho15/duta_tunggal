<?php

namespace App\Services;

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Scopes\CabangScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    public function createPeriod(string|Carbon $periodStart, string|Carbon $periodEnd, ?int $cabangId = null): AccountingPeriod
    {
        $start = $periodStart instanceof Carbon ? $periodStart->toDateString() : $periodStart;
        $end = $periodEnd instanceof Carbon ? $periodEnd->toDateString() : $periodEnd;

        if ($start > $end) {
            throw new \InvalidArgumentException('Tanggal period_start harus <= period_end.');
        }

        $this->ensureNoOverlap($start, $end, $cabangId);

        return AccountingPeriod::create([
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'open',
            'cabang_id' => $cabangId,
        ]);
    }

    public function closePeriod(AccountingPeriod $period, ?int $closedBy = null, bool $generateClosingEntries = true): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $closedBy, $generateClosingEntries) {
            $lockedPeriod = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($lockedPeriod->status === 'closed') {
                return $lockedPeriod;
            }

            if ($generateClosingEntries) {
                $this->postClosingEntries($lockedPeriod);
            }

            $lockedPeriod->update([
                'status' => 'closed',
                'closed_by' => $closedBy,
                'closed_at' => now(),
            ]);

            return $lockedPeriod->fresh();
        });
    }

    public function reopenPeriod(AccountingPeriod $period): AccountingPeriod
    {
        return DB::transaction(function () use ($period) {
            $lockedPeriod = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $lockedPeriod->update([
                'status' => 'open',
                'closed_by' => null,
                'closed_at' => null,
            ]);

            JournalEntry::withoutGlobalScopes()
                ->where('source_type', AccountingPeriod::class)
                ->where('source_id', $period->id)
                ->where('journal_type', 'closing')
                ->delete();

            return $lockedPeriod->fresh();
        });
    }

    public function postClosingEntries(AccountingPeriod $period): int
    {
        if ($period->status === 'closed') {
            throw new ClosedPeriodException('Periode sudah ditutup. Tidak dapat membuat closing entries lagi.');
        }

        // Hapus closing entries lama untuk periode ini jika ada (idempotency)
        JournalEntry::withoutGlobalScopes()
            ->where('source_type', AccountingPeriod::class)
            ->where('source_id', $period->id)
            ->where('journal_type', 'closing')
            ->delete();

        $retainedEarningsAccount = $this->resolveRetainedEarningsAccount();
        $journalDate = Carbon::parse($period->period_end)->toDateString();
        $reference = 'CLOSE-' . Carbon::parse($period->period_end)->format('Ym') . '-' . ($period->cabang_id ?? 'ALL');
        $transactionId = 'CLOSE-' . $period->id;
        $createdCount = 0;

        // Tutup SEMUA saldo akun nominal (pendapatan, beban, termasuk akun kontra seperti retur/potongan
        // penjualan bertipe Revenue bersaldo debit) apa pun tandanya, sehingga saldo nominal = 0 setelah closing.
        $nominalAccounts = ChartOfAccount::query()->whereIn('type', ['Revenue', 'Expense'])->orderBy('code')->get();

        foreach ($nominalAccounts as $account) {
            // Saldo bertanda dalam sisi debit: positif = saldo debit, negatif = saldo kredit.
            $balance = $this->getAccountBalanceForPeriod($account->id, $period, $account->type);
            $debitBalance = $account->type === 'Revenue' ? -$balance : $balance;
            if (abs($debitBalance) < 0.005) {
                continue;
            }

            $amount = round(abs($debitBalance), 2);
            $closeAccountSide = $debitBalance > 0 ? 'credit' : 'debit';
            $label = $account->type === 'Revenue' ? 'pendapatan' : 'beban';

            foreach ([
                [$account->id, 'Closing Entry - Tutup akun ' . $label, $closeAccountSide],
                [$retainedEarningsAccount->id, 'Closing Entry - Saldo laba ditahan dari ' . $label, $closeAccountSide === 'credit' ? 'debit' : 'credit'],
            ] as [$coaId, $description, $side]) {
                JournalEntry::create([
                    'coa_id' => $coaId,
                    'date' => $journalDate,
                    'reference' => $reference,
                    'description' => $description,
                    'debit' => $side === 'debit' ? $amount : 0,
                    'credit' => $side === 'credit' ? $amount : 0,
                    'journal_type' => 'closing',
                    'cabang_id' => $period->cabang_id,
                    'source_type' => AccountingPeriod::class,
                    'source_id' => $period->id,
                    'transaction_id' => $transactionId,
                ]);
            }

            $createdCount += 2;
        }

        return $createdCount;
    }

    protected function getAccountBalanceForPeriod(int $coaId, AccountingPeriod $period, string $accountType): float
    {
        $query = JournalEntry::query()
            ->withoutGlobalScope(CabangScope::class)
            ->where('coa_id', $coaId)
            ->where('journal_type', '!=', 'closing')
            ->whereBetween('date', [
                Carbon::parse($period->period_start)->toDateString(),
                Carbon::parse($period->period_end)->toDateString(),
            ]);

        if ($period->cabang_id) {
            $query->where('cabang_id', $period->cabang_id);
        }

        $totalDebit = (float) $query->sum('debit');
        $totalCredit = (float) $query->sum('credit');

        // Saldo bertanda (tanpa pemotongan ke 0): Revenue → kredit − debit; selain itu → debit − kredit.
        return $accountType === 'Revenue'
            ? $totalCredit - $totalDebit
            : $totalDebit - $totalCredit;
    }

    protected function resolveRetainedEarningsAccount(): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->where('type', 'Equity')
            ->where(function ($query) {
                $query->where('code', '3400')
                    ->orWhereRaw('LOWER(name) like ?', ['%laba ditahan%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%retained%']);
            })
            ->first();

        if (!$account) {
            throw new \RuntimeException('Akun laba ditahan tidak ditemukan.');
        }

        return $account;
    }

    protected function ensureNoOverlap(string $periodStart, string $periodEnd, ?int $cabangId): void
    {
        $overlapExists = AccountingPeriod::query()
            ->withoutGlobalScope(CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('period_start', [$periodStart, $periodEnd])
                    ->orWhereBetween('period_end', [$periodStart, $periodEnd])
                    ->orWhere(function ($nested) use ($periodStart, $periodEnd) {
                        $nested->where('period_start', '<=', $periodStart)
                            ->where('period_end', '>=', $periodEnd);
                    });
            })
            ->exists();

        if ($overlapExists) {
            throw new \RuntimeException('Periode akuntansi overlap dengan periode yang sudah ada.');
        }
    }
}
