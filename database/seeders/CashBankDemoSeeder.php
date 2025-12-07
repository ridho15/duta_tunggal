<?php

namespace Database\Seeders;

use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Services\CashBankService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CashBankDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Defensive: ensure the expected table exists before attempting to insert.
        if (! Schema::hasTable('cash_bank_transfers')) {
            Log::warning('[CashBankDemoSeeder] Skipping seeder because table cash_bank_transfers does not exist. Run migrations first.');
            return;
        }

        // Get the first cabang
        $cabang = Cabang::first();
        if (!$cabang) {
            Log::warning('[CashBankDemoSeeder] Skipping seeder because no cabang exists.');
            return;
        }

        DB::transaction(function () use ($cabang) {
            // 1) Ensure two bank accounts exist and active
            $bankA = ChartOfAccount::firstOrCreate(
                ['code' => '1112.01'],
                ['name' => 'Bank A', 'type' => 'Asset', 'is_active' => true]
            );
            $bankB = ChartOfAccount::firstOrCreate(
                ['code' => '1112.02'],
                ['name' => 'Bank B', 'type' => 'Asset', 'is_active' => true]
            );

            // 2) Create a transfer today from A to B with biaya lainnya
            $transferAttrs = [
                'number' => app(CashBankService::class)->generateTransferNumber(),
                'date' => now()->toDateString(),
                'from_coa_id' => $bankA->id,
                'to_coa_id' => $bankB->id,
                'amount' => 1500000,
                'other_costs' => 5000,
                'description' => 'Seed transfer A ke B',
                'reference' => 'SEED-TRF',
                'status' => 'draft',
                'cabang_id' => $cabang->id, // Use the first cabang
            ];

            [$transfer, $created] = $this->firstOrCreateWithFlag(CashBankTransfer::class, ['reference' => $transferAttrs['reference']], $transferAttrs);

            // 3) Post to journal entries only when newly created
            if ($created) {
                app(CashBankService::class)->postTransfer($transfer);
            }

            // 4) Create a second transfer in the same period to have more data
            $transfer2Attrs = [
                'number' => app(CashBankService::class)->generateTransferNumber(),
                'date' => now()->toDateString(),
                'from_coa_id' => $bankB->id,
                'to_coa_id' => $bankA->id,
                'amount' => 700000,
                'other_costs' => 0,
                'description' => 'Seed transfer B ke A',
                'reference' => 'SEED-TRF-2',
                'status' => 'draft',
                'cabang_id' => $cabang->id,
            ];

            [$transfer2, $created2] = $this->firstOrCreateWithFlag(CashBankTransfer::class, ['reference' => $transfer2Attrs['reference']], $transfer2Attrs);
            if ($created2) {
                app(CashBankService::class)->postTransfer($transfer2);
            }

            // Done: now journal_entries contain DR/CR for both bank accounts in today date
        });
    }

    /**
     * Helper to perform firstOrCreate but also return whether the record was created.
     *
     * @param string $modelClass
     * @param array $conditions
     * @param array $attributes
     * @return array [\Illuminate\Database\Eloquent\Model $model, bool $created]
     */
    private function firstOrCreateWithFlag(string $modelClass, array $conditions, array $attributes): array
    {
        $model = $modelClass::where($conditions)->first();

        if ($model) {
            return [$model, false];
        }

        $model = $modelClass::create($attributes);

        return [$model, true];
    }
}
