<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuotationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any quotation');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('view quotation');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create quotation');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Quotation $quotation): bool
    {
        // Hanya Draft / Ditolak yang boleh diubah; selebihnya terkunci (revisi lewat versi baru).
        return $quotation->isEditable() && $user->hasPermissionTo('update quotation');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->isEditable() && $user->hasPermissionTo('delete quotation');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('restore quotation');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('force-delete quotation');
    }

    /**
     * Buat revisi (versi baru) dari quotation yang terkunci: Disetujui / Kedaluwarsa dan belum digantikan.
     */
    public function revise(User $user, Quotation $quotation): bool
    {
        return in_array($quotation->status, [Quotation::STATUS_APPROVE, Quotation::STATUS_EXPIRED], true)
            && $quotation->superseded_at === null
            && $user->hasPermissionTo('create quotation');
    }

    public function requestApprove(User $user, ?Quotation $quotation = null): bool
    {
        return $user->hasPermissionTo('request-approve quotation');
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        // T3.1 (flag sales.controls.approval_rules): pemisahan tugas + ambang nominal juga berlaku untuk Quotation (D6/D26).
        if (config('sales.controls.approval_rules', false)) {
            return app(\App\Services\ApprovalControlService::class)->canApprove($user, $quotation)['allowed'];
        }

        return $user->hasPermissionTo('approve quotation');
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('reject quotation');
    }
}
