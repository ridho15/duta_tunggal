<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Deposit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menggabungkan customer ganda (T4.3, D14/D33): SEMUA referensi dipindah ke customer "survivor" dalam SATU transaksi,
 * customer yang digabung di-soft-delete dengan `merged_into`. Terbukti dengan snapshot sebelum/sesudah
 * (Σ piutang berjalan, saldo deposit, jumlah dokumen per tabel): selisih → transaksi dibatalkan.
 *
 * Deposit: bila KEDUANYA punya baris deposit, penggabungan otomatis ditolak (saldo harus dikonsolidasi manual terlebih dulu).
 */
class CustomerMerger
{
    /** Tabel yang menyimpan customer lewat kolom `customer_id`. */
    public const TABLES = ['sale_orders', 'quotations', 'customer_receipts', 'customer_returns', 'account_receivables', 'other_sales'];

    /**
     * Ringkasan angka yang wajib kekal bila survivor + yang digabung dijumlahkan.
     *
     * @return array{receivables: float, deposit: float, documents: array<string, int>}
     */
    public function snapshot(Customer $customer): array
    {
        return [
            'receivables' => round((float) AccountReceivable::where('customer_id', $customer->id)->where('status', 'Belum Lunas')->sum('remaining'), 2),
            'deposit' => round((float) Deposit::where('from_model_type', Customer::class)->where('from_model_id', $customer->id)->sum('remaining_amount'), 2),
            'documents' => collect(self::TABLES)->mapWithKeys(fn ($table) => [$table => (int) DB::table($table)->where('customer_id', $customer->id)->count()])->all(),
        ];
    }

    /** Alasan penggabungan ini tidak boleh dilakukan; null = boleh. */
    public function blocker(Customer $survivor, Customer $merged): ?string
    {
        if ($survivor->is($merged)) {
            return 'Survivor dan customer yang digabung tidak boleh sama.';
        }
        if ($merged->merged_into) {
            return "Customer #{$merged->id} sudah digabung ke #{$merged->merged_into}.";
        }
        if ($survivor->merged_into) {
            return "Survivor #{$survivor->id} sendiri sudah digabung ke #{$survivor->merged_into}; pilih survivor akhir.";
        }

        $depositRows = fn (Customer $c) => Deposit::where('from_model_type', Customer::class)->where('from_model_id', $c->id)->count();
        if ($depositRows($survivor) > 0 && $depositRows($merged) > 0) {
            return "Customer #{$survivor->id} dan #{$merged->id} sama-sama punya deposit; konsolidasikan saldo deposit secara manual lebih dulu.";
        }

        return null;
    }

    /**
     * @return array{moved: array<string, array<int, int>>, before: array, after: array}
     *
     * @throws ValidationException
     */
    public function merge(Customer $survivor, Customer $merged, ?string $reason = null): array
    {
        if ($message = $this->blocker($survivor, $merged)) {
            throw ValidationException::withMessages(['merge' => $message]);
        }

        return DB::transaction(function () use ($survivor, $merged) {
            $before = ['survivor' => $this->snapshot($survivor), 'merged' => $this->snapshot($merged)];
            $moved = [];

            foreach (self::TABLES as $table) {
                $ids = DB::table($table)->where('customer_id', $merged->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($ids !== []) {
                    DB::table($table)->whereIn('id', $ids)->update(['customer_id' => $survivor->id]);
                }
                $moved[$table] = $ids;
            }

            $depositIds = Deposit::where('from_model_type', Customer::class)->where('from_model_id', $merged->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($depositIds !== []) {
                Deposit::whereIn('id', $depositIds)->update(['from_model_id' => $survivor->id]);
            }
            $moved['deposits'] = $depositIds;

            $merged->forceFill(['merged_into' => $survivor->id])->save();
            $merged->delete();   // soft delete; dokumen lama tetap terbaca lewat survivor

            $survivor->refresh();
            $after = $this->snapshot($survivor);

            // Bukti: angka survivor sesudah = survivor sebelum + yang digabung sebelum
            $expectedDocs = [];
            foreach (self::TABLES as $table) {
                $expectedDocs[$table] = $before['survivor']['documents'][$table] + $before['merged']['documents'][$table];
            }
            $ok = abs($after['receivables'] - ($before['survivor']['receivables'] + $before['merged']['receivables'])) < 0.01
                && abs($after['deposit'] - ($before['survivor']['deposit'] + $before['merged']['deposit'])) < 0.01
                && $after['documents'] === $expectedDocs;

            if (! $ok) {
                throw new \RuntimeException("Verifikasi penggabungan customer #{$merged->id} → #{$survivor->id} gagal (angka sebelum/sesudah tidak sama); dibatalkan.");
            }

            return ['moved' => $moved, 'before' => $before, 'after' => $after];
        });
    }
}
