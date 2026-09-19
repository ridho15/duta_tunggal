<?php

namespace App\Services;

use App\Models\OrderRequest;
use App\Models\PaymentRequest;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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
     */
    public function canApproveSaleOrder(?User $user, SaleOrder $saleOrder): array
    {
        if (! $user) {
            return ['allowed' => false, 'reason' => 'Pengguna tidak terautentikasi.'];
        }

        if (! $user->hasPermissionTo('response sales order')) {
            return ['allowed' => false, 'reason' => 'Anda tidak memiliki hak akses persetujuan Sales Order.'];
        }

        // 1. Anti-Self-Approval
        if ($this->isSelfApproval($user, $saleOrder) && ! $user->hasRole(['Super Admin', 'Owner'])) {
            return [
                'allowed' => false,
                'reason' => 'Pemisahan tugas (Segregation of Duties): Pembuat Sales Order tidak boleh menyetujui dokumen miliknya sendiri.',
            ];
        }

        // 2. Nominal Tier Check
        $amount = (float) ($saleOrder->total_amount ?? 0);
        if ($amount > self::TIER_1_MAX_AMOUNT) {
            if (! $user->hasRole(self::TOP_TIER_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan bertingkat: Nilai Sales Order di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager.',
                ];
            }
        } else {
            if (! $user->hasRole(self::SALES_TIER_1_ROLES)) {
                return [
                    'allowed' => false,
                    'reason' => 'Persetujuan Sales Order membutuhkan wewenang Sales Manager / Direktur.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
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
