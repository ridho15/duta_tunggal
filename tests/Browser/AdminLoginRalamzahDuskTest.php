<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdminLoginRalamzahDuskTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::updateOrCreate(
            ['email' => 'ralamzah@gmail.com'],
            [
                'name' => 'Ridho Al Amzah',
                'first_name' => 'Ridho',
                'last_name' => 'Al Amzah',
                'username' => 'ridho_al_amzah',
                'kode_user' => 'ridho',
                'status' => true,
                'manage_type' => 'all',
                'password' => Hash::make('ridho123'),
            ]
        );
    }

    private function setInputById(Browser $browser, string $id, string $value): void
    {
        $jsId = addslashes($id);
        $jsValue = addslashes($value);

        $browser->script("
            (function() {
                var el = document.querySelector('[id=\"{$jsId}\"]');
                if (!el) return;

                el.focus();
                el.value = '{$jsValue}';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.blur();
            })();
        ");
    }

    public function test_admin_login_with_seeded_ralamzah_account_succeeds(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin/login')
                ->waitForText('Masuk ke akun Anda', 10)
                ->screenshot('login-ralamzah-01-initial');

            $this->setInputById($browser, 'data.email', 'ralamzah@gmail.com');
            $this->setInputById($browser, 'data.password', 'ridho123');

            $browser->script("(function(){ var btn = document.querySelector('form button[type=\"submit\"]'); if (btn) btn.click(); })();");

            $browser->pause(2500)
                ->screenshot('login-ralamzah-02-after-submit');

            $path = (string) ($browser->script('return window.location.pathname;')[0] ?? '');

            $this->assertNotSame('/admin/login', $path, 'Login stayed on /admin/login, indicating credentials were rejected.');
            $this->assertStringStartsWith('/admin', $path, 'Login did not end up in admin panel path.');
        });
    }
}
