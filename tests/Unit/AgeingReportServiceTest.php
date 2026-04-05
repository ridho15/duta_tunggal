<?php

namespace Tests\Unit;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\AgeingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeingReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_bucket_boundaries_from_invoice_date_against_as_of_date(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $branch->id]);
        $asOfDate = '2025-03-31';

        $cases = [
            ['suffix' => 'CUR', 'invoice_date' => '2025-03-01', 'expected_bucket' => 'Current'],
            ['suffix' => '3160', 'invoice_date' => '2025-01-30', 'expected_bucket' => '31–60'],
            ['suffix' => '6190', 'invoice_date' => '2024-12-31', 'expected_bucket' => '61–90'],
            ['suffix' => 'OVER', 'invoice_date' => '2024-12-30', 'expected_bucket' => '>90'],
        ];

        foreach ($cases as $index => $case) {
            $invoice = Invoice::factory()->create([
                'invoice_number' => 'INV-' . $case['suffix'],
                'invoice_date' => $case['invoice_date'],
                'due_date' => '2025-04-30',
                'customer_name' => $customer->name,
                'cabang_id' => $branch->id,
            ]);

            AccountReceivable::factory()->create([
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'cabang_id' => $branch->id,
                'total' => 100000 + ($index * 1000),
                'paid' => 0,
                'remaining' => 100000 + ($index * 1000),
                'status' => 'Belum Lunas',
                'created_by' => $user->id,
            ]);
        }

        $records = app(AgeingReportService::class)->getReceivableRecords([
            'as_of_date' => $asOfDate,
            'cabang_id' => $branch->id,
        ])->values();

        $this->assertSame('Current', $records[0]->aging_bucket_computed);
        $this->assertSame('31–60', $records[1]->aging_bucket_computed);
        $this->assertSame('61–90', $records[2]->aging_bucket_computed);
        $this->assertSame('>90', $records[3]->aging_bucket_computed);
    }

    public function test_it_projects_cash_flow_relative_to_as_of_date_window(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $branch->id]);
        $supplier = Supplier::factory()->create(['cabang_id' => $branch->id]);

        $receivableIncluded = Invoice::factory()->create([
            'invoice_number' => 'INV-CF-AR-IN',
            'invoice_date' => '2025-03-01',
            'due_date' => '2025-03-31',
            'customer_name' => $customer->name,
            'cabang_id' => $branch->id,
        ]);

        $receivableExcluded = Invoice::factory()->create([
            'invoice_number' => 'INV-CF-AR-OUT',
            'invoice_date' => '2025-03-01',
            'due_date' => '2025-04-01',
            'customer_name' => $customer->name,
            'cabang_id' => $branch->id,
        ]);

        $payableIncluded = Invoice::factory()->create([
            'invoice_number' => 'INV-CF-AP-IN',
            'invoice_date' => '2025-02-01',
            'due_date' => '2025-03-15',
            'supplier_name' => $supplier->perusahaan,
            'cabang_id' => $branch->id,
        ]);

        $payableExcluded = Invoice::factory()->create([
            'invoice_number' => 'INV-CF-AP-OUT',
            'invoice_date' => '2025-02-01',
            'due_date' => '2025-04-05',
            'supplier_name' => $supplier->perusahaan,
            'cabang_id' => $branch->id,
        ]);

        AccountReceivable::factory()->create([
            'invoice_id' => $receivableIncluded->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'total' => 300000,
            'paid' => 0,
            'remaining' => 300000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        AccountReceivable::factory()->create([
            'invoice_id' => $receivableExcluded->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'total' => 900000,
            'paid' => 0,
            'remaining' => 900000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        AccountPayable::factory()->create([
            'invoice_id' => $payableIncluded->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'total' => 125000,
            'paid' => 0,
            'remaining' => 125000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        AccountPayable::factory()->create([
            'invoice_id' => $payableExcluded->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'total' => 800000,
            'paid' => 0,
            'remaining' => 800000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        $projection = app(AgeingReportService::class)->projectCashFlow([
            'as_of_date' => '2025-03-01',
            'cabang_id' => $branch->id,
        ], 30);

        $this->assertSame(300000.0, $projection['receivables']);
        $this->assertSame(125000.0, $projection['payables']);
        $this->assertSame(175000.0, $projection['net_cash_flow']);
    }
}