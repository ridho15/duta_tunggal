<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\Rak;
use App\Models\Scopes\CabangScope;
use App\Models\TaxSetting;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Checklist kesiapan master data sebelum UAT / go-live.
 *
 * KRITIS  : alur penjualan tidak dapat berjalan tanpanya (gudang, akun kas/bank, IDR, PPN aktif).
 *            Master per cabang: status "sebagian" (ada, tetapi sebagian cabang belum punya) tidak memblokir
 *            tetapi dilaporkan per cabang; hanya "kosong" total yang memblokir.
 * PERINGATAN: dapat diakali (driver/kendaraan -> gunakan metode Ekspedisi; rak) tetapi perlu diketahui.
 *
 * Akun kas/bank memakai heuristik yang sama dengan Penerimaan Customer (kode 111*, nama kas/bank/...)
 * sampai flag master COA `is_cash_bank` tersedia (Fase 5A).
 */
class MasterDataReadiness
{
    public const CRITICAL = 'kritis';

    public const WARNING = 'peringatan';

    /**
     * @return array{checks: array<int, array<string, mixed>>, ready: bool, cabang: string|null}
     */
    public function check(?int $cabangId = null): array
    {
        $cabangs = Cabang::query()
            ->where('status', 1)
            ->when($cabangId, fn ($q) => $q->whereKey($cabangId))
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama']);

        $checks = [
            $this->perCabang('gudang', 'Gudang', self::CRITICAL, Warehouse::withoutGlobalScope(CabangScope::class), $cabangs, 'Tambahkan di Master Gudang.'),
            $this->global('rak', 'Rak', self::WARNING, Rak::query()->count(), 'Tambahkan di Master Rak (per gudang).'),
            $this->perCabang('driver', 'Driver', self::WARNING, Driver::withoutGlobalScope(CabangScope::class), $cabangs, 'Tambahkan di Master Driver, atau gunakan metode Ekspedisi pada Jadwal Pengiriman.'),
            $this->perCabang('kendaraan', 'Kendaraan', self::WARNING, Vehicle::withoutGlobalScope(CabangScope::class), $cabangs, 'Tambahkan di Master Kendaraan, atau gunakan metode Ekspedisi pada Jadwal Pengiriman.'),
            $this->cashBankCheck(),
            $this->global('mata_uang_idr', 'Mata uang IDR', self::CRITICAL, Currency::query()->where('code', 'IDR')->count(), 'Tambahkan mata uang IDR (kurs 1).'),
            $this->global('pajak_ppn', 'Tarif PPN aktif', self::CRITICAL, TaxSetting::query()->where('type', 'PPN')->where('status', true)->where('effective_date', '<=', now())->count(), 'Tambahkan tarif PPN aktif di Pengaturan Pajak.'),
        ];

        // T3.4 (flag accounting_settings): "siap" hanya bila SEMUA kunci Pengaturan Akuntansi terisi eksplisit dan sah.
        if (AccountingSettings::enabled()) {
            $checks[] = $this->accountingSettingsCheck();
        }

        return [
            'checks' => $checks,
            'ready' => collect($checks)->where('severity', self::CRITICAL)->every(fn ($c) => $c['ok']),
            'cabang' => $cabangId ? optional($cabangs->first())->nama : null,
        ];
    }

    protected function global(string $key, string $label, string $severity, int $count, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'count' => $count,
            'state' => $count > 0 ? 'siap' : 'kosong',
            'ok' => $count > 0,
            'missing_cabang' => [],
            'hint' => $hint,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query  query master (tanpa CabangScope)
     * @param  Collection<int, Cabang>  $cabangs  cabang aktif yang diperiksa
     */
    protected function perCabang(string $key, string $label, string $severity, $query, Collection $cabangs, string $hint): array
    {
        $countsByCabang = (clone $query)->selectRaw('cabang_id, count(*) as total')->groupBy('cabang_id')->pluck('total', 'cabang_id');

        $missing = $cabangs
            ->filter(fn (Cabang $cabang) => (int) ($countsByCabang[$cabang->id] ?? 0) === 0)
            ->map(fn (Cabang $cabang) => "({$cabang->kode}) {$cabang->nama}")
            ->values()
            ->all();

        $total = $cabangs->sum(fn (Cabang $cabang) => (int) ($countsByCabang[$cabang->id] ?? 0));

        // siap: semua cabang punya; sebagian: ada, tetapi beberapa cabang belum (tidak memblokir); kosong: tidak ada sama sekali.
        $state = $total === 0 ? 'kosong' : ($missing === [] ? 'siap' : 'sebagian');

        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'count' => (int) $total,
            'state' => $state,
            'ok' => $state !== 'kosong',
            'missing_cabang' => $missing,
            'hint' => $hint,
        ];
    }

    /**
     * Akun penerima uang: yang bertanda `is_cash_bank`; bila belum ada satu pun yang ditandai, kandidat
     * dihitung tetapi statusnya "sebagian" (perlu ditinjau akuntansi lalu ditandai lewat coa:flag-cash-bank).
     */
    protected function cashBankCheck(): array
    {
        $check = $this->global(
            'coa_kas_bank',
            'Akun Kas/Bank penerima uang',
            self::CRITICAL,
            ChartOfAccount::query()->cashBank()->count(),
            'Lengkapi Chart of Account (akun detail kode 1111*/1112*) lalu tandai lewat php artisan coa:flag-cash-bank.'
        );

        if ($check['ok'] && ! ChartOfAccount::hasCashBankFlags()) {
            $check['state'] = 'sebagian';
            $check['hint'] = 'Belum ada akun bertanda is_cash_bank: penerimaan customer memakai kandidat sementara. Tinjau daftarnya (php artisan coa:flag-cash-bank) bersama akuntansi, lalu jalankan dengan --apply.';
        }

        return $check;
    }

    /** Pengaturan Akuntansi: kunci wajib terisi & sah (akun detail, aktif, tipe sesuai). Kunci kosong memakai akun bawaan (peringatan). */
    protected function accountingSettingsCheck(): array
    {
        $rows = app(AccountingSettings::class)->status();
        $missing = collect($rows)->filter(fn ($row) => ! $row['ready'])->map(fn ($row) => $row['label'])->values()->all();
        $filled = count($rows) - count($missing);

        return [
            'key' => 'pengaturan_akuntansi',
            'label' => 'Pengaturan Akuntansi (akun jurnal penjualan)',
            'severity' => self::WARNING,
            'count' => $filled,
            'state' => $missing === [] ? 'siap' : ($filled === 0 ? 'kosong' : 'sebagian'),
            'ok' => $missing === [],
            'missing_cabang' => [],
            'hint' => $missing === []
                ? 'Semua kunci akun terisi.'
                : 'Belum diisi (memakai akun bawaan): '.implode(', ', $missing).'. Atur di Pengaturan → Pengaturan Akuntansi.',
        ];
    }
}
