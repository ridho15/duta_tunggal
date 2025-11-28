<?php

namespace App\Livewire;

use Filament\Notifications\Livewire\DatabaseNotifications as BaseDatabaseNotifications;

/**
 * Local wrapper for Filament's DatabaseNotifications Livewire component.
 *
 * Purpose: provide a public `$data` property so Livewire won't throw
 * PublicPropertyNotFoundException when an unrelated payload targets this
 * component due to front-end snapshot collisions.
 */
class DatabaseNotifications extends BaseDatabaseNotifications
{
    // Add a public data property to accept incoming Livewire payloads safely.
    public array $data = [];
}
