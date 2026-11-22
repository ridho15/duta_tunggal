<?php

use App\Services\BalanceSheetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * chart_of_accounts.is_current dulu NOT NULL DEFAULT 0, sehingga semua akun (kas, piutang, hutang dagang)
 * tersaji sebagai aset tidak lancar / kewajiban jangka panjang dan inferensi kode di BalanceSheetService
 * tidak pernah terpakai.
 *
 *  - Kolom dijadikan NULLABLE DEFAULT NULL (NULL = disimpulkan dari kode saat laporan dibuat).
 *  - Backfill: akun Asset/Liability yang bernilai 0 diisi hasil inferensi kode (11xx/21xx = lancar,
 *    12xx/13xx/22xx = tidak lancar; tak dapat disimpulkan → NULL). Akun yang sudah 1 (diset manual) dibiarkan.
 *    Akun non A/L (Equity/Revenue/Expense/Contra Asset) → NULL karena klasifikasi lancar tidak berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'is_current')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_current')->nullable()->default(null)->after('is_active');
            });
        } else {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_current')->nullable()->default(null)->change();
            });
        }

        DB::table('chart_of_accounts')
            ->whereNotIn('type', ['Asset', 'Liability'])
            ->update(['is_current' => null]);

        DB::table('chart_of_accounts')
            ->whereIn('type', ['Asset', 'Liability'])
            ->where(fn ($q) => $q->where('is_current', 0)->orWhereNull('is_current'))
            ->orderBy('id')
            ->select(['id', 'code', 'type'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $inferred = BalanceSheetService::inferIsCurrent((string) $row->code, (string) $row->type);
                    DB::table('chart_of_accounts')->where('id', $row->id)->update([
                        'is_current' => $inferred === null ? null : (int) $inferred,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')->whereNull('is_current')->update(['is_current' => 0]);

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->nullable(false)->change();
        });
    }
};
