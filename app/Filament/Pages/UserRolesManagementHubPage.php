<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class UserRolesManagementHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.user-roles-management-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen User dan Role';

    protected static ?string $navigationLabel = 'Manajemen User & Role';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'user-roles-management-hub';

    protected static function getHubClasses(): array
    {
        return [
            UserResource::class,
            RoleResource::class,
            PermissionResource::class,
        ];
    }
}