<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Illuminate\Support\Facades\DB;

class TestVoucherApprove extends Command
{
    protected $signature = 'test:voucher-approve';
    protected $description = 'Create vouchers and test approve flow with and without auto-create cashbank transaction';

    public function handle()
    {
        $this->info('Starting voucher approve tests');

        $service = app(VoucherRequestService::class);

        // Helper to create a draft voucher
        $createDraft = function ($label, $amount = 100000) use ($service) {
            $voucher = VoucherRequest::create([
                'voucher_number' => $service->generateVoucherNumber(),
                'voucher_date' => now()->toDateString(),
                'amount' => $amount,
                'related_party' => 'Test ' . $label,
                'description' => 'Auto-created for test: ' . $label,
                'status' => 'draft',
            ]);
            return $voucher;
        };

        // Test 1: approve without auto_create_transaction
        $this->info('Test 1: Approve without auto_create_transaction');
        $v1 = $createDraft('no-auto');
        $this->info('Created voucher id=' . $v1->id . ' number=' . $v1->voucher_number);

        $service->submitForApproval($v1);
        $this->info('Submitted for approval (status should be pending): ' . $v1->fresh()->status);

        try {
            $service->approve($v1, []);
            $v1 = $v1->fresh();
            $this->info('Approved. Status=' . $v1->status . ', cash_bank_transaction_id=' . ($v1->cash_bank_transaction_id ?? 'null'));
        } catch (\Exception $e) {
            $this->error('Approve failed: ' . $e->getMessage());
            return 1;
        }

        // Test 2: approve with auto_create_transaction
        $this->info('Test 2: Approve WITH auto_create_transaction + auto_post');
        $v2 = $createDraft('with-auto');
        $this->info('Created voucher id=' . $v2->id . ' number=' . $v2->voucher_number);

        $service->submitForApproval($v2);
        $this->info('Submitted for approval (status should be pending): ' . $v2->fresh()->status);

        // Prepare data: pick cash_bank_account, pick two coa ids
        $cashAccount = DB::table('cash_bank_accounts')->first();
        $coa1 = DB::table('chart_of_accounts')->first();
        $coa2 = DB::table('chart_of_accounts')->skip(1)->first();

        if (!$coa1 || !$coa2) {
            $this->error('Not enough COA records found (need at least 2). Aborting test 2.');
            return 1;
        }

        $data = [
            'auto_create_transaction' => true,
            'transaction_type' => 'cash_out',
            'cash_bank_account_id' => $cashAccount ? $cashAccount->id : null,
            'account_coa_id' => $coa1->id,
            'offset_coa_id' => $coa2->id,
            'auto_post' => true,
            'approval_notes' => 'Testing auto create and post',
        ];

        try {
            $service->approve($v2, $data);
            $v2 = $v2->fresh();
            $this->info('Approved. Status=' . $v2->status . ', cash_bank_transaction_id=' . ($v2->cash_bank_transaction_id ?? 'null'));

            if ($v2->cash_bank_transaction_id) {
                $trx = DB::table('cash_bank_transactions')->where('id', $v2->cash_bank_transaction_id)->first();
                $this->info('CashBankTransaction created: id=' . $trx->id . ' number=' . $trx->number);

                // Check journal entries
                $journals = DB::table('journal_entries')->where('source_type', 'App\\Models\\CashBankTransaction')->where('source_id', $trx->id)->get();
                $this->info('Journal entries created: ' . $journals->count());
                foreach ($journals as $j) {
                    $this->info("Journal: id={$j->id} coa_id={$j->coa_id} debit={$j->debit} credit={$j->credit}");
                }
            } else {
                $this->error('Expected cash_bank_transaction_id to be set but it is null');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Approve with auto-create failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('All tests completed successfully');
        return 0;
    }
}
