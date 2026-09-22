<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratJalanResource\Pages\ListSuratJalans;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuratJalanRecapTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Driver $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Recap User',
            'email' => 'recap-user@example.com',
            'username' => 'recapuser',
            'password' => bcrypt('password'),
            'first_name' => 'Recap',
            'kode_user' => 'RCP001',
        ]);

        $this->cabang = Cabang::factory()->create();
        $this->driver = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);
    }

    #[Test]
    public function it_filters_rekap_surat_jalan_by_date_range_and_status(): void
    {
        $approvedDeliveryOrder = DeliveryOrder::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $matching = SuratJalan::create([
            'sj_number' => 'SJ-REKAP-001',
            'issued_at' => '2026-03-15 09:00:00',
            'status' => 1,
            'created_by' => $this->user->id,
            'signed_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $matching->deliveryOrder()->attach($approvedDeliveryOrder->id);

        $outsideDate = SuratJalan::create([
            'sj_number' => 'SJ-REKAP-002',
            'issued_at' => '2026-03-25 09:00:00',
            'status' => 1,
            'created_by' => $this->user->id,
            'signed_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $outsideDate->deliveryOrder()->attach($approvedDeliveryOrder->id);

        $draft = SuratJalan::create([
            'sj_number' => 'SJ-REKAP-003',
            'issued_at' => '2026-03-16 09:00:00',
            'status' => 0,
            'created_by' => $this->user->id,
            'signed_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $draft->deliveryOrder()->attach($approvedDeliveryOrder->id);

        $records = ListSuratJalans::getRekapSuratJalanQuery('2026-03-10', '2026-03-20', '1')->get();

        $this->assertCount(1, $records);
        $this->assertSame('SJ-REKAP-001', $records->first()->sj_number);
        $this->assertTrue($records->first()->deliveryOrder->first()->relationLoaded('salesOrders'));
    }

    #[Test]
    public function it_streams_a_rekap_surat_jalan_pdf_download(): void
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-REKAP-PDF-001',
            'issued_at' => '2026-03-18 09:00:00',
            'status' => 1,
            'created_by' => $this->user->id,
            'signed_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        $response = ListSuratJalans::streamRekapSuratJalanPdf('2026-03-10', '2026-03-20', '1');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('rekap-surat-jalan-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }
}