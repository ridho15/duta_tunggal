<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InventoryStockPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any inventory stock');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('view inventory stock');
    }

    /**
     * Determine whether the user can create models.
     * Stok hanya boleh dibuat melalui dokumen transaksi (Penerimaan Barang / Penyesuaian).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     * Stok hanya boleh berubah melalui dokumen transaksi resmi.
     */
    public function update(User $user, InventoryStock $inventoryStock): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryStock $inventoryStock): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventoryStock $inventoryStock): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryStock $inventoryStock): bool
    {
        return false;
    }
}
