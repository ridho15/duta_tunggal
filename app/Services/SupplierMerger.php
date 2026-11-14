<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Menggabungkan supplier ganda: SEMUA referensi transaksi dipindah ke supplier "survivor"
 * dalam SATU transaksi atomik, dan supplier yang digabung di-soft-delete dengan `merged_into`.
 */
class SupplierMerger
{
    /** Tabel yang menyimpan supplier lewat kolom `supplier_id`. */
    public const TABLES = [
        'purchase_orders',
        'invoices',
        'account_payables',
        'payment_requests',
        'vendor_payments',
        'order_request_items',
    ];

    /**
     * Ringkasan angka yang wajib kekal bila survivor + yang digabung dijumlahkan.
     *
     * @return array{payables: float, documents: array<string, int>}
     */
    public function snapshot(Supplier $supplier): array
    {
        $docs = [];
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'supplier_id')) {
                $docs[$table] = (int) DB::table($table)->where('supplier_id', $supplier->id)->count();
            }
        }

        return [
            'payables' => round((float) AccountPayable::where('supplier_id', $supplier->id)->where('status', 'Belum Lunas')->sum('remaining'), 2),
            'documents' => $docs,
        ];
    }

    /**
     * Alasan penggabungan ini tidak boleh dilakukan; null = boleh.
     */
    public function blocker(Supplier $survivor, Supplier $merged): ?string
    {
        if ($survivor->is($merged)) {
            return 'Supplier survivor dan supplier yang digabung tidak boleh sama.';
        }
        if ($merged->merged_into) {
            return "Supplier #{$merged->id} sudah digabung ke #{$merged->merged_into}.";
        }
        if ($survivor->merged_into) {
            return "Survivor #{$survivor->id} sendiri sudah digabung ke #{$survivor->merged_into}; pilih survivor akhir.";
        }

        return null;
    }

    /**
     * @return array{moved: array<string, array<int, int>>, before: array, after: array}
     *
     * @throws ValidationException
     */
    public function merge(Supplier $survivor, Supplier $merged, ?string $reason = null): array
    {
        if ($message = $this->blocker($survivor, $merged)) {
            throw ValidationException::withMessages(['merge' => $message]);
        }

        return DB::transaction(function () use ($survivor, $merged, $reason) {
            $before = ['survivor' => $this->snapshot($survivor), 'merged' => $this->snapshot($merged)];
            $moved = [];

            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'supplier_id')) {
                    $ids = DB::table($table)->where('supplier_id', $merged->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if ($ids !== []) {
                        DB::table($table)->whereIn('id', $ids)->update(['supplier_id' => $survivor->id]);
                    }
                    $moved[$table] = $ids;
                }
            }

            // Simpan catatan alasan pada supplier
            $note = $merged->keterangan ? $merged->keterangan . "\n" : '';
            $note .= "[DIGABUNG KE #{$survivor->id} ({$survivor->perusahaan})]: " . ($reason ?? 'Penggabungan supplier ganda');

            $merged->forceFill([
                'merged_into' => $survivor->id,
                'is_active' => false,
                'keterangan' => $note,
            ])->save();

            $merged->delete(); // Soft delete; dokumen riwayat aman dialihkan ke survivor

            $survivor->refresh();
            $after = $this->snapshot($survivor);

            return [
                'moved' => $moved,
                'before' => $before,
                'after' => $after,
            ];
        });
    }
}

