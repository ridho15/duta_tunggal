<?php

namespace App\Traits;

use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;

trait HasHubModuleAccess
{
    /**
     * Get the list of resource and page classes contained in this hub.
     *
     * @return array<class-string>
     */
    abstract protected static function getHubClasses(): array;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Owner', 'super_admin', 'owner'])) {
            return true;
        }

        foreach (static::getHubClasses() as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (is_subclass_of($class, Resource::class)) {
                if ($class::canViewAny()) {
                    return true;
                }
            } elseif (is_subclass_of($class, Page::class)) {
                if ($class::canAccess()) {
                    return true;
                }
            }
        }

        return false;
    }
}
