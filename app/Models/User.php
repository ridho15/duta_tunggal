<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\LogsGlobalActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }
    use LogsGlobalActivity;
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
            // 'manage_type' => 'array', // Removed to handle manually
        ];
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->kode_user)) {
                $latestUser = static::where('kode_user', 'like', 'USR-%')
                    ->orderBy('id', 'desc')
                    ->first();

                $number = 1;
                if ($latestUser && preg_match('/^USR-(\d+)$/', $latestUser->kode_user, $matches)) {
                    $number = intval($matches[1]) + 1;
                } else {
                    $count = static::where('kode_user', 'like', 'USR-%')->count();
                    $number = $count + 1;
                }

                while (static::where('kode_user', 'USR-' . str_pad($number, 4, '0', STR_PAD_LEFT))->exists()) {
                    $number++;
                }

                $user->kode_user = 'USR-' . str_pad($number, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function getManageTypeAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        return explode(',', $value ?? '');
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

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->spatieHasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $exception) {
            return false;
        }
    }
}
