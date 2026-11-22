<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan data deposit customer lama (audit siklus penjualan #1–#3). DEFAULT dry-run; perubahan hanya dengan --apply.
 *
 *  1. deposits.coa_id customer yang berisi akun KAS/BANK → dipindah ke payment_coa_id (bila kosong) dan coa_id = Deposit Pelanggan;
 *  2. jurnal pemakaian deposit penerimaan customer yang mendebit akun selain Deposit Pelanggan → jurnal koreksi
 *     Dr Deposit Pelanggan / Cr akun yang salah (jurnal lama tidak diubah/dihapus; idempoten lewat reference FIX-DEP-{id});
 *  3. LAPORAN saja (tanpa perubahan): selisih Σ sisa deposit customer vs saldo GL Deposit Pelanggan, serta pemotongan saldo
 *     verifikasi kasir COD lama (log `use` bereferensi SO tanpa jurnal) — butuh penelusuran akuntan per customer.
 */
class FixCustomerDepositAccounts extends Command
{
    protected $signature = 'sales:fix-customer-deposit-accounts {--apply : Terapkan perubahan (default: dry-run, hanya melaporkan)}';

    protected $description = 'Perbaiki akun deposit customer lama (coa_id kas) & jurnal pemakaian deposit yang mendebit kas (dry-run secara default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $depositAccount = Deposit::customerDepositAccount();
        if (! $depositAccount) {
            $this->error('Akun Deposit Pelanggan ('.config('coa.customer_deposit').') tidak ditemukan. Tambahkan akun tersebut di Chart of Account lalu jalankan ulang.');

            return self::FAILURE;
        }

        $this->info('Perbaikan deposit customer'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]')." — akun Deposit Pelanggan: {$depositAccount->code} {$depositAccount->name}");

        $deposits = $this->misclassifiedDeposits($depositAccount);
        $this->line("1) Deposit customer dengan coa_id akun kas/bank: {$deposits->count()}");
        if ($deposits->isNotEmpty()) {
            $this->table(['ID', 'Nomor', 'coa_id (kas)', 'payment_coa_id', 'Sisa'], $deposits->map(fn (Deposit $d) => [
                $d->id, $d->deposit_number, $d->coa?->code, $d->paymentCoa?->code ?: '-', number_format((float) $d->remaining_amount, 2, ',', '.'),
            ])->all());
        }

        $journals = $this->misclassifiedUsageJournals($depositAccount);
        $this->line("2) Jurnal pemakaian deposit yang mendebit akun selain {$depositAccount->code}: {$journals->count()} (total Rp ".number_format((float) $journals->sum('debit'), 2, ',', '.').')');
        if ($journals->isNotEmpty()) {
            $this->table(['Jurnal', 'Penerimaan', 'Tanggal', 'Akun salah', 'Debit'], $journals->map(fn (JournalEntry $e) => [
                $e->id, $e->reference, substr((string) $e->date, 0, 10), $e->coa?->code, number_format((float) $e->debit, 2, ',', '.'),
            ])->all());
        }

        if ($apply) {
            DB::transaction(function () use ($deposits, $journals, $depositAccount) {
                foreach ($deposits as $deposit) {
                    $deposit->forceFill([
                        'payment_coa_id' => $deposit->payment_coa_id ?: $deposit->coa_id,
                        'coa_id' => $depositAccount->id,
                    ])->saveQuietly();
                }

                foreach ($journals as $entry) {
                    $base = [
                        'date' => now()->toDateString(), 'reference' => "FIX-DEP-{$entry->id}", 'journal_type' => 'adjustment',
                        'cabang_id' => $entry->cabang_id, 'source_type' => CustomerReceipt::class, 'source_id' => $entry->source_id,
                        'description' => "Koreksi akun pemakaian deposit customer (jurnal #{$entry->id} mendebit akun kas, seharusnya Deposit Pelanggan)",
                    ];
                    JournalEntry::create($base + ['coa_id' => $depositAccount->id, 'debit' => $entry->debit, 'credit' => 0]);
                    JournalEntry::create($base + ['coa_id' => $entry->coa_id, 'debit' => 0, 'credit' => $entry->debit]);
                }
            });
            $this->info("Diterapkan: {$deposits->count()} deposit, {$journals->count()} jurnal koreksi.");
        }

        $this->reportBalances($depositAccount);

        return self::SUCCESS;
    }

    private function misclassifiedDeposits(ChartOfAccount $depositAccount)
    {
        return Deposit::query()->with(['coa', 'paymentCoa'])
            ->where('from_model_type', Customer::class)
            ->where('coa_id', '!=', $depositAccount->id)
            ->whereHas('coa', fn ($q) => $q->where('type', '!=', 'Liability'))
            ->orderBy('id')->get();
    }

    private function misclassifiedUsageJournals(ChartOfAccount $depositAccount)
    {
        $fixed = JournalEntry::query()->where('reference', 'like', 'FIX-DEP-%')->pluck('reference')
            ->map(fn ($ref) => (int) substr((string) $ref, 8))->all();

        return JournalEntry::query()->with('coa')
            ->where('source_type', CustomerReceipt::class)
            ->where('description', 'like', 'Deposit / Uang Muka usage for receipt id%')
            ->where('coa_id', '!=', $depositAccount->id)
            ->where('debit', '>', 0)
            ->whereNotIn('id', $fixed)
            ->orderBy('id')->get();
    }

    private function reportBalances(ChartOfAccount $depositAccount): void
    {
        $subledger = round((float) Deposit::query()->where('from_model_type', Customer::class)->sum('remaining_amount'), 2);
        $gl = round(-(float) JournalEntry::query()->where('coa_id', $depositAccount->id)->sum(DB::raw('debit - credit')), 2);
        $this->line(sprintf('3) Σ sisa deposit customer Rp %s vs saldo GL %s Rp %s → selisih Rp %s (hanya laporan).',
            number_format($subledger, 2, ',', '.'), $depositAccount->code, number_format($gl, 2, ',', '.'), number_format($subledger - $gl, 2, ',', '.')));

        $legacyCod = DepositLog::query()->where('type', 'use')->where('reference_type', SaleOrder::class)->count();
        if ($legacyCod > 0) {
            $this->warn("   {$legacyCod} log verifikasi kasir COD lama memotong saldo tanpa jurnal/pelunasan piutang; telusuri per SO (lunasi piutang invoice SO tsb dengan Penerimaan metode Deposit setelah saldo dikembalikan).");
        }
        if (abs($subledger - $gl) > 0.01) {
            $this->warn('   Selisih dapat berasal dari pemotongan deposit ganda/pembayaran tunai yang memotong deposit (temuan #2) — perlu rekonsiliasi manual per customer.');
        }
    }
}
