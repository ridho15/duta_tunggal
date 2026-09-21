<?php

namespace App\Services;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan Akuntansi (T3.4, D9/D30): kunci akun alur penjualan → akun COA.
 *
 * Urutan resolusi kode akun untuk sebuah kunci: [akun pengaturan (bila flag `sales.controls.accounting_settings` hidup dan valid)]
 * → awalan bawaan → `config/coa.php` → kode bawaan. Tanpa pengaturan/flag mati hasilnya IDENTIK dengan perilaku sebelum T3.4,
 * sehingga tidak ada perubahan mendadak pada jurnal. Akun pengaturan harus: ada, aktif, akun DETAIL (bukan induk), bertipe sesuai.
 */
class AccountingSettings
{
    /**
     * @var array<string, array{label: string, types: array<int, string>, config: string|null, prefix: array<int, string>, fallbacks: array<int, string>, editable: bool, inherits: string|null, hint: string}>
     */
    public const DEFINITIONS = [
        'accounts_receivable' => ['label' => 'Piutang Dagang', 'types' => ['Asset'], 'config' => 'accounts_receivable', 'prefix' => [], 'fallbacks' => ['1120'], 'editable' => true, 'inherits' => null, 'hint' => 'Dikredit saat penerimaan, didebit saat invoice'],
        'sales_revenue' => ['label' => 'Penjualan (Pendapatan)', 'types' => ['Revenue'], 'config' => 'sales_revenue', 'prefix' => [], 'fallbacks' => ['4000', '4111'], 'editable' => true, 'inherits' => null, 'hint' => 'Pendapatan invoice penjualan'],
        'sales_discount' => ['label' => 'Diskon Penjualan', 'types' => ['Expense', 'Revenue', 'Contra Asset'], 'config' => 'sales_discount', 'prefix' => [], 'fallbacks' => ['4100.01'], 'editable' => true, 'inherits' => null, 'hint' => 'Diskon pada invoice'],
        'sales_returns' => ['label' => 'Retur Penjualan', 'types' => ['Expense', 'Revenue', 'Contra Asset'], 'config' => null, 'prefix' => [], 'fallbacks' => ['4120.10'], 'editable' => true, 'inherits' => null, 'hint' => 'Retur & Nota Kredit'],
        'sales_output_vat' => ['label' => 'PPN Keluaran', 'types' => ['Liability'], 'config' => 'sales_output_vat', 'prefix' => [], 'fallbacks' => ['2120.06'], 'editable' => true, 'inherits' => null, 'hint' => 'PPN pada invoice penjualan'],
        'customer_deposit' => ['label' => 'Deposit Customer', 'types' => ['Liability'], 'config' => 'customer_deposit', 'prefix' => [], 'fallbacks' => [], 'editable' => true, 'inherits' => null, 'hint' => 'Uang muka / deposit pelanggan'],
        'goods_in_transit' => ['label' => 'Barang Terkirim (Barang dalam Pengiriman)', 'types' => ['Asset'], 'config' => null, 'prefix' => [], 'fallbacks' => ['1140.20', '1180.10'], 'editable' => true, 'inherits' => null, 'hint' => 'Didebit saat DO selesai'],
        'cogs' => ['label' => 'HPP (Harga Pokok Penjualan)', 'types' => ['Expense'], 'config' => null, 'prefix' => [], 'fallbacks' => ['5100.10', '5000'], 'editable' => true, 'inherits' => null, 'hint' => 'HPP saat invoice diterbitkan'],
        'inventory' => ['label' => 'Persediaan Barang Dagangan', 'types' => ['Asset'], 'config' => 'inventory', 'prefix' => [], 'fallbacks' => ['1140.01'], 'editable' => true, 'inherits' => null, 'hint' => 'Dikredit saat barang keluar'],
        'cash_bank_default' => ['label' => 'Kas / Bank default penerimaan', 'types' => ['Asset'], 'config' => 'cash_and_bank', 'prefix' => [], 'fallbacks' => [], 'editable' => true, 'inherits' => null, 'hint' => 'Default akun penerima uang (pengguna tetap memilih akun kas/bank sah)'],
        'sales_shipping' => ['label' => 'Biaya Pengiriman Penjualan', 'types' => ['Expense'], 'config' => 'sales_shipping', 'prefix' => [], 'fallbacks' => ['6100.02'], 'editable' => true, 'inherits' => null, 'hint' => 'Ongkir pada invoice'],
        // Daftar internal (tidak dapat diatur sendiri; mengikuti kunci induknya) — mempertahankan urutan kode bawaan retur customer
        'return_inventory' => ['label' => 'Persediaan retur', 'types' => ['Asset'], 'config' => 'inventory', 'prefix' => ['1101.01'], 'fallbacks' => ['1140.10', '1100'], 'editable' => false, 'inherits' => 'inventory', 'hint' => ''],
        'return_wip' => ['label' => 'Akun barang dalam perbaikan (retur)', 'types' => ['Asset'], 'config' => null, 'prefix' => [], 'fallbacks' => ['1101.02', '1-201', '1140.02'], 'editable' => false, 'inherits' => null, 'hint' => ''],
    ];

    public static function enabled(): bool
    {
        return (bool) config('sales.controls.accounting_settings', false);
    }

    /** @return array<string, array<string, mixed>> hanya kunci yang dapat diatur */
    public static function editableKeys(): array
    {
        return array_filter(self::DEFINITIONS, fn ($definition) => $definition['editable']);
    }

    /** Akun pengaturan (valid) untuk kunci ini bila flag hidup; selain itu null. */
    public function explicit(string $key): ?ChartOfAccount
    {
        if (! self::enabled() || ! Schema::hasTable('accounting_settings')) {
            return null;
        }

        $definition = self::DEFINITIONS[$key] ?? null;
        $lookupKey = $definition['inherits'] ?? $key;
        $setting = AccountingSetting::query()->where('key', $lookupKey)->first();
        if (! $setting || ! $setting->coa_id) {
            return null;
        }

        $coa = ChartOfAccount::query()->find($setting->coa_id);
        $error = $coa ? $this->problem($lookupKey, $coa) : 'Akun tidak ditemukan.';

        return $error === null ? $coa : null;   // pengaturan yang menjadi tidak valid (akun dinonaktifkan/dijadikan induk) diabaikan → fallback
    }

    /**
     * Kode akun berurutan untuk kunci: [pengaturan] → awalan → config → kode bawaan.
     *
     * @return array<int, string>
     */
    public function codes(string $key): array
    {
        $definition = self::DEFINITIONS[$key] ?? ['config' => null, 'prefix' => [], 'fallbacks' => []];

        return array_values(array_unique(array_filter([
            $this->explicit($key)?->code,
            ...$definition['prefix'],
            $definition['config'] ? config('coa.'.$definition['config']) : null,
            ...$definition['fallbacks'],
        ])));
    }

    /** Akun menurut URUTAN PREFERENSI kode (pengaturan lebih dulu); mempertahankan pola "kode A, jika tidak ada kode B". */
    public function first(string $key): ?ChartOfAccount
    {
        foreach ($this->codes($key) as $code) {
            if ($account = ChartOfAccount::where('code', $code)->first()) {
                return $account;
            }
        }

        return null;
    }

    /** Akun pengaturan; bila tidak ada, `whereIn(kode)->first()` (urutan DB) — mempertahankan pola lama DO/persediaan. */
    public function anyOf(string $key): ?ChartOfAccount
    {
        return $this->explicit($key) ?? ChartOfAccount::whereIn('code', $this->codes($key))->first();
    }

    /** Id akun menurut urutan preferensi (untuk nilai bawaan form). */
    public function idFor(string $key): ?int
    {
        return $this->first($key)?->id;
    }

    /** Pesan galat bila $coa tidak sah untuk kunci; null = sah. */
    public function problem(string $key, ChartOfAccount $coa): ?string
    {
        $definition = self::DEFINITIONS[$key] ?? null;

        if (! $coa->is_active) {
            return "Akun {$coa->code} {$coa->name} tidak aktif.";
        }
        if ($coa->children()->exists()) {
            return "Akun {$coa->code} {$coa->name} adalah akun induk (punya sub-akun); pilih akun detail yang dapat diposting.";
        }
        if ($definition && ! in_array($coa->type, $definition['types'], true)) {
            return "Akun {$coa->code} {$coa->name} bertipe {$coa->type}; kunci ini membutuhkan tipe ".implode(' / ', $definition['types']).'.';
        }

        return null;
    }

    /** @throws ValidationException */
    public function set(string $key, ?int $coaId, ?User $actor = null): void
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (! $definition || ! $definition['editable']) {
            throw ValidationException::withMessages(['key' => "Kunci akun \"{$key}\" tidak dikenal."]);
        }

        if ($coaId !== null) {
            $coa = ChartOfAccount::find($coaId);
            if (! $coa) {
                throw ValidationException::withMessages([$key => 'Akun tidak ditemukan.']);
            }
            if ($error = $this->problem($key, $coa)) {
                throw ValidationException::withMessages([$key => $error]);
            }
        }

        AccountingSetting::updateOrCreate(['key' => $key], ['coa_id' => $coaId, 'updated_by' => ($actor ?? Auth::user())?->getKey()]);
    }

    /**
     * Ringkasan per kunci untuk halaman & readiness: akun pengaturan, akun efektif, dan sumbernya.
     *
     * @return array<string, array{label: string, setting: ChartOfAccount|null, effective: ChartOfAccount|null, source: string, ready: bool}>
     */
    public function status(): array
    {
        $rows = [];
        foreach (self::editableKeys() as $key => $definition) {
            $setting = Schema::hasTable('accounting_settings') ? AccountingSetting::with('coa')->where('key', $key)->first()?->coa : null;
            $explicit = $this->explicit($key);
            $effective = $this->first($key);

            $rows[$key] = [
                'label' => $definition['label'],
                'setting' => $setting,
                'effective' => $effective,
                'source' => match (true) {
                    $explicit !== null => 'pengaturan',
                    $effective !== null => 'bawaan',
                    default => 'kosong',
                },
                'ready' => $explicit !== null,   // "siap" = diisi eksplisit dan sah
            ];
        }

        return $rows;
    }
}
