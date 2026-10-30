<?php

/**
 * Tahap 3 — halaman Bantuan (video per peran) dan mode latihan.
 */

use App\Filament\Pages\BantuanPage;
use App\Filament\Resources\HelpVideoResource;
use App\Models\Cabang;
use App\Models\HelpVideo;
use App\Models\User;
use App\Support\MenuAccess;

function tahap3User(string $role): User
{
    $user = User::factory()->create(['cabang_id' => Cabang::query()->value('id')]);
    $user->assignRole($role);
    MenuAccess::flush();

    return $user;
}

it('Bantuan hanya menampilkan video untuk peran user dan video umum', function () {
    HelpVideo::create(['title' => 'Cara approve SO', 'role_code' => 'KSR', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    HelpVideo::create(['title' => 'Cara kirim DO dari HP', 'role_code' => 'GDG', 'url' => 'https://example.com/kirim.mp4']);
    HelpVideo::create(['title' => 'Mengenal menu', 'role_code' => null, 'url' => 'https://www.youtube.com/watch?v=abcdefghijk']);

    $this->actingAs(tahap3User('Kasir'))
        ->get(BantuanPage::getUrl())
        ->assertOk()
        ->assertSee('Cara approve SO')
        ->assertSee('Mengenal menu')
        ->assertDontSee('Cara kirim DO dari HP')
        ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
});

it('kelola video tutorial hanya untuk Owner / Admin', function () {
    $this->actingAs(tahap3User('Kasir'))->get(HelpVideoResource::getUrl('index'))->assertForbidden();
    $this->actingAs(tahap3User('Owner'))->get(HelpVideoResource::getUrl('index'))->assertOk();
});

it('banner mode latihan hanya tampil bila diaktifkan', function () {
    $owner = tahap3User('Owner');

    $this->actingAs($owner)->get('/admin')->assertOk()->assertDontSee('MODE LATIHAN');

    config(['training.enabled' => true]);
    $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('MODE LATIHAN');
});

it('latihan:reset menolak berjalan bila mode latihan mati atau database bukan database latihan', function () {
    $this->artisan('latihan:reset', ['--force' => true])->assertFailed();

    config(['training.enabled' => true]);
    $this->artisan('latihan:reset', ['--force' => true])
        ->expectsOutputToContain('tidak mengandung "latihan"')
        ->assertFailed();
});
