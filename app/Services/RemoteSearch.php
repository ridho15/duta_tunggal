<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Support\LineAmounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencarian dropdown SISI-SERVER (T7.2, usulan 17): mengganti pola "muat semua + preload" dan `limit(50)` statis yang membuat
 * customer/produk/dokumen ke-51 dst. tak dapat dipilih. Hasil dibatasi {@see self::LIMIT}; setiap kata pencarian harus cocok pada
 * salah satu kolom (nama, kode, perusahaan, NIK/NPWP, nomor dokumen, nama customer). Label nilai terpilih selalu dapat dipulihkan (`label()`).
 * NIK/NPWP ikut dicari tetapi TIDAK ditampilkan pada label.
 */
class RemoteSearch
{
    public const LIMIT = 50;

    public const HINT = 'Menampilkan 50 teratas — ketik nama, kode, atau nomor untuk mempersempit.';

    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'customers' => Customer::class,
        'products' => Product::class,
        'sale-orders' => SaleOrder::class,
        'quotations' => Quotation::class,
        'invoices' => Invoice::class,
        'delivery-orders' => DeliveryOrder::class,
    ];

    public static function types(): array
    {
        return array_keys(self::MODELS);
    }

    public static function modelFor(string $type): string
    {
        return self::MODELS[$type] ?? throw new \InvalidArgumentException("Jenis pencarian \"{$type}\" tidak dikenal.");
    }

    /**
     * @param  array<string, mixed>  $filters  customer_id, cabang_id (opsional, mempersempit)
     * @return array<int|string, string> [id => label], paling banyak LIMIT
     */
    public function options(string $type, ?string $query = null, array $filters = []): array
    {
        return $this->query($type, $query, $filters)->limit(self::LIMIT)->get()->mapWithKeys(fn (Model $m) => [$m->getKey() => $this->labelOf($type, $m)])->all();
    }

    /** Label satu nilai terpilih (walau di luar 50 teratas) — untuk `getOptionLabelUsing`. */
    public function label(string $type, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $model = $this->baseQuery($type, forLabel: true)->find($id);

        return $model ? $this->labelOf($type, $model) : null;
    }

    /** @return array{results: array<int, array{id: int|string, label: string}>, limit: int, truncated: bool, hint: ?string} */
    public function search(string $type, ?string $query = null, array $filters = []): array
    {
        $rows = $this->query($type, $query, $filters)->limit(self::LIMIT + 1)->get();
        $truncated = $rows->count() > self::LIMIT;

        return [
            'results' => $rows->take(self::LIMIT)->map(fn (Model $m) => ['id' => $m->getKey(), 'label' => $this->labelOf($type, $m)])->values()->all(),
            'limit' => self::LIMIT,
            'truncated' => $truncated,
            'hint' => $truncated ? self::HINT : null,
        ];
    }

    // ------------------------------------------------------------------ internal

    private function baseQuery(string $type, bool $forLabel = false): Builder
    {
        $class = self::modelFor($type);

        return match ($type) {
            // customer yang sudah digabung tidak ditawarkan untuk dipilih, tetapi labelnya tetap dapat dipulihkan (dokumen lama)
            'customers' => $forLabel ? $class::query() : $class::query()->whereNull('merged_into'),
            'invoices' => $class::query()->where('from_model_type', SaleOrder::class),
            'sale-orders', 'quotations' => $class::query()->with('customer:id,name,code'),
            'delivery-orders' => $class::query()->with('salesOrders.customer:id,name'),
            default => $class::query(),
        };
    }

    private function query(string $type, ?string $query, array $filters): Builder
    {
        $builder = $this->baseQuery($type);

        if (! empty($filters['cabang_id'])) {
            $builder->where('cabang_id', $filters['cabang_id']);
        }
        if (! empty($filters['customer_id'])) {
            match ($type) {
                'sale-orders', 'quotations' => $builder->where('customer_id', $filters['customer_id']),
                default => null,
            };
        }

        foreach (preg_split('/\s+/', trim((string) $query), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $builder->where(fn (Builder $q) => $this->matchTerm($type, $q, $like));
        }

        return match ($type) {
            'customers', 'products' => $builder->orderBy('name'),
            default => $builder->orderByDesc('id'),
        };
    }

    private function matchTerm(string $type, Builder $q, string $like): void
    {
        match ($type) {
            'customers' => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('legacy_code', 'like', $like)
                ->orWhere('perusahaan', 'like', $like)->orWhere('nik_npwp', 'like', $like),
            'products' => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like),
            'sale-orders' => $q->where('so_number', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like)),
            'quotations' => $q->where('quotation_number', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like)),
            'invoices' => $q->where('invoice_number', 'like', $like)->orWhere('customer_name', 'like', $like),
            'delivery-orders' => $q->where('do_number', 'like', $like)->orWhereHas('salesOrders.customer', fn ($c) => $c->where('name', 'like', $like)),
        };
    }

    private function labelOf(string $type, Model $m): string
    {
        return match ($type) {
            'customers' => $m->getDisplayName(),
            'products' => '('.($m->sku ?: '-').') '.$m->name,
            'sale-orders' => $this->docLabel($m->so_number, $m->customer?->name, $m->order_date, $m->total_amount),
            'quotations' => $this->docLabel($m->quotation_number, $m->customer?->name, $m->date, $m->total_amount),
            'invoices' => $this->docLabel($m->invoice_number, $m->customer_name, $m->invoice_date, $m->total),
            'delivery-orders' => $this->docLabel($m->do_number, $m->salesOrders->map(fn ($so) => $so->customer?->name)->filter()->unique()->implode(', '), $m->delivery_date, null),
        };
    }

    /** "SO-0004 · PT Contoh · 12 Sep 2026 · Rp 1.332.000,00" — nomor, customer, tanggal, nilai (bagian kosong dilewati). */
    private function docLabel(?string $number, ?string $customer, mixed $date, mixed $amount): string
    {
        return collect([
            $number,
            $customer,
            $date ? \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d M Y') : null,
            $amount !== null && (float) $amount > 0 ? LineAmounts::money((float) $amount) : null,
        ])->filter(fn ($part) => filled($part))->implode(' · ');
    }
}
