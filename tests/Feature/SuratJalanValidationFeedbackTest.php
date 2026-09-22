<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratJalanResource\Pages\CreateSuratJalan;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuratJalanValidationFeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function authenticateUser(Cabang $cabang): User
    {
        $user = User::factory()->create([
            'email' => 'sj_test_' . uniqid() . '@example.com',
            'username' => 'sj_test_' . uniqid(),
            'kode_user' => 'SJU' . rand(100, 999),
            'cabang_id' => $cabang->id,
            'manage_type' => 'all',
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        return $user;
    }

    public function test_create_surat_jalan_with_unapproved_do_shows_danger_notification_and_error(): void
    {
        $cabang = Cabang::first() ?? Cabang::factory()->create();
        $this->authenticateUser($cabang);
        $customer = Customer::first() ?? Customer::factory()->create();

        $so = SaleOrder::factory()->create([
            'cabang_id' => $cabang->id,
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $unapprovedDo = DeliveryOrder::create([
            'so_id' => $so->id,
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'do_number' => 'DO-TEST-UNAPPROVED-' . uniqid(),
            'status' => 'draft',
            'delivery_date' => now(),
        ]);

        $sjNumber = 'SJ-TEST-UNAPP-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$unapprovedDo->id],
                'cabang_id' => $cabang->id,
            ])
            ->call('create');

        $component->assertHasFormErrors(['deliveryOrder'])
            ->assertNotified('Gagal Membuat Surat Jalan');

        $this->assertDatabaseMissing('surat_jalans', [
            'sj_number' => $sjNumber,
        ]);
    }

    public function test_create_surat_jalan_with_approved_do_succeeds(): void
    {
        $cabang = Cabang::first() ?? Cabang::factory()->create();
        $this->authenticateUser($cabang);
        $customer = Customer::first() ?? Customer::factory()->create();

        $so = SaleOrder::factory()->create([
            'cabang_id' => $cabang->id,
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);

        $approvedDo = DeliveryOrder::create([
            'so_id' => $so->id,
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'do_number' => 'DO-TEST-APPROVED-' . uniqid(),
            'status' => 'approved',
            'delivery_date' => now(),
        ]);

        $sjNumber = 'SJ-TEST-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$approvedDo->id],
                'cabang_id' => $cabang->id,
            ])
            ->call('create');

        $component->assertHasNoFormErrors();

        $this->assertDatabaseHas('surat_jalans', [
            'sj_number' => $sjNumber,
            'status' => SuratJalan::STATUS_ISSUED,
        ]);
    }
}
