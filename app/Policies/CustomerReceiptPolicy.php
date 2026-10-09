<?php

namespace App\Policies;

use App\Models\CustomerReceipt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerReceiptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any customer receipt');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerReceipt $customerReceipt): bool
    {
        if (! $user->canAccessCabang($customerReceipt->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('view customer receipt');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create customer receipt');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerReceipt $customerReceipt): bool
    {
        if (! $user->canAccessCabang($customerReceipt->cabang_id)) {
            return false;
        }

        if (in_array(strtolower((string) $customerReceipt->status), ['partial', 'paid'], true)) {
            return false;
        }

        // T3.3 (flag sales.controls.doc_lock): penerimaan berjurnal tidak diubah/dihapus langsung — gunakan Batalkan Penerimaan.
        if (\App\Services\DocumentLock::blocks($customerReceipt, 'update')) {
            return false;
        }

        return $user->hasPermissionTo('update customer receipt');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerReceipt $customerReceipt): bool
    {
        if (! $user->canAccessCabang($customerReceipt->cabang_id)) {
            return false;
        }

        if (in_array(strtolower((string) $customerReceipt->status), ['partial', 'paid'], true) || $customerReceipt->journalEntries()->exists()) {
            return false;
        }

        // T3.3 (flag sales.controls.doc_lock): penerimaan berjurnal tidak diubah/dihapus langsung — gunakan Batalkan Penerimaan.
        if (\App\Services\DocumentLock::blocks($customerReceipt, 'delete')) {
            return false;
        }

        return $user->hasPermissionTo('delete customer receipt');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerReceipt $customerReceipt): bool
    {
        if (! $user->canAccessCabang($customerReceipt->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('restore customer receipt');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerReceipt $customerReceipt): bool
    {
        if (! $user->canAccessCabang($customerReceipt->cabang_id)) {
            return false;
        }

        if (in_array(strtolower((string) $customerReceipt->status), ['partial', 'paid'], true) || $customerReceipt->journalEntries()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('force-delete customer receipt');
    }
}
