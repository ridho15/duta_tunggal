<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login as EventsLogin;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EventsLogin $event): void
    {
         activity()
            ->causedBy($event->user)
            ->withProperties([
                'ip' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
            ])
            ->log('User login');
    }
}
