<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class MasterViewsSingleAlertTest extends TestCase
{
    protected function getAdminUser(): User
    {
        return User::first() ?? User::factory()->create();
    }

    /**
     * Data provider untuk ke-10 route master view yang telah diremediasi
     */
    public static function masterRoutesProvider(): array
    {
        return [
            'Master Satuan' => ['master.satuan.index'],
            'Master Kategori' => ['master.kategori.index'],
            'Master Mata Uang' => ['master.mata-uang.index'],
            'Master Cabang' => ['master.cabang.index'],
            'Master Pajak' => ['master.pajak.index'],
            'Master COA' => ['master.coa.index'],
            'Master Gudang' => ['master.gudang.index'],
            'Master Driver' => ['master.driver.index'],
            'Master Kendaraan' => ['master.kendaraan.index'],
        ];
    }

    /**
     * @dataProvider masterRoutesProvider
     */
    public function test_master_view_renders_exactly_one_success_alert(string $routeName): void
    {
        $user = $this->getAdminUser();
        $message = "Pesan Sukses Verifikasi {$routeName}";

        $response = $this->actingAs($user)
            ->withSession(['success' => $message])
            ->get(route($routeName));

        $response->assertStatus(200);

        // Verifikasi teks pesan muncul tepat 1 KALI (tidak ganda/duplikat)
        $content = $response->getContent();
        $occurrences = substr_count($content, $message);
        $this->assertSame(1, $occurrences, "Route {$routeName} harus menampilkan tepat 1 alert sukses, tetapi ditemukan {$occurrences}");
    }

    /**
     * @dataProvider masterRoutesProvider
     */
    public function test_master_view_renders_exactly_one_error_alert(string $routeName): void
    {
        $user = $this->getAdminUser();
        $message = "Pesan Error Verifikasi {$routeName}";

        $response = $this->actingAs($user)
            ->withSession(['error' => $message])
            ->get(route($routeName));

        $response->assertStatus(200);

        // Verifikasi teks pesan muncul tepat 1 KALI (tidak ganda/duplikat)
        $content = $response->getContent();
        $occurrences = substr_count($content, $message);
        $this->assertSame(1, $occurrences, "Route {$routeName} harus menampilkan tepat 1 alert error, tetapi ditemukan {$occurrences}");
    }
}
