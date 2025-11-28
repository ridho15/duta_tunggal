<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Warehouse;

class JournalBranchResolver
{
    /**
     * Resolve cabang_id for a given source model if possible.
     */
    public function resolve(?object $source): ?int
    {
        if (!$source) return null;

        // 1) Direct property cabang_id
        if (isset($source->cabang_id) && $source->cabang_id) {
            return (int) $source->cabang_id;
        }

        // 2) If has warehouse -> cabang_id
        if (method_exists($source, 'warehouse') && $source->warehouse) {
            $warehouse = $source->warehouse;
            if ($warehouse && isset($warehouse->cabang_id)) {
                return (int) $warehouse->cabang_id;
            }
        }

        // 3) Manufacturing: through manufacturingOrder -> warehouse -> cabang_id
        if (method_exists($source, 'manufacturingOrder') && $source->manufacturingOrder) {
            $mo = $source->manufacturingOrder;
            if ($mo && $mo->warehouse && isset($mo->warehouse->cabang_id)) {
                return (int) $mo->warehouse->cabang_id;
            }
        }

        // 4) Invoice-like chains: invoice -> fromModel (may hold cabang_id or warehouse)
        if (isset($source->invoice) && $source->invoice && method_exists($source->invoice, 'fromModel')) {
            $fm = $source->invoice->fromModel;
            if ($fm) {
                return $this->resolve($fm);
            }
        }

        // 5) Fallback: if created_by user has cabang_id
        if (isset($source->created_by) && $source->created_by && class_exists('App\\Models\\User')) {
            $user = \App\Models\User::find($source->created_by);
            if ($user && $user->cabang_id) {
                return (int) $user->cabang_id;
            }
        }

        return null;
    }

    /**
     * Resolve department_id for a given source model if possible.
     */
    public function resolveDepartment(?object $source): ?int
    {
        if (!$source) return null;

        // Direct property department_id
        if (isset($source->department_id) && $source->department_id) {
            return (int) $source->department_id;
        }

        // Fallback: if created_by user has department_id
        if (isset($source->created_by) && $source->created_by && class_exists('App\\Models\\User')) {
            $user = \App\Models\User::find($source->created_by);
            if ($user && $user->department_id) {
                return (int) $user->department_id;
            }
        }

        return null;
    }

    /**
     * Resolve project_id for a given source model if possible.
     */
    public function resolveProject(?object $source): ?int
    {
        if (!$source) return null;

        // Direct property project_id
        if (isset($source->project_id) && $source->project_id) {
            return (int) $source->project_id;
        }

        // Fallback: if created_by user has project_id
        if (isset($source->created_by) && $source->created_by && class_exists('App\\Models\\User')) {
            $user = \App\Models\User::find($source->created_by);
            if ($user && $user->project_id) {
                return (int) $user->project_id;
            }
        }

        return null;
    }
}
