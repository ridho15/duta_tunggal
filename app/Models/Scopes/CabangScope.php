<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CabangScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // Jika user tidak login atau tidak memiliki cabang_id, skip scope
        if (!$user || !$user->cabang_id) {
            return;
        }

        // Jika user memiliki 'all' dalam manage_type, boleh akses semua cabang
        $manageType = $user->manage_type ?? [];
        if (is_array($manageType) && in_array('all', $manageType)) {
            return;
        }

        // Filter berdasarkan cabang user
        $builder->where('cabang_id', $user->cabang_id);
    }
}