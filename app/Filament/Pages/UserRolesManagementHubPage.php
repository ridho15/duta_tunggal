<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class UserRolesManagementHubPage extends Page
{
    protected static string $view = 'filament.pages.user-roles-management-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen User dan Role';

    protected static ?string $navigationLabel = 'Pusat Manajemen User & Role';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'user-roles-management-hub';
}