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
        // Sales Manager dan Admin bisa lihat semua
        if ($user->hasRole(['Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
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
        if (! $user->canAccessCabang($deliveryOrder->cabang_id)) {
            return false;
        }

        // Sales Manager dan Admin bisa lihat semua delivery order
        if ($user->hasRole(['Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
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
        if (! $user->canAccessCabang($deliveryOrder->cabang_id)) {
            return false;
        }

        // T3.3 (flag sales.controls.doc_lock): DO yang sudah diproses terkunci; koreksi lewat Batalkan DO / Pengiriman Gagal.
        if (\App\Services\DocumentLock::blocks($deliveryOrder, 'update')) {
            return false;
        }

        // Sales Manager dan Admin bisa update semua delivery order
        if ($user->hasRole(['Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
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
        if (! $user->canAccessCabang($deliveryOrder->cabang_id)) {
            return false;
        }

        // P0-1: DO yang sudah dikirim / sedang dikirim / selesai TIDAK BOLEH dihapus untuk mencegah stok & jurnal hilang tanpa jejak.
        if (in_array($deliveryOrder->status, ['sent', 'completed', 'delivered', 'delivering'], true)) {
            return false;
        }

        // DO yang sudah memiliki faktur tidak boleh dihapus
        $hasInvoices = \App\Models\Invoice::where(function ($q) use ($deliveryOrder) {
            $q->where('from_model_type', DeliveryOrder::class)
              ->where('from_model_id', $deliveryOrder->id);
        })->orWhereJsonContains('delivery_orders', (string) $deliveryOrder->id)
          ->orWhereJsonContains('delivery_orders', (int) $deliveryOrder->id)
          ->exists();

        if ($hasInvoices) {
            return false;
        }

        // T3.3 (flag sales.controls.doc_lock): DO yang sudah diproses terkunci; koreksi lewat Batalkan DO / Pengiriman Gagal.
        if (\App\Services\DocumentLock::blocks($deliveryOrder, 'delete')) {
            return false;
        }

        // Sales Manager dan Admin bisa delete semua delivery order
        if ($user->hasRole(['Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
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
        if (! $user->canAccessCabang($deliveryOrder->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('restore delivery order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeliveryOrder $deliveryOrder): bool
    {
        if (! $user->canAccessCabang($deliveryOrder->cabang_id)) {
            return false;
        }

        if (in_array($deliveryOrder->status, ['sent', 'completed', 'delivered', 'delivering'], true)) {
            return false;
        }

        $hasInvoices = \App\Models\Invoice::where(function ($q) use ($deliveryOrder) {
            $q->where('from_model_type', DeliveryOrder::class)
              ->where('from_model_id', $deliveryOrder->id);
        })->orWhereJsonContains('delivery_orders', (string) $deliveryOrder->id)
          ->orWhereJsonContains('delivery_orders', (int) $deliveryOrder->id)
          ->exists();

        if ($hasInvoices) {
            return false;
        }

        return $user->hasRole('Super Admin') && $user->hasPermissionTo('delete delivery order');
    }
}
