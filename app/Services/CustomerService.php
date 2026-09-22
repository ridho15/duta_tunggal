<?php

namespace App\Services;

use App\Models\ApprovalOverride;
use App\Models\Customer;
use App\Support\CustomerDuplicateFinder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function generateCode()
    {
        // T4.2 (flag central_numbering, D32): kode global berurutan CUST-00001, atomik.
        if (DocumentNumberService::enabled()) {
            return app(DocumentNumberService::class)->next('customer');
        }

        $date = now()->format('Ymd');
        $prefix = 'CUS-'.$date.'-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix.$random;
            $exists = Customer::where('code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Customer yang KEMUNGKINAN sama dengan $data (NIK/NPWP sama; atau nama ternormalisasi sama DAN telepon sama).
     * Hasilnya untuk ditinjau manusia; customer yang sudah digabung (soft-delete) tidak dihitung.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{customer: Customer, reason: string}>
     */
    public function findDuplicates(array $data, ?int $ignoreId = null): Collection
    {
        $found = collect();

        $digits = CustomerDuplicateFinder::digits($data['nik_npwp'] ?? null);
        if (strlen($digits) >= 10) {
            Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->whereRaw("REGEXP_REPLACE(nik_npwp, '[^0-9]', '') = ?", [$digits])
                ->get()
                ->each(fn (Customer $c) => $found->put($c->id, ['customer' => $c, 'reason' => 'NIK/NPWP sama']));
        }

        $name = CustomerDuplicateFinder::normalizeName($data['name'] ?? null);
        $phoneKeys = collect([$data['phone'] ?? null, $data['telephone'] ?? null])->map(fn ($p) => CustomerDuplicateFinder::phoneKey($p))->filter()->unique();
        if ($name !== '' && $phoneKeys->isNotEmpty()) {
            Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where(function ($q) use ($phoneKeys) {
                    foreach ($phoneKeys as $key) {
                        $q->orWhere('phone', 'like', '%'.$key)->orWhere('telephone', 'like', '%'.$key);
                    }
                })
                ->get()
                ->filter(function (Customer $c) use ($name, $phoneKeys) {
                    $keys = collect([$c->phone, $c->telephone])->map(fn ($p) => CustomerDuplicateFinder::phoneKey($p))->filter();

                    return CustomerDuplicateFinder::normalizeName($c->name) === $name && $keys->intersect($phoneKeys)->isNotEmpty();
                })
                ->each(fn (Customer $c) => $found->has($c->id) ?: $found->put($c->id, ['customer' => $c, 'reason' => 'nama dan telepon sama']));
        }

        return $found->values();
    }

    /**
     * SATU-SATUNYA pintu pembuatan customer (T4.2, X10): form Customer, SO, Quotation, API. Flag `customer_dedup` menolak duplikat
     * (kecuali override beralasan Owner/Super Admin/Admin, tercatat); flag `central_numbering` mengeluarkan kode CUST-00001.
     *
     * @param  array<string, mixed>  $data
     * @param  array{allow_duplicate_reason?: string|null}  $options
     *
     * @throws ValidationException
     */
    public function create(array $data, array $options = []): Customer
    {
        $duplicates = config('sales.controls.customer_dedup', false) ? $this->findDuplicates($data) : collect();
        $override = null;

        if ($duplicates->isNotEmpty()) {
            $reason = trim((string) ($options['allow_duplicate_reason'] ?? ''));
            $user = Auth::user();

            if (! $user || ! $user->hasRole(['Super Admin', 'Owner', 'Admin']) || mb_strlen($reason) < 10) {
                $list = $duplicates->map(fn ($d) => "{$d['customer']->code} — {$d['customer']->name} ({$d['reason']})")->implode('; ');
                throw ValidationException::withMessages([
                    'customer' => "Customer ini kemungkinan sudah ada: {$list}. Gunakan customer tersebut, atau minta Owner/Super Admin/Admin membuatnya dengan alasan tercatat.",
                ]);
            }
            $override = ['reason' => $reason, 'matches' => $duplicates->map(fn ($d) => $d['customer']->id)->all(), 'user' => $user];
        }

        // Kode: bila penomoran terpusat hidup atau kode kosong → dibuat server (atomik); selain itu kode yang diisi form tetap dipakai.
        if (DocumentNumberService::enabled() || blank($data['code'] ?? null)) {
            $data['code'] = $this->generateCode();
        }

        $customer = Customer::create($data);

        if ($override) {
            ApprovalOverride::create([
                'document_type' => 'customer', 'document_id' => $customer->id, 'user_id' => $override['user']->getKey(),
                'reason' => $override['reason'], 'context' => ['kind' => 'duplicate_customer', 'matches' => $override['matches']],
            ]);
        }

        return $customer;
    }
}
