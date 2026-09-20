<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai akun kas/bank yang BOLEH menerima uang (chart_of_accounts.is_cash_bank).
 *
 *  - DEFAULT dry-run: hanya mengusulkan kandidat (akun detail aktif di bawah 1111 dan 1112 selain DEPOSITO/INVESTASI);
 *  - `--apply` menandai kandidat; `--codes=1111.01,1112.01.01` menandai HANYA kode tsb (hasil tinjauan akuntansi);
 *  - `--reset` (bersama --apply) lebih dulu menghapus semua penanda lama;
 *  - --apply menulis CSV sebelum/sesudah ke storage/app/backfill.
 *
 * WAJIB ditinjau akuntansi sebelum --apply: penanda menentukan akun mana yang bisa dipilih pada Penerimaan Customer.
 */
class FlagCashBankAccounts extends Command
{
    protected $signature = 'coa:flag-cash-bank
        {--apply : Terapkan penanda (default: dry-run, hanya melaporkan)}
        {--codes= : Tandai hanya kode akun ini (dipisah koma), hasil tinjauan akuntansi}
        {--reset : Hapus semua penanda is_cash_bank lebih dulu (dengan --apply)}';

    protected $description = 'Usulkan/tandai akun kas & bank penerima uang (is_cash_bank) — dry-run secara default';

    public function handle(): int
    {
        if (! Schema::hasColumn('chart_of_accounts', 'is_cash_bank')) {
            $this->error('Kolom chart_of_accounts.is_cash_bank belum ada. Jalankan dulu: php artisan migrate');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $only = collect(explode(',', (string) $this->option('codes')))->map(fn ($c) => trim($c))->filter()->values();

        $this->info('Penandaan akun kas/bank' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $candidates = ChartOfAccount::query()->cashBankCandidates()->orderBy('chart_of_accounts.code')
            ->get(['chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.is_cash_bank']);

        if ($only->isNotEmpty()) {
            $unknown = $only->diff($candidates->pluck('code'));
            if ($unknown->isNotEmpty()) {
                $this->error('Kode berikut bukan kandidat (bukan akun detail aktif 1111 dan 1112 selain DEPOSITO/INVESTASI): ' . $unknown->implode(', '));

                return self::FAILURE;
            }
            $candidates = $candidates->whereIn('code', $only->all())->values();
        }

        $flaggedNow = ChartOfAccount::query()->where('is_cash_bank', true)->orderBy('code')->get(['id', 'code', 'name']);

        $this->line('Sudah bertanda saat ini: ' . ($flaggedNow->isEmpty()
            ? 'tidak ada (penerimaan memakai kandidat sebagai jembatan)'
            : $flaggedNow->count() . ' akun'));

        $rows = $candidates->map(fn ($c) => [$c->code, $c->name, $c->is_cash_bank ? 'sudah bertanda' : 'akan ditandai'])->all();
        $this->table(['Kode', 'Nama', 'Tindakan'], $rows);

        $excluded = ChartOfAccount::query()
            ->where(function ($q) {
                $q->where('code', 'LIKE', ChartOfAccount::CASH_PREFIX . '%')->orWhere('code', 'LIKE', ChartOfAccount::BANK_PREFIX . '%');
            })
            ->whereNotIn('id', ChartOfAccount::query()->cashBankCandidates()->select('chart_of_accounts.id'))
            ->orderBy('code')
            ->get(['code', 'name']);
        if ($excluded->isNotEmpty()) {
            $this->line('Tidak diusulkan (akun induk / non-aktif / DEPOSITO / INVESTASI): ' . $excluded->map(fn ($c) => "{$c->code} {$c->name}")->implode('; '));
        }

        if (! $apply) {
            $this->warn('Dry-run selesai. Minta akuntansi meninjau daftar di atas, lalu jalankan --apply (atau --codes=… untuk daftar yang disetujui).');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/coa-cash-bank-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, ['coa_id', 'code', 'name', 'is_cash_bank_lama', 'is_cash_bank_baru']);

        DB::transaction(function () use ($candidates, $fh) {
            if ($this->option('reset')) {
                foreach (ChartOfAccount::query()->where('is_cash_bank', true)->get(['id', 'code', 'name']) as $old) {
                    fputcsv($fh, [$old->id, $old->code, $old->name, 1, 0]);
                }
                // Query builder: tanpa event/observer.
                DB::table('chart_of_accounts')->where('is_cash_bank', true)->update(['is_cash_bank' => false]);
            }

            foreach ($candidates as $coa) {
                fputcsv($fh, [$coa->id, $coa->code, $coa->name, (int) $coa->is_cash_bank, 1]);
                DB::table('chart_of_accounts')->where('id', $coa->id)->update(['is_cash_bank' => true]);
            }
        });
        fclose($fh);

        $this->info($candidates->count() . " akun ditandai. CSV sebelum/sesudah: {$path}");

        return self::SUCCESS;
    }
}
