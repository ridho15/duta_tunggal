<?php

namespace Tests\Unit;

use App\Filament\Resources\Reports\AgeingReportResource;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeingReportResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_ageing_resource_columns_using_service_as_of_date(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create();
        $customer = Customer::factory()->create(['name' => 'AR Customer', 'cabang_id' => $branch->id]);
        $supplier = Supplier::factory()->create(['perusahaan' => 'AP Supplier', 'cabang_id' => $branch->id]);

        $receivableInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-AR-RESOURCE',
            'invoice_date' => '2025-02-10',
            'due_date' => '2025-04-05',
            'customer_name' => $customer->name,
            'cabang_id' => $branch->id,
        ]);

        $payableInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-AP-RESOURCE',
            'invoice_date' => '2024-12-20',
            'due_date' => '2025-03-15',
            'supplier_name' => $supplier->perusahaan,
            'cabang_id' => $branch->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'invoice_id' => $receivableInvoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'total' => 200000,
            'paid' => 0,
            'remaining' => 200000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ])->load(['customer', 'invoice', 'ageingSchedule']);

        $payable = AccountPayable::factory()->create([
            'invoice_id' => $payableInvoice->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'total' => 300000,
            'paid' => 0,
            'remaining' => 300000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ])->load(['supplier', 'invoice', 'ageingSchedule']);

        $this->assertSame('AR Customer', AgeingReportResource::customerOrSupplierName($receivable));
        $this->assertSame('AP Supplier', AgeingReportResource::customerOrSupplierName($payable));
        $this->assertSame(45, AgeingReportResource::daysOutstandingForRecord($receivable, '2025-03-27'));
        $this->assertSame('31–60', AgeingReportResource::bucketForRecord($receivable, '2025-03-27'));
        $this->assertSame(97, AgeingReportResource::daysOutstandingForRecord($payable, '2025-03-27'));
        $this->assertSame('>90', AgeingReportResource::bucketForRecord($payable, '2025-03-27'));
    }
}