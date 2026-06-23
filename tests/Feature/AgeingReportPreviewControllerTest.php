<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\AgeingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeingReportPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ageing_report_preview_matches_ageing_report_service(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Ageing Preview Branch']);
        $customer = Customer::factory()->create(['cabang_id' => $branch->id]);
        $supplier = Supplier::factory()->create(['cabang_id' => $branch->id]);

        $receivableInvoice = Invoice::factory()->create([
            'invoice_date' => '2025-02-10',
            'due_date' => '2025-03-05',
            'customer_name' => $customer->name,
            'cabang_id' => $branch->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'invoice_id' => $receivableInvoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'total' => 700000,
            'paid' => 100000,
            'remaining' => 600000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        AgeingSchedule::create([
            'from_model_type' => AccountReceivable::class,
            'from_model_id' => $receivable->id,
            'invoice_date' => '2025-02-10',
            'due_date' => '2025-03-05',
            'days_outstanding' => 45,
            'bucket' => '31–60',
        ]);

        $payableInvoice = Invoice::factory()->create([
            'invoice_date' => '2025-01-01',
            'due_date' => '2025-01-20',
            'supplier_name' => $supplier->perusahaan,
            'cabang_id' => $branch->id,
        ]);

        $payable = AccountPayable::factory()->create([
            'invoice_id' => $payableInvoice->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'total' => 500000,
            'paid' => 0,
            'remaining' => 500000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        AgeingSchedule::create([
            'from_model_type' => AccountPayable::class,
            'from_model_id' => $payable->id,
            'invoice_date' => '2025-01-01',
            'due_date' => '2025-01-20',
            'days_outstanding' => 95,
            'bucket' => '>90',
        ]);

        $expected = app(AgeingReportService::class)->generate([
            'as_of_date' => '2025-03-27',
            'cabang_id' => $branch->id,
            'report_type' => 'both',
        ]);

        $response = $this->actingAs($user)->get(route('reports.ageing-report.preview', [
            'as_of_date' => '2025-03-27',
            'cabang_id' => $branch->id,
            'report_type' => 'both',
        ]));

        $response->assertOk();
        $response->assertViewHas('arSummary', fn (array $summary) => $summary === $expected['arSummary']);
        $response->assertViewHas('apSummary', fn (array $summary) => $summary === $expected['apSummary']);
        $response->assertViewHas('expectedInflow', $expected['expectedInflow']);
        $response->assertViewHas('expectedOutflow', $expected['expectedOutflow']);
        $response->assertViewHas('overdueAR', $expected['overdueAR']);
        $response->assertViewHas('overdueAP', $expected['overdueAP']);
        $response->assertViewHas('arRecords', function ($records) use ($expected) {
            return $records->count() === $expected['arRecords']->count()
                && $records->first()?->aging_bucket_computed === $expected['arRecords']->first()?->aging_bucket_computed
                && (float) ($records->first()?->remaining ?? 0) === (float) ($expected['arRecords']->first()?->remaining ?? 0);
        });
        $response->assertViewHas('apRecords', function ($records) use ($expected) {
            return $records->count() === $expected['apRecords']->count()
                && $records->first()?->aging_bucket_computed === $expected['apRecords']->first()?->aging_bucket_computed
                && (float) ($records->first()?->remaining ?? 0) === (float) ($expected['apRecords']->first()?->remaining ?? 0);
        });
        $response->assertSee('600.000');
        $response->assertSee('500.000');
    }
}