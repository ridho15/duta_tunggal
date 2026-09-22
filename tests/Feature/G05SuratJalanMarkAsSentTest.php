<?php

/**
 * G-05: Surat Jalan no longer owns a "mark as sent" delivery transition.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);
});

it('surat jalan resource no longer defines mark as sent action', function () {
    $resourceSource = file_get_contents(app_path('Filament/Resources/SuratJalanResource.php'));

    expect($resourceSource)->not->toContain("Action::make('mark_as_sent')")
        ->and($resourceSource)->not->toContain('Mark as Sent');
});

it('surat jalan create page redirects back to index after save', function () {
    $pageSource = file_get_contents(app_path('Filament/Resources/SuratJalanResource/Pages/CreateSuratJalan.php'));

    expect($pageSource)->toContain('getRedirectUrl')
        ->and($pageSource)->toContain("getUrl('index')");
});
