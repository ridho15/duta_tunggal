<?php

namespace App\Policies;

use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliveryOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super Sales, Sales Manager dan Admin bisa lihat semua
        if ($user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
            return $user->hasPermissionTo('view any delivery order');
        }
        
        // Sales hanya bisa lihat jika ada permission
        if ($user->hasRole('Sales')) {
            return $user->hasPermissionTo('view any delivery order');
        }
        
        return $user->hasPermissionTo('view any delivery order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeliveryOrder $deliveryOrder): bool
    {
        // Super Sales, Sales Manager dan Admin bisa lihat semua delivery order
        if ($user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
            return $user->hasPermissionTo('view delivery order');
        }
        
        // Sales hanya bisa lihat delivery order dari sale order yang dia buat
        if ($user->hasRole('Sales')) {
            // Cek apakah delivery order ini terkait dengan sale order yang dibuat oleh user ini
            $userSaleOrders = $deliveryOrder->salesOrders()->where('created_by', $user->id)->exists();
            
            return $user->hasPermissionTo('view delivery order') && $userSaleOrders;
        }
        
        return $user->hasPermissionTo('view delivery order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create delivery order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeliveryOrder $deliveryOrder): bool
    {
        // Super Sales, Sales Manager dan Admin bisa update semua delivery order
        if ($user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
            return $user->hasPermissionTo('update delivery order');
        }
        
        // Sales hanya bisa update delivery order dari sale order yang dia buat
        if ($user->hasRole('Sales')) {
            $userSaleOrders = $deliveryOrder->salesOrders()->where('created_by', $user->id)->exists();
            
            return $user->hasPermissionTo('update delivery order') && $userSaleOrders;
        }
        
        return $user->hasPermissionTo('update delivery order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeliveryOrder $deliveryOrder): bool
    {
        // Super Sales, Sales Manager dan Admin bisa delete semua delivery order
        if ($user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
            return $user->hasPermissionTo('delete delivery order');
        }
        
        // Sales hanya bisa delete delivery order dari sale order yang dia buat
        if ($user->hasRole('Sales')) {
            $userSaleOrders = $deliveryOrder->salesOrders()->where('created_by', $user->id)->exists();
            
            return $user->hasPermissionTo('delete delivery order') && $userSaleOrders;
        }
        
        return $user->hasPermissionTo('delete delivery order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $user->hasPermissionTo('restore delivery order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $user->hasRole('Super Admin');
    }
}
