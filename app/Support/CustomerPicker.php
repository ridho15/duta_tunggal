<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pemilih customer berbasis pencarian AJAX untuk form penjualan: halaman TIDAK memuat master customer,
 * hanya customer terpilih (prefill) + hasil pencarian. Dipakai x-searchable-select dengan remote-url.
 */
class CustomerPicker
{
    public const MIN_QUERY = 2;

    public const LIMIT = 25;

    private const COLUMNS = [
        'id', 'code', 'name', 'perusahaan', 'address', 'telephone', 'phone',
        'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'tipe',
    ];

    /** Setiap kata harus cocok di nama, kode, kode lama, perusahaan, atau NIK/NPWP; customer yang sudah digabung tidak ditawarkan. */
    public static function search(string $q, bool $debtorsFirst = false): array
    {
        $query = Customer::query()->with('deposit')->whereNull('merged_into');
        foreach (preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('legacy_code', 'like', $like)
                ->orWhere('perusahaan', 'like', $like)->orWhere('nik_npwp', 'like', $like));
        }
        if ($debtorsFirst) {
            $query->orderByRaw('exists ('.self::debtSql().') desc');
        }

        return self::options($query->orderByRaw('code = ? desc', [$q])->orderBy('name')->limit(self::LIMIT)->get(self::COLUMNS));
    }

    /** Customer yang dipilih saat halaman dibuka (input lama / dokumen asal), tanpa memuat master. */
    public static function prefill(iterable $ids, int $debtors = 0): array
    {
        $ids = collect($ids)->filter();
        if ($debtors > 0) {
            // MySQL tidak mendukung LIMIT di dalam IN (subquery): ambil id pelanggan berpiutang lebih dulu.
            $ids = $ids->merge(Customer::query()->whereRaw('exists ('.self::debtSql().')')->orderBy('name')->limit($debtors)->pluck('id'));
        }
        $query = Customer::query()->with('deposit')->whereIn('id', $ids->unique()->values());

        return self::options($query->orderBy('name')->get(self::COLUMNS));
    }

    public static function options(Collection $customers): array
    {
        return $customers->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->name.($c->code ? ' ('.$c->code.')' : ''),
            'subtext' => ($c->address ? Str::limit($c->address, 35) : '').' | Limit: Rp '.number_format($c->kredit_limit, 0, ',', '.').' | Tipe: '.($c->tipe_pembayaran ?? '-'),
            'badge' => $c->tipe_pembayaran ?? 'Customer',
            'data' => [
                'id' => (string) $c->id,
                'name' => $c->name,
                'tipe' => $c->tipe_pembayaran ?? 'Tunai',
                'limit' => (float) $c->kredit_limit,
                'tempo' => (int) $c->tempo_kredit,
                'address' => $c->address ?? '',
                'phone' => $c->phone ?? ($c->telephone ?? ''),
                'deposit' => (float) ($c->deposit?->remaining_amount ?? 0),
            ],
        ])->values()->all();
    }

    /** Cache JS {id: data} dari hasil options(). */
    public static function dataMap(array $options): object
    {
        return (object) collect($options)->mapWithKeys(fn ($o) => [$o['id'] => $o['data']])->all();
    }

    private static function debtSql(): string
    {
        return 'select 1 from account_receivables where account_receivables.customer_id = customers.id and account_receivables.remaining > 1.00 and account_receivables.deleted_at is null';
    }
}
