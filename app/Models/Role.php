<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as ModelsRole;

class Role extends ModelsRole
{
    use LogsGlobalActivity;

    /**
     * Allow description to be set via mass assignment when seeding or
     * editing roles programmatically. Spatie's base model already defines
     * name and guard_name as fillable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];
}
