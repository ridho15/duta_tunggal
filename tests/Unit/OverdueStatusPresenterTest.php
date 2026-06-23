<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Support\OverdueStatusPresenter;
use Carbon\Carbon;
use stdClass;
use Tests\TestCase;

class OverdueStatusPresenterTest extends TestCase
{
    public function test_it_formats_current_and_overdue_labels_for_unpaid_records(): void
    {
        Carbon::setTestNow('2026-04-04 10:00:00');

        $current = $this->record('2026-04-10');
        $overdue = $this->record('2026-03-25');
        $severe = $this->record('2026-01-15');

        $presenter = app(OverdueStatusPresenter::class);

        $this->assertSame(0, $presenter->daysOverdue($current));
        $this->assertSame('💚 CURRENT', $presenter->overdueGroupLabel($current));
        $this->assertSame(10, $presenter->daysOverdue($overdue));
        $this->assertSame('⏰ OVERDUE', $presenter->overdueGroupLabel($overdue));
        $this->assertSame('warning', $presenter->daysOverdueColor(10));
        $this->assertSame(79, $presenter->daysOverdue($severe));
        $this->assertSame('🚨 OVERDUE 60+ Days', $presenter->overdueGroupLabel($severe));
        $this->assertSame('danger', $presenter->daysOverdueColor(79));

        Carbon::setTestNow();
    }

    public function test_it_handles_paid_and_deleted_invoice_records(): void
    {
        $paid = $this->record('2026-03-01', PaymentStatus::PAID->value);
        $deleted = $this->record('2026-03-01', PaymentStatus::UNPAID->value, OverdueStatusPresenter::DELETED_INVOICE);

        $presenter = app(OverdueStatusPresenter::class);

        $this->assertSame('✅ PAID', $presenter->overdueGroupLabel($paid));
        $this->assertSame(0, $presenter->daysOverdue($paid));
        $this->assertSame('DELETED', $presenter->daysOverdue($deleted));
        $this->assertSame('🗑️ DELETED INVOICE', $presenter->overdueGroupLabel($deleted));
        $this->assertSame('danger', $presenter->daysOverdueColor('DELETED'));
        $this->assertSame('danger', $presenter->dueDateColor($deleted));
    }

    private function record(string $dueDate, string $status = 'Belum Lunas', ?string $overdueGroup = null): object
    {
        $record = new stdClass();
        $record->status = $status;
        $record->overdue_group = $overdueGroup;

        $invoice = new stdClass();
        $invoice->due_date = $dueDate;
        $record->invoice = $invoice;

        return $record;
    }
}