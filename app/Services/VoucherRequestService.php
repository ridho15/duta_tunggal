<?php

namespace App\Services;

use App\Models\VoucherRequest;
use App\Models\CashBankTransaction;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VoucherRequestService
{
    /**
     * Generate nomor voucher otomatis
     * Format: VR-YYYYMMDD-XXXX
     */
    public function generateVoucherNumber(): string
    {
        $date = now()->format('Ymd');
        $cacheKey = "voucher_number_counter_{$date}";

        // Gunakan cache untuk tracking counter per hari
        $counter = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
        $counter += 1;

        // Simpan counter ke cache dengan expiry 24 jam
        \Illuminate\Support\Facades\Cache::put($cacheKey, $counter, now()->addDay());

        return 'VR-' . $date . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Submit voucher request untuk approval
     */
    /**
     * Submit voucher request untuk approval
     *
     * @param bool $notifyOwner jika true, notifikasi juga dikirimkan ke user dengan role 'Owner'
     */
    public function submitForApproval(VoucherRequest $voucherRequest, bool $notifyOwner = false): VoucherRequest
    {
        if (Auth::check() && !Auth::user()->can('submit', $voucherRequest)) {
            throw new AuthorizationException('Tidak memiliki izin untuk mengajukan voucher request.');
        }

        if (!$voucherRequest->canBeSubmitted()) {
            throw new \Exception('Voucher request tidak dapat diajukan. Status saat ini: ' . $voucherRequest->getStatusLabel());
        }

        $voucherRequest->update([
            'status' => 'pending',
        ]);

        activity()
            ->performedOn($voucherRequest)
            ->causedBy(Auth::user())
            ->log('Voucher request diajukan untuk persetujuan');

        // Jika diminta, tandai waktu/siapa yang meminta dan kirim notifikasi ke semua user yang punya role 'Owner'
        if ($notifyOwner) {
            try {
                // Persist informasi request ke voucher
                $voucherRequest->update([
                    'requested_to_owner_at' => now(),
                    'requested_to_owner_by' => Auth::id(),
                ]);

                $owners = \App\Models\User::role('Owner')->get();
                if ($owners->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($owners, new \App\Notifications\VoucherRequestApprovalRequestedToOwner($voucherRequest->fresh()));
                    activity()
                        ->performedOn($voucherRequest)
                        ->causedBy(Auth::user())
                        ->log('Notifikasi permintaan approval dikirim ke Owner');
                }
            } catch (\Throwable $e) {
                // Jangan ganggu alur utama jika notifikasi gagal; log saja
                logger()->warning('Gagal mengirim notifikasi ke Owner: ' . $e->getMessage());
            }
        }
        return $voucherRequest->fresh();
    }

    /**
     * Approve voucher request dan create cash/bank transaction
     */
    public function approve(VoucherRequest $voucherRequest, array $data = []): VoucherRequest
    {
        if (Auth::check() && !Auth::user()->can('approve', $voucherRequest)) {
            throw new AuthorizationException('Tidak memiliki izin untuk menyetujui voucher request.');
        }

        if (!$voucherRequest->canBeApproved()) {
            throw new \Exception('Voucher request tidak dapat disetujui. Status saat ini: ' . $voucherRequest->getStatusLabel());
        }

        DB::beginTransaction();
        try {
            // Update voucher request status
            $voucherRequest->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $data['approval_notes'] ?? null,
            ]);

            // Opsional: Auto-create cash/bank transaction jika diperlukan
            if (isset($data['auto_create_transaction']) && $data['auto_create_transaction']) {
                // Jika user memilih cash_bank_account_id dan tabel memiliki mapping coa_id, gunakan mapping itu
                if (!empty($data['cash_bank_account_id']) && Schema::hasTable('cash_bank_accounts')) {
                    // Jika kolom coa_id ada, ambil mapping dan set account_coa_id bila belum diisi
                    if (Schema::hasColumn('cash_bank_accounts', 'coa_id')) {
                        $mapped = DB::table('cash_bank_accounts')->where('id', $data['cash_bank_account_id'])->value('coa_id');
                        if ($mapped && empty($data['account_coa_id'])) {
                            $data['account_coa_id'] = $mapped;
                        }
                    }
                }

                // Validasi server-side: account_coa_id dan offset_coa_id harus ada
                if (empty($data['account_coa_id']) || empty($data['offset_coa_id'])) {
                    throw new \Exception('Untuk membuat transaksi otomatis, mohon pilih Akun COA (Kas/Bank) dan Akun COA Lawan.');
                }

                $transaction = $this->createCashBankTransaction($voucherRequest, $data);
                $voucherRequest->update([
                    'cash_bank_transaction_id' => $transaction->id,
                ]);
            }

            activity()
                ->performedOn($voucherRequest)
                ->causedBy(Auth::user())
                ->log('Voucher request disetujui');

            DB::commit();
            return $voucherRequest->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject voucher request
     */
    public function reject(VoucherRequest $voucherRequest, string $reason): VoucherRequest
    {
        if (Auth::check() && !Auth::user()->can('reject', $voucherRequest)) {
            throw new AuthorizationException('Tidak memiliki izin untuk menolak voucher request.');
        }

        if (!$voucherRequest->canBeRejected()) {
            throw new \Exception('Voucher request tidak dapat ditolak. Status saat ini: ' . $voucherRequest->getStatusLabel());
        }

        if (empty(trim($reason))) {
            throw new \Exception('Alasan penolakan harus diisi');
        }

        $voucherRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_notes' => $reason,
        ]);

        activity()
            ->performedOn($voucherRequest)
            ->causedBy(Auth::user())
            ->log('Voucher request ditolak: ' . $reason);

        return $voucherRequest->fresh();
    }

    /**
     * Cancel voucher request
     */
    public function cancel(VoucherRequest $voucherRequest, string $reason = null): VoucherRequest
    {
        if (Auth::check() && !Auth::user()->can('cancel', $voucherRequest)) {
            throw new AuthorizationException('Tidak memiliki izin untuk membatalkan voucher request.');
        }

        if (!$voucherRequest->canBeCancelled()) {
            throw new \Exception('Voucher request tidak dapat dibatalkan. Status saat ini: ' . $voucherRequest->getStatusLabel());
        }

        $voucherRequest->update([
            'status' => 'cancelled',
            'approval_notes' => $reason ?? 'Dibatalkan oleh user',
        ]);

        activity()
            ->performedOn($voucherRequest)
            ->causedBy(Auth::user())
            ->log('Voucher request dibatalkan');

        return $voucherRequest->fresh();
    }

    /**
     * Create cash/bank transaction dari voucher yang approved
     */
    public function createCashBankTransaction(VoucherRequest $voucherRequest, array $data): CashBankTransaction
    {
        if (!$voucherRequest->isApproved()) {
            throw new \Exception('Hanya voucher yang sudah disetujui yang dapat dibuatkan transaksi kas/bank');
        }

        if ($voucherRequest->cash_bank_transaction_id) {
            throw new \Exception('Voucher ini sudah memiliki transaksi kas/bank terkait');
        }

        // Generate transaction number
        $cashBankService = app(CashBankService::class);
        $transactionNumber = $cashBankService->generateNumber('CB');

        // Create transaction
        $transaction = CashBankTransaction::create([
            'number' => $transactionNumber,
            'date' => $voucherRequest->voucher_date,
            'type' => $data['transaction_type'] ?? 'cash_out', // default cash out
            'amount' => $voucherRequest->amount,
            'description' => 'Pembayaran Voucher: ' . $voucherRequest->voucher_number . ' - ' . $voucherRequest->related_party . ' - ' . $voucherRequest->description,
            'cash_bank_account_id' => $data['cash_bank_account_id'] ?? null,
            'account_coa_id' => $data['account_coa_id'] ?? null,
            'offset_coa_id' => $data['offset_coa_id'] ?? null,
            'cabang_id' => $voucherRequest->cabang_id,
        ]);

        // Post transaction jika diminta
        if (isset($data['auto_post']) && $data['auto_post']) {
            $cashBankService->postTransaction($transaction);
        }

        activity()
            ->performedOn($voucherRequest)
            ->causedBy(Auth::user())
            ->log('Transaksi kas/bank dibuat untuk voucher request: ' . $transaction->number);

        return $transaction;
    }

    /**
     * Link existing cash/bank transaction ke voucher
     */
    public function linkCashBankTransaction(VoucherRequest $voucherRequest, int $transactionId): VoucherRequest
    {
        $transaction = CashBankTransaction::findOrFail($transactionId);

        $voucherRequest->update([
            'cash_bank_transaction_id' => $transaction->id,
        ]);

        activity()
            ->performedOn($voucherRequest)
            ->causedBy(Auth::user())
            ->log('Linked to cash/bank transaction: ' . $transaction->number);

        return $voucherRequest->fresh();
    }

    /**
     * Validate voucher usage for cash/bank transaction
     */
    public function validateVoucherUsage(VoucherRequest $voucherRequest, float $amount, string $usageType = 'single_use'): bool
    {
        if (!$voucherRequest->isApproved()) {
            throw new \Exception('Voucher belum disetujui');
        }

        if ($usageType === 'single_use') {
            // Single use: check if voucher has been used at all
            $hasBeenUsed = CashBankTransaction::where('voucher_request_id', $voucherRequest->id)->exists();
            if ($hasBeenUsed) {
                throw new \Exception('Voucher ini sudah digunakan sebelumnya (single use)');
            }

            // Check if amount matches voucher amount
            if ($amount != $voucherRequest->amount) {
                throw new \Exception('Untuk voucher single use, jumlah transaksi harus sama dengan nominal voucher');
            }
        } else {
            // Multi use: check remaining amount
            $remainingAmount = $voucherRequest->getRemainingAmount();
            if ($amount > $remainingAmount) {
                throw new \Exception("Jumlah transaksi melebihi sisa voucher. Sisa voucher: " . formatCurrency($remainingAmount));
            }
        }

        return true;
    }

    /**
     * Get available vouchers for selection
     */
    public function getAvailableVouchers(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = VoucherRequest::approved();

        // Apply filters
        if (isset($filters['cabang_id'])) {
            $query->where('cabang_id', $filters['cabang_id']);
        }

        if (isset($filters['amount'])) {
            $query->where('amount', '>=', $filters['amount']);
        }

        $vouchers = $query->orderBy('voucher_date', 'desc')->get();

        // Filter vouchers that can still be used
        return $vouchers->filter(function ($voucher) {
            return $voucher->canBeUsed();
        });
    }

    /**
     * Get statistics untuk dashboard
     */
    public function getStatistics(array $filters = []): array
    {
        $query = VoucherRequest::query();

        // Apply filters
        if (isset($filters['date_from'])) {
            $query->where('voucher_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('voucher_date', '<=', $filters['date_to']);
        }
        if (isset($filters['cabang_id'])) {
            $query->where('cabang_id', $filters['cabang_id']);
        }

        return [
            'total' => $query->count(),
            'draft' => (clone $query)->draft()->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'rejected' => (clone $query)->rejected()->count(),
            'cancelled' => (clone $query)->cancelled()->count(),
            'total_requests' => $query->count(),
            'total_amount' => [
                'pending' => (clone $query)->pending()->sum('amount'),
                'approved' => (clone $query)->approved()->sum('amount'),
                'rejected' => (clone $query)->rejected()->sum('amount'),
            ],
        ];
    }
}
