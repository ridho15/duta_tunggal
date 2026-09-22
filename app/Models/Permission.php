<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as ModelsPermission;

class Permission extends ModelsPermission
{
    use LogsGlobalActivity;

    /**
     * Permit setting a textual description via mass-assignment.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];
}
