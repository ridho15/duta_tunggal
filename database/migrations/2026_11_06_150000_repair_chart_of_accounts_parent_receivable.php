<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Memastikan akun 1120 (Piutang Dagang) tidak menjadi akun induk yang tidak sah akibat
     * relasi parent_id dari akun saudara seperti 1121 (Piutang Marketplace) dan 1122 (Piutang Giro).
     * Sesuai standar bagan akun Duta Tunggal, 1121 dan 1122 menginduk langsung ke 1100 (Aktiva Lancar),
     * sehingga 1120 tetap menjadi akun transaksi (leaf account).
     */
    public function up(): void
    {
        $piutangDagang = ChartOfAccount::withoutGlobalScopes()->where('code', '1120')->first();
        if (! $piutangDagang) {
            return;
        }

        $aktivaLancar = ChartOfAccount::withoutGlobalScopes()
            ->where('code', '1100')
            ->first();

        if (! $aktivaLancar) {
            // Fallback bila 1100 tidak ada: cari akun induk 1000 (Aktiva)
            $aktivaLancar = ChartOfAccount::withoutGlobalScopes()
                ->where('code', '1000')
                ->first();
        }

        if ($aktivaLancar) {
            // Pindahkan akun saudara non-sub-1120 (seperti 1121, 1122) yang keliru menginduk ke 1120
            $affected = ChartOfAccount::withoutGlobalScopes()
                ->where('parent_id', $piutangDagang->id)
                ->where('code', 'not like', '1120.%')
                ->update(['parent_id' => $aktivaLancar->id]);

            if ($affected > 0) {
                Log::info("Migrasi repair_chart_of_accounts_parent_receivable: {$affected} akun berhasil dipindahkan parent_id ke {$aktivaLancar->code}.");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Operasi perbaikan relasi data bersifat perbaikan integritas; tidak perlu dibatalkan.
    }
};

