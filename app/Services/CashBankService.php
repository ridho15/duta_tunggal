<?php

namespace App\Services;

use App\Events\TransferPosted;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use App\Traits\JournalValidationTrait;

class CashBankService
{
    use JournalValidationTrait;
    public function generateNumber(string $prefix = 'CB', string $format = 'default'): string
    {
        $date = now()->format('Ymd');
        $base = $prefix;
        switch ($format) {
            case 'simple':
                $base = $prefix;
                break;
            case 'monthly':
                $base = $prefix . '-' . now()->format('Ym');
                break;
            case 'yearly':
                $base = $prefix . '-' . now()->format('Y');
                break;
            case 'custom':
                $base = $prefix . '-' . $date;
                break;
            default:
                $base = $prefix . '-' . $date;
        }

        $prefixFull = $base . '-';
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefixFull . $random;
            $exists = CashBankTransaction::where('number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public function generateTransferNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'TRF-' . $date . '-';
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = CashBankTransfer::where('number', $candidate)->exists();
        } while ($exists);
        return $candidate;
    }

    public function postTransaction(CashBankTransaction $trx): void
    {
        DB::transaction(function () use ($trx) {
            $entries = [];
            $isIn = in_array($trx->type, ['cash_in', 'bank_in']);

            // Clear previous entries for idempotent posting
            JournalEntry::where('source_type', CashBankTransaction::class)
                ->where('source_id', $trx->id)
                ->where('journal_type', 'cashbank')
                ->delete();

            // If transaction has details (breakdown to child accounts), use them
            if ($trx->transactionDetails->isNotEmpty()) {
                $totalAmount = $trx->transactionDetails->sum('amount');

                // Validate total matches transaction amount
                if (abs($totalAmount - $trx->amount) > 0.01) {
                    throw new \Exception('Total rincian pembayaran (' . number_format($totalAmount) . ') tidak sama dengan jumlah transaksi (' . number_format($trx->amount) . ')');
                }

                // For transactions with breakdown, handle positive and negative amounts
                if ($isIn) {
                    // Penerimaan: Dr Main Account (Kas/Bank), Cr Breakdown Accounts (Pendapatan)
                    $entries[] = $this->createEntry($trx->account_coa_id, $trx, debit: $trx->amount, credit: 0, type: 'cashbank');
                    foreach ($trx->transactionDetails as $detail) {
                        // For negative amounts (tax reductions), reverse the debit/credit
                        if ($detail->amount < 0) {
                            // Negative amount: Dr Main Account, Cr Breakdown Account (reduces income)
                            $entries[] = $this->createEntry($detail->chart_of_account_id, $trx, debit: abs($detail->amount), credit: 0, type: 'cashbank', description: $detail->description);
                        } else {
                            // Positive amount: Dr Main Account, Cr Breakdown Account (increases income)
                            $entries[] = $this->createEntry($detail->chart_of_account_id, $trx, debit: 0, credit: $detail->amount, type: 'cashbank', description: $detail->description);
                        }
                    }
                } else {
                    // Pengeluaran: Dr Breakdown Accounts (Beban), Cr Main Account (Kas/Bank)
                    foreach ($trx->transactionDetails as $detail) {
                        // For negative amounts (tax reductions), reverse the debit/credit
                        if ($detail->amount < 0) {
                            // Negative amount: Cr Breakdown Account, Dr Main Account (reduces expense)
                            $entries[] = $this->createEntry($detail->chart_of_account_id, $trx, debit: 0, credit: abs($detail->amount), type: 'cashbank', description: $detail->description);
                        } else {
                            // Positive amount: Dr Breakdown Account, Cr Main Account (increases expense)
                            $entries[] = $this->createEntry($detail->chart_of_account_id, $trx, debit: $detail->amount, credit: 0, type: 'cashbank', description: $detail->description);
                        }
                    }
                    $entries[] = $this->createEntry($trx->account_coa_id, $trx, debit: 0, credit: $trx->amount, type: 'cashbank');
                }
            } else {
                // Original logic for transactions without breakdown
                // Journal: Penerimaan -> Dr Kas/Bank, Cr Lawan; Pengeluaran -> Dr Lawan, Cr Kas/Bank
                if ($isIn) {
                    $entries[] = $this->createEntry($trx->account_coa_id, $trx, debit: $trx->amount, credit: 0, type: 'cashbank');
                    $entries[] = $this->createEntry($trx->offset_coa_id, $trx, debit: 0, credit: $trx->amount, type: 'cashbank');
                } else {
                    $entries[] = $this->createEntry($trx->offset_coa_id, $trx, debit: $trx->amount, credit: 0, type: 'cashbank');
                    $entries[] = $this->createEntry($trx->account_coa_id, $trx, debit: 0, credit: $trx->amount, type: 'cashbank');
                }
            }

            // Validate that entries are balanced
            $this->validateJournalEntries($entries);
        });
    }

    public function postTransfer(CashBankTransfer $trf): void
    {
        DB::transaction(function () use ($trf) {
            // Clear previous entries for idempotent posting
            JournalEntry::where('source_type', CashBankTransfer::class)
                ->where('source_id', $trf->id)
                ->where('journal_type', 'transfer')
                ->delete();
            
            $amount = (float) ($trf->amount ?? 0);
            $otherCosts = (float) ($trf->other_costs ?? 0);
            $total = $amount + $otherCosts;
            
            // Direct transfer: Cr From (total), Dr To (amount)
            $this->createEntry($trf->from_coa_id, $trf, debit: 0, credit: $total, type: 'transfer');
            $this->createEntry($trf->to_coa_id, $trf, debit: $amount, credit: 0, type: 'transfer');
            
            // Jika ada biaya lain-lain, posting debit ke COA biaya yang ditentukan
            if ($otherCosts > 0) {
                $biayaAdminCoaId = $trf->other_costs_coa_id ?? \App\Models\ChartOfAccount::where('code', '8000.01')->first()->id ?? null;
                if ($biayaAdminCoaId) {
                    $this->createEntry(
                        $biayaAdminCoaId, 
                        $trf, 
                        debit: $otherCosts, 
                        credit: 0, 
                        type: 'transfer'
                    );
                }
            }
            
            $trf->status = 'posted';
            $trf->save();
        });

        // Dispatch event for auto reconciliation
        TransferPosted::dispatch($trf);
    }

    private function createEntry(int $coaId, object $source, float $debit, float $credit, string $type, string $description = null): JournalEntry
    {
        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($source);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($source);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($source);

        return JournalEntry::create([
            'coa_id' => $coaId,
            'date' => ($source->date ?? now())->toDateString(),
            'reference' => $source->number ?? $source->reference ?? null,
            'description' => $description ?? $source->description ?? null,
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => $type,
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => get_class($source),
            'source_id' => $source->id,
        ]);
    }
}
