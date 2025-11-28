<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\LogsGlobalActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasPermissions, LogsGlobalActivity;
    protected $fillable = [
        'name', // Gabungan dari first name dan last name
        'email',
        'username',
        'telepon',
        'password',
        'manage_type',
        'cabang_id', // nullable
        'first_name',
        'last_name',
        'status',
        'kode_user',
        'posisi',
        'signature',
        'warehouse_id' // nullable
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    /**
     * Check if user has a specific permission
     * This is a type-safe wrapper for hasPermissionTo method
     */
    public function hasPermission(string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }
}
