<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchaseReportPage;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class PurchaseReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles if they don't exist
        if (!Role::where('name', 'Admin')->exists()) {
            Role::create(['name' => 'Admin']);
        }

        // Create a cabang for testing
        Cabang::factory()->create(['nama' => 'Test Cabang', 'kode' => 'TC']);
    }

    public function test_purchase_report_page_can_be_rendered()
    {
        $this->actingAs($this->createUserWithRole('Admin'));

        Livewire::test(PurchaseReportPage::class)
            ->assertOk();
    }

    public function test_purchase_report_displays_data_correctly()
    {
        $cabang = \App\Models\Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 1000000,
        ]);

        $this->actingAs($this->createUserWithRole('Admin'));

        Livewire::test(PurchaseReportPage::class)
            ->assertSee('Laporan Pembelian')
            ->assertSee($supplier->perusahaan);
    }

    public function test_purchase_report_filters_work()
    {
        Cabang::factory()->create();
        $supplier1 = Supplier::factory()->create(['perusahaan' => 'Supplier A']);
        $supplier2 = Supplier::factory()->create(['perusahaan' => 'Supplier B']);

        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier1->id,
            'total_amount' => 1000000,
            'order_date' => now()->subDays(10),
        ]);

        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier2->id,
            'total_amount' => 2000000,
            'order_date' => now(),
        ]);

        $this->actingAs($this->createUserWithRole('Admin'));

        // Test filter by supplier
        Livewire::test(PurchaseReportPage::class)
            ->set('supplier_id', $supplier1->id)
            ->call('updateFilters')
            ->assertSee('Supplier A');

        // Test without filter should see both
        Livewire::test(PurchaseReportPage::class)
            ->assertSee('Supplier A')
            ->assertSee('Supplier B');
    }

    private function createUserWithRole($role): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);
        $user->manage_type = ['all'];
        $user->save();
        return $user;
    }
}