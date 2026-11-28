<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransactionDetail;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan Bayar non-barang (Ko Handy 28 Nov 2026):
 * dibuat (akun biaya per baris dipilih user) → diajukan → disetujui atasan/manager/finance/owner sesuai nominal
 * → dibayar kasir/finance (Kas & Bank keluar + jurnal Dr Biaya / Cr Kas-Bank) → voucher bertanda tangan digital.
 */
class ExpenseRequestService
{
    /** Bawaan bila belum ada Aturan Persetujuan "Permintaan Bayar": [batas atas => peran]. */
    public const DEFAULT_APPROVERS = [
        5_000_000 => ['Finance Manager', 'Sales Manager', 'Purchasing Manager', 'Inventory Manager', 'Admin'],
        PHP_INT_MAX => ['Finance Manager', 'Admin'],
    ];

    /** Peran yang membayar (kasir / finance). Owner & Super Admin selalu boleh. */
    public const PAYER_ROLES = ['Kasir', 'Finance Manager', 'Accounting', 'Admin Keuangan', 'Admin'];

    public function __construct(private SignatureService $signatures, private CashBankService $cashBank) {}

    // ------------------------------------------------------------------ simpan

    /** @param array{request_date:string,payee:string,description:string,cabang_id?:int|null,lines:array} $data */
    public function save(?ExpenseRequest $request, array $data, User $user, ?string $attachmentPath = null): ExpenseRequest
    {
        $lines = collect($data['lines'] ?? [])
            ->filter(fn ($l) => ! empty($l['coa_id']) && (float) ($l['amount'] ?? 0) > 0)
            ->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Isi minimal satu baris biaya dengan akun dan nominal lebih dari 0.']);
        }
        $this->assertExpenseAccounts($lines->pluck('coa_id')->all());

        return DB::transaction(function () use ($request, $data, $user, $lines, $attachmentPath) {
            $request ??= new ExpenseRequest([
                'number' => $this->nextNumber(),
                'requested_by' => $user->getKey(),
                'status' => ExpenseRequest::STATUS_DRAFT,
            ]);
            $request->fill([
                'request_date' => $data['request_date'],
                'payee' => trim($data['payee']),
                'description' => trim($data['description']),
                'cabang_id' => $data['cabang_id'] ?? $user->cabang_id,
                'amount' => round($lines->sum(fn ($l) => (float) $l['amount']), 2),
            ]);
            if ($attachmentPath) {
                $request->attachment_path = $attachmentPath;
            }
            if ($request->status === ExpenseRequest::STATUS_REJECTED) {
                $request->status = ExpenseRequest::STATUS_DRAFT;   // diperbaiki setelah ditolak → draf lagi
            }
            $request->save();

            $request->lines()->delete();
            foreach ($lines as $l) {
                $request->lines()->create([
                    'coa_id' => (int) $l['coa_id'],
                    'description' => isset($l['description']) ? mb_substr(trim((string) $l['description']), 0, 255) : null,
                    'amount' => round((float) $l['amount'], 2),
                ]);
            }

            return $request->fresh('lines');
        });
    }

    public function submit(ExpenseRequest $request, User $user): ExpenseRequest
    {
        $this->assertStatus($request, [ExpenseRequest::STATUS_DRAFT], 'diajukan');
        if ((int) $request->requested_by !== (int) $user->getKey() && ! Access::isSuper($user)) {
            throw ValidationException::withMessages(['status' => 'Hanya pembuat permintaan yang bisa mengajukannya.']);
        }
        DB::transaction(function () use ($request, $user) {
            $request->update(['status' => ExpenseRequest::STATUS_SUBMITTED]);
            $this->signatures->sign($request, 'requested', $user);
        });

        return $request->fresh();
    }

    // ------------------------------------------------------------------ persetujuan

    /** @return array{0: array<int, string>, 1: string} [peran yang boleh menyetujui, keterangan] */
    public function approverRoles(float $amount): array
    {
        $rule = ApprovalRule::forDocument(ApprovalRule::TYPE_PAYMENT_REQUEST, $amount);
        if ($rule) {
            return [$rule->roles ?? [], $rule->approver_label ?: implode(', ', $rule->roles ?? [])];
        }
        foreach (self::DEFAULT_APPROVERS as $max => $roles) {
            if ($amount <= $max) {
                return [$roles, implode(', ', $roles) . ', Owner / Super Admin'];
            }
        }

        return [[], 'Owner / Super Admin'];
    }

    /** @return array{allowed: bool, reason: string|null, self: bool} */
    public function canApprove(ExpenseRequest $request, ?User $user): array
    {
        $deny = fn (string $r) => ['allowed' => false, 'reason' => $r, 'self' => false];
        if (! $user) {
            return $deny('Belum masuk.');
        }
        if ($request->status !== ExpenseRequest::STATUS_SUBMITTED) {
            return $deny('Permintaan tidak sedang menunggu persetujuan.');
        }
        if (! Access::allows('keuangan.payment-request.approve', $user)) {
            return $deny('Anda tidak dicentang "Setujui / Tolak" Permintaan Bayar.');
        }
        $self = (int) $request->requested_by === (int) $user->getKey();
        if (Access::isSuper($user)) {
            return ['allowed' => true, 'reason' => null, 'self' => $self];
        }
        if ($self) {
            return $deny('Pemisahan tugas: pembuat tidak boleh menyetujui permintaannya sendiri.');
        }
        [$roles, $label] = $this->approverRoles((float) $request->amount);
        if (! $user->hasAnyRole($roles)) {
            return $deny("Nominal ini disetujui oleh: {$label}.");
        }

        return ['allowed' => true, 'reason' => null, 'self' => false];
    }

    public function approve(ExpenseRequest $request, User $user, ?string $notes = null): ExpenseRequest
    {
        $check = $this->canApprove($request, $user);
        if (! $check['allowed']) {
            throw ValidationException::withMessages(['status' => $check['reason'] . ' Solusi: minta penyetuju yang berwenang.']);
        }
        if ($check['self'] && mb_strlen(trim((string) $notes)) < 10) {
            throw ValidationException::withMessages(['approval_notes' => 'Menyetujui permintaan sendiri wajib diberi alasan minimal 10 karakter.']);
        }
        DB::transaction(function () use ($request, $user, $notes) {
            $request->update([
                'status' => ExpenseRequest::STATUS_APPROVED, 'approved_by' => $user->getKey(), 'approved_at' => now(),
                'approval_notes' => $notes ? trim($notes) : null,
            ]);
            $this->signatures->sign($request, 'approved', $user);
        });

        return $request->fresh();
    }

    public function reject(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        $check = $this->canApprove($request, $user);
        if (! $check['allowed']) {
            throw ValidationException::withMessages(['status' => $check['reason']]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['approval_notes' => 'Tulis alasan penolakan (minimal 5 karakter) agar pembuat tahu yang harus diperbaiki.']);
        }
        $request->update(['status' => ExpenseRequest::STATUS_REJECTED, 'approved_by' => $user->getKey(), 'approved_at' => now(), 'approval_notes' => trim($reason)]);
        $request->signatures()->where('role', 'requested')->delete();   // diajukan ulang setelah diperbaiki

        return $request->fresh();
    }

    // ------------------------------------------------------------------ pembayaran

    public function canPay(ExpenseRequest $request, ?User $user): bool
    {
        return $user && $request->status === ExpenseRequest::STATUS_APPROVED
            && Access::allows('keuangan.payment-request.pay', $user)
            && (Access::isSuper($user) || $user->hasAnyRole(self::PAYER_ROLES));
    }

    /** Bayar: transaksi Kas & Bank keluar (rincian = baris biaya) + jurnal otomatis. */
    public function pay(ExpenseRequest $request, User $user, int $cashCoaId, string $date): ExpenseRequest
    {
        if (! $this->canPay($request, $user)) {
            throw ValidationException::withMessages(['status' => 'Permintaan belum disetujui atau Anda bukan kasir / finance. Solusi: minta kasir atau finance yang membayar.']);
        }
        $cashCoa = ChartOfAccount::query()->whereKey($cashCoaId)->where('is_active', true)
            ->where(fn ($q) => $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%'))->first();
        if (! $cashCoa) {
            throw ValidationException::withMessages(['payment_coa_id' => 'Pilih akun Kas atau Bank (kode 1111 / 1112) yang aktif.']);
        }
        $request->loadMissing('lines');

        DB::transaction(function () use ($request, $user, $cashCoa, $date) {
            $isBank = str_starts_with((string) $cashCoa->code, '1112');
            $trx = CashBankTransaction::create([
                'number' => $this->cashBank->generateNumber($isBank ? 'BK' : 'KK'),
                'date' => $date,
                'type' => $isBank ? 'bank_out' : 'cash_out',
                'cash_bank_account_id' => CashBankAccount::where('coa_id', $cashCoa->id)->value('id'),
                'account_coa_id' => $cashCoa->id,
                'offset_coa_id' => $request->lines->first()->coa_id,
                'amount' => $request->amount,
                'counterparty' => $request->payee,
                'description' => "Permintaan Bayar {$request->number}: {$request->description}",
                'cabang_id' => $request->cabang_id,
            ]);
            foreach ($request->lines as $line) {
                CashBankTransactionDetail::create([
                    'cash_bank_transaction_id' => $trx->id, 'chart_of_account_id' => $line->coa_id,
                    'amount' => $line->amount, 'description' => $line->description,
                ]);
            }
            $this->cashBank->postTransaction($trx);

            $request->update([
                'status' => ExpenseRequest::STATUS_PAID, 'paid_by' => $user->getKey(), 'paid_at' => now(),
                'payment_coa_id' => $cashCoa->id, 'cash_bank_transaction_id' => $trx->id,
            ]);
            $this->signatures->sign($request, 'paid', $user);
        });

        return $request->fresh();
    }

    public function cancel(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        $this->assertStatus($request, [ExpenseRequest::STATUS_DRAFT, ExpenseRequest::STATUS_SUBMITTED, ExpenseRequest::STATUS_APPROVED, ExpenseRequest::STATUS_REJECTED], 'dibatalkan');
        if ((int) $request->requested_by !== (int) $user->getKey() && ! Access::isSuper($user) && ! $user->hasAnyRole(['Finance Manager'])) {
            throw ValidationException::withMessages(['status' => 'Hanya pembuat, Finance Manager, atau Owner yang bisa membatalkan.']);
        }
        $request->update(['status' => ExpenseRequest::STATUS_CANCELLED, 'cancel_reason' => mb_substr(trim($reason), 0, 500)]);

        return $request->fresh();
    }

    // ------------------------------------------------------------------ bantu

    /** User ini peninjau (melihat semua permintaan, bukan hanya miliknya)? */
    public function isReviewer(User $user): bool
    {
        if (Access::isSuper($user) || $user->hasAnyRole(self::PAYER_ROLES)) {
            return true;
        }
        $roles = collect(self::DEFAULT_APPROVERS)->flatten()
            ->merge(ApprovalRule::query()->where('document_type', ApprovalRule::TYPE_PAYMENT_REQUEST)->where('is_active', true)->pluck('roles')->flatten())
            ->unique()->all();

        return $user->hasAnyRole($roles);
    }

    /**
     * Akun yang boleh dipakai di Permintaan Bayar: Beban, atau Aset selain Kas/Bank, Piutang & Persediaan
     * (mis. asuransi dibayar dimuka, peralatan kantor). Akun induk tidak boleh.
     */
    public static function expenseAccountQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ChartOfAccount::query()->where('is_active', true)->whereDoesntHave('children')
            ->where(fn ($q) => $q->where('type', 'Expense')->orWhere(fn ($a) => $a->where('type', 'Asset')
                ->where('code', 'not like', '1111%')->where('code', 'not like', '1112%')
                ->where('code', 'not like', '1120%')->where('code', 'not like', '1-12%')->where('code', 'not like', '1140%')
                ->where('name', 'not like', '%piutang%')->where('name', 'not like', '%persediaan%')))
            ->orderByRaw("CASE WHEN type = 'Expense' THEN 0 ELSE 1 END")->orderBy('code');
    }

    private function assertExpenseAccounts(array $coaIds): void
    {
        $valid = self::expenseAccountQuery()->whereIn('id', $coaIds)->pluck('id')->all();
        if (count(array_unique($coaIds)) !== count($valid)) {
            throw ValidationException::withMessages(['lines' => 'Akun harus akun Beban (atau Aset non-piutang / non-persediaan) yang aktif dan bukan akun induk / Kas / Bank.']);
        }
    }

    private function assertStatus(ExpenseRequest $request, array $allowed, string $verb): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Permintaan berstatus \"{$request->statusLabel()}\" tidak bisa {$verb}."]);
        }
    }

    private function nextNumber(): string
    {
        $prefix = 'PB-' . now()->format('Ym') . '-';
        $last = ExpenseRequest::withTrashed()->where('number', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
