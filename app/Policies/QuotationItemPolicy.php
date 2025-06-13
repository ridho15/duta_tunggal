<?php

namespace App\Policies;

use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuotationItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any quotation item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QuotationItem $quotationItem): bool
    {
        return $user->hasPermissionTo('view quotation item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create quotation item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QuotationItem $quotationItem): bool
    {
        return $user->hasPermissionTo('update quotation item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QuotationItem $quotationItem): bool
    {
        return $user->hasPermissionTo('delete quotation item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QuotationItem $quotationItem): bool
    {
        return $user->hasPermissionTo('restore quotation item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QuotationItem $quotationItem): bool
    {
        return $user->hasPermissionTo('force-delete quotation item');
    }
}
