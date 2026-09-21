<?php

namespace App\Services;

use App\Models\ApprovalOverride;
use App\Models\ApprovalRule;
use App\Models\OrderRequest;
use App\Models\PaymentRequest;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ApprovalControlService
{
    public const TIER_1_MAX_AMOUNT = 10000000; // Rp 10.000.000

    public const TOP_TIER_ROLES = [
        'Super Admin',
        'Owner',
        'Finance Manager',
        'Admin',
    ];

    public const SALES_TIER_1_ROLES = [
        'Sales Manager',
        'Super Admin',
        'Owner',
        'Finance Manager',
        'Admin',
    ];

    public const PURCHASING_TIER_1_ROLES = [
        'Purchasing Manager',
        'Inventory Manager',
        'Super Admin',
        'Owner',
        'Finance Manager',
        'Admin',
    ];

    public const PAYMENT_REQUEST_TIER_1_ROLES = [
        'Finance Manager',
        'Accounting',
        'Super Admin',
        'Owner',
        'Admin',
    ];

    /**
     * Check if user is the creator of the document (anti-self-approval rule).
     * Super Admin and Owner have system override capability for emergency workflows,
     * but standard staff and managers cannot approve their own documents.
     */
    public function isSelfApproval(?User $user, Model $record): bool
    {
        if (! $user) {
            return false;
        }

        $creatorId = match (true) {
            $record instanceof OrderRequest => $record->created_by,
            $record instanceof SaleOrder => $record->created_by,
            $record instanceof PaymentRequest => $record->requested_by,
            default => $record->created_by ?? $record->user_id ?? null,
        };

        if (! $creatorId) {
            return false;
        }

        return (int) $user->id === (int) $creatorId;
    }

    /**
     * Determine whether the user can approve the given Order Request.
     */
    public function canApproveOrderRequest(?User $user, OrderRequest $orderRequest): array
    {
        if (! $user) {
            return ['allowed' => false, 'reason' => 'Pengguna tidak terautentikasi.'];
        }

        if (! $user->hasPermissionTo('approve order request')) {
            return ['allowed' => false, 'reason' => 'Anda tidak memiliki hak akses persetujuan Order Request.'];
        }

        // 1. Anti-Self-Approval
        if ($this->isSelfApproval($user, $orderRequest) && ! $user->hasRole(['Super Admin', 'Owner'])) {
            return [
                'allowed' => false,
                'reason' => 'Pemisahan tugas (Segregation of Duties): Pembuat dokumen tidak boleh menyetujui Order Request miliknya sendiri.',
            ];
        }

        // 2. Nominal Tier Check
        $amount = $this->resolveOrderRequestTotal($orderRequest);
        if ($amount > self::TIER_1_MAX_AMOUNT) {
            if (! $user->hasRole(self::TOP_TIER_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan bertingkat: Nilai Order Request di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager.',
                ];
            }
        } else {
            if (! $user->hasRole(self::PURCHASING_TIER_1_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan Order Request membutuhkan wewenang Purchasing Manager / Direktur.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Determine whether the user can approve the given Sales Order.
     * (Sejak T3.1 didelegasikan ke canApprove() yang membaca aturan dari tabel approval_rules; pesan & hasil identik dengan
     * aturan awal, sehingga pemanggil lama tidak berubah.)
     */
    public function canApproveSaleOrder(?User $user, SaleOrder $saleOrder): array
    {
        return $this->canApprove($user, $saleOrder);
    }

    /** Jenis dokumen yang memakai aturan persetujuan generik. */
    public function documentType(Model $document): ?string
    {
        return match (true) {
            $document instanceof SaleOrder => ApprovalRule::TYPE_SALE_ORDER,
            $document instanceof Quotation => ApprovalRule::TYPE_QUOTATION,
            $document instanceof \App\Models\CreditNote => ApprovalRule::TYPE_CREDIT_NOTE,
            default => null,
        };
    }

    /**
     * Apakah $user boleh menyetujui $document (Quotation / Sales Order)? Urutan: izin → pemisahan tugas → aturan nominal.
     *
     * @return array{allowed: bool, reason: string|null, self_override: bool, rule_id: int|null}
     */
    public function canApprove(?User $user, Model $document): array
    {
        $type = $this->documentType($document);
        $deny = fn (string $reason) => ['allowed' => false, 'reason' => $reason, 'self_override' => false, 'rule_id' => null];

        if ($type === null) {
            return $deny('Jenis dokumen ini tidak memiliki aturan persetujuan.');
        }

        if (! $user) {
            return $deny('Pengguna tidak terautentikasi.');
        }

        $label = ApprovalRule::TYPES[$type];
        $permission = match ($type) {
            ApprovalRule::TYPE_QUOTATION => 'approve quotation',
            ApprovalRule::TYPE_CREDIT_NOTE => 'approve credit note',
            default => 'response sales order',
        };

        if (! $user->hasPermissionTo($permission)) {
            return $deny("Anda tidak memiliki hak akses persetujuan {$label}.");
        }

        // 1. Anti-Self-Approval (Super Admin/Owner: override darurat — wajib beralasan & tercatat bila aturan T3.1 hidup, D24)
        $selfOverride = false;
        if ($this->isSelfApproval($user, $document)) {
            if (! $user->hasRole(['Super Admin', 'Owner'])) {
                return $deny("Pemisahan tugas (Segregation of Duties): Pembuat {$label} tidak boleh menyetujui dokumen miliknya sendiri.");
            }
            $selfOverride = true;
        }

        // 2. Nominal Tier Check — dari tabel approval_rules; bila belum ada tabel/aturan → konstanta lama (perilaku semula)
        $amount = (float) ($document->total_amount ?? $document->total ?? 0);
        $rule = $this->ruleFor($type, $amount);
        [$roles, $approverLabel, $above] = $rule
            ? [$rule->roles ?? [], $rule->approver_label, $rule->above_amount !== null ? (float) $rule->above_amount : null]
            : ($amount > self::TIER_1_MAX_AMOUNT
                ? [self::TOP_TIER_ROLES, 'Direktur / Owner / Finance Manager', self::TIER_1_MAX_AMOUNT]
                : [self::SALES_TIER_1_ROLES, 'Sales Manager / Direktur', null]);

        if (! $user->hasRole($roles)) {
            return $deny($above !== null
                ? sprintf('Persetujuan bertingkat: Nilai %s di atas Rp %s wajib disetujui oleh %s.', $label, number_format($above, 0, ',', '.'), $approverLabel)
                : "Persetujuan {$label} membutuhkan wewenang {$approverLabel}.");
        }

        return ['allowed' => true, 'reason' => null, 'self_override' => $selfOverride, 'rule_id' => $rule?->id];
    }

    private function ruleFor(string $type, float $amount): ?ApprovalRule
    {
        return Schema::hasTable('approval_rules') ? ApprovalRule::forDocument($type, $amount) : null;
    }

    /** Penyetuju yang lolos hanya karena override (menyetujui dokumen buatan sendiri) wajib memberi alasan bila flag `approval_rules` hidup. */
    public function requiresOverrideReason(?User $user, Model $document): bool
    {
        if (! config('sales.controls.approval_rules', false)) {
            return false;
        }

        $check = $this->canApprove($user, $document);

        return $check['allowed'] && $check['self_override'];
    }

    /**
     * Penegakan DI SERVICE (flag `sales.controls.approval_rules`): API/aksi lain tidak bisa menghindari aturan. Mencatat override.
     *
     * @param  array{override_reason?: string|null}  $options
     *
     * @throws ValidationException
     */
    public function enforce(?User $user, Model $document, array $options = []): void
    {
        if (! config('sales.controls.approval_rules', false)) {
            return;
        }

        $check = $this->canApprove($user, $document);
        if (! $check['allowed']) {
            throw ValidationException::withMessages(['approval' => $check['reason']]);
        }

        if (! $check['self_override']) {
            return;
        }

        $reason = trim((string) ($options['override_reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['override_reason' => 'Anda menyetujui dokumen buatan sendiri (override Owner/Super Admin). Alasan wajib diisi, minimal 10 karakter.']);
        }

        ApprovalOverride::create([
            'document_type' => $this->documentType($document),
            'document_id' => $document->getKey(),
            'user_id' => $user->getKey(),
            'approval_rule_id' => $check['rule_id'],
            'amount' => (float) ($document->total_amount ?? $document->total ?? 0),
            'reason' => $reason,
            'context' => ['kind' => 'self_approval', 'document_number' => $document->so_number ?? $document->quotation_number ?? $document->credit_note_number ?? null],
        ]);
    }

    /**
     * Determine whether the user can approve the given Payment Request.
     */
    public function canApprovePaymentRequest(?User $user, PaymentRequest $paymentRequest): array
    {
        if (! $user) {
            return ['allowed' => false, 'reason' => 'Pengguna tidak terautentikasi.'];
        }

        // 1. Anti-Self-Approval
        if ($this->isSelfApproval($user, $paymentRequest) && ! $user->hasRole(['Super Admin', 'Owner'])) {
            return [
                'allowed' => false,
                'reason' => 'Pemisahan tugas (Segregation of Duties): Pembuat Permintaan Pembayaran tidak boleh menyetujui dokumen miliknya sendiri.',
            ];
        }

        // 2. Nominal Tier Check
        $amount = (float) ($paymentRequest->total_amount ?? 0);
        if ($amount > self::TIER_1_MAX_AMOUNT) {
            if (! $user->hasRole(self::TOP_TIER_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan bertingkat: Nilai Permintaan Pembayaran di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager.',
                ];
            }
        } else {
            if (! $user->hasRole(self::PAYMENT_REQUEST_TIER_1_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan Permintaan Pembayaran membutuhkan wewenang Finance Manager / Direktur.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function resolveOrderRequestTotal(OrderRequest $orderRequest): float
    {
        return (float) $orderRequest->orderRequestItem->sum(function ($item) {
            if (! empty($item->subtotal)) {
                return (float) $item->subtotal;
            }
            return (float) ($item->quantity * ($item->unit_price ?? 0));
        });
    }
}
