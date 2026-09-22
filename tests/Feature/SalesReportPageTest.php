<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesReportPage;
use App\Models\SaleOrder;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_page_can_be_rendered()
    {
        $this->actingAs($this->createUserWithRole('admin'));

        Livewire::test(SalesReportPage::class)
            ->assertOk();
    }

    public function test_sales_report_displays_data_correctly()
    {
        $customer = Customer::factory()->create();
        SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 1000000,
        ]);

        $this->actingAs($this->createUserWithRole('admin'));

        Livewire::test(SalesReportPage::class)
            ->assertSee('Laporan Penjualan')
            ->assertSee($customer->name);
    }

    public function test_sales_report_filters_work()
    {
        $customer1 = Customer::factory()->create(['name' => 'Customer A']);
        $customer2 = Customer::factory()->create(['name' => 'Customer B']);

        SaleOrder::factory()->create([
            'customer_id' => $customer1->id,
            'so_number' => 'SO-FILTER-A',
            'total_amount' => 1000000,
            'order_date' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        SaleOrder::factory()->create([
            'customer_id' => $customer2->id,
            'so_number' => 'SO-FILTER-B',
            'total_amount' => 2000000,
            'order_date' => now(),
            'created_at' => now(),
        ]);

        $this->actingAs($this->createUserWithRole('admin'));

        // Test filter by customer (mode Pesanan/SO — default halaman kini Penjualan/Invoice)
        Livewire::test(SalesReportPage::class)
            ->set('mode', 'order')
            ->set('customer_id', $customer1->id)
            ->assertSee('SO-FILTER-A')
            ->assertDontSee('SO-FILTER-B');
    }

    private function createUserWithRole($role)
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);
        $user->manage_type = ['all'];
        $user->save();
        return $user;
    }
}