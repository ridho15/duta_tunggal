<?php

namespace App\Services\Reports;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgeingReportService
{
    public function generate(array $filters = []): array
    {
        $asOf = !empty($filters['as_of_date'])
            ? Carbon::parse($filters['as_of_date'])->endOfDay()
            : now()->endOfDay();

        $cabangId = !empty($filters['cabang_id']) ? (int) $filters['cabang_id'] : null;
        $reportType = $filters['report_type'] ?? 'receivables';

        $arRecords = $reportType !== 'payables'
            ? $this->getReceivableRecords([
                'as_of_date' => $asOf,
                'cabang_id' => $cabangId,
            ])
            : collect();

        $apRecords = $reportType !== 'receivables'
            ? $this->getPayableRecords([
                'as_of_date' => $asOf,
                'cabang_id' => $cabangId,
            ])
            : collect();

        $arSummary = $this->summarizeBuckets($arRecords);
        $apSummary = $this->summarizeBuckets($apRecords);

        $projection30 = $this->projectCashFlow([
            'as_of_date' => $asOf,
            'cabang_id' => $cabangId,
        ], 30);

        $overdue = $this->calculateOverdue([
            'as_of_date' => $asOf,
            'cabang_id' => $cabangId,
        ]);

        return [
            'arRecords' => $arRecords,
            'apRecords' => $apRecords,
            'arSummary' => $arSummary,
            'apSummary' => $apSummary,
            'asOfDate' => $asOf->toDateString(),
            'cabangId' => $cabangId,
            'reportType' => $reportType,
            'expectedInflow' => $projection30['receivables'],
            'expectedOutflow' => $projection30['payables'],
            'overdueAR' => $overdue['receivables'],
            'overdueAP' => $overdue['payables'],
        ];
    }

    public function getReceivableRecords(array $filters = []): Collection
    {
        $asOf = $this->normalizeAsOfDate($filters['as_of_date'] ?? null);
        $cabangId = !empty($filters['cabang_id']) ? (int) $filters['cabang_id'] : null;

        $records = AccountReceivable::with(['customer', 'invoice', 'ageingSchedule', 'cabang'])
            ->where('remaining', '>', 0)
            ->when($cabangId, fn ($query) => $query->where('cabang_id', $cabangId))
            ->orderBy('id')
            ->get();

        return $records->map(function ($record) use ($asOf) {
            $record->days_outstanding_computed = $this->resolveDaysOutstanding($record, $asOf);
            $record->aging_bucket_computed = $this->resolveBucketLabel($record->days_outstanding_computed);

            return $record;
        });
    }

    public function getPayableRecords(array $filters = []): Collection
    {
        $asOf = $this->normalizeAsOfDate($filters['as_of_date'] ?? null);
        $cabangId = !empty($filters['cabang_id']) ? (int) $filters['cabang_id'] : null;

        $records = AccountPayable::with(['supplier', 'invoice', 'ageingSchedule'])
            ->where('remaining', '>', 0)
            ->when($cabangId, function ($query) use ($cabangId) {
                $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('cabang_id', $cabangId));
            })
            ->orderBy('id')
            ->get();

        return $records->map(function ($record) use ($asOf) {
            $record->days_outstanding_computed = $this->resolveDaysOutstanding($record, $asOf);
            $record->aging_bucket_computed = $this->resolveBucketLabel($record->days_outstanding_computed);

            return $record;
        });
    }

    public function summarizeBuckets(Collection $records, bool $asciiKeys = false): array
    {
        $summary = [
            'Current' => 0.0,
            '31–60' => 0.0,
            '61–90' => 0.0,
            '>90' => 0.0,
        ];

        foreach ($summary as $bucket => $_) {
            $summary[$bucket] = (float) $records->where('aging_bucket_computed', $bucket)->sum('remaining');
        }

        if (! $asciiKeys) {
            return $summary;
        }

        $mapped = [
            'current' => ['count' => 0, 'amount' => 0.0],
            '31-60' => ['count' => 0, 'amount' => 0.0],
            '61-90' => ['count' => 0, 'amount' => 0.0],
            '>90' => ['count' => 0, 'amount' => 0.0],
            'total' => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($records as $record) {
            $key = match ($record->aging_bucket_computed) {
                'Current' => 'current',
                '31–60' => '31-60',
                '61–90' => '61-90',
                default => '>90',
            };

            $mapped[$key]['count']++;
            $mapped[$key]['amount'] += (float) $record->remaining;
            $mapped['total']['count']++;
            $mapped['total']['amount'] += (float) $record->remaining;
        }

        return $mapped;
    }

    public function projectCashFlow(array $filters = [], int $days = 30): array
    {
        $asOf = $this->normalizeAsOfDate($filters['as_of_date'] ?? null);
        $cabangId = !empty($filters['cabang_id']) ? (int) $filters['cabang_id'] : null;
        $futureDate = $asOf->copy()->addDays($days);

        $receivables = AccountReceivable::query()
            ->where('remaining', '>', 0)
            ->whereHas('invoice', fn ($query) => $query->whereBetween('due_date', [$asOf->toDateString(), $futureDate->toDateString()]))
            ->when($cabangId, fn ($query) => $query->where('cabang_id', $cabangId))
            ->sum('remaining');

        $payables = AccountPayable::query()
            ->where('remaining', '>', 0)
            ->whereHas('invoice', function ($query) use ($asOf, $futureDate, $cabangId) {
                $query->whereBetween('due_date', [$asOf->toDateString(), $futureDate->toDateString()]);
                if ($cabangId) {
                    $query->where('cabang_id', $cabangId);
                }
            })
            ->sum('remaining');

        return [
            'receivables' => (float) $receivables,
            'payables' => (float) $payables,
            'net_cash_flow' => (float) $receivables - (float) $payables,
        ];
    }

    public function calculateOverdue(array $filters = []): array
    {
        $asOf = $this->normalizeAsOfDate($filters['as_of_date'] ?? null);
        $cabangId = !empty($filters['cabang_id']) ? (int) $filters['cabang_id'] : null;

        $receivables = AccountReceivable::query()
            ->where('remaining', '>', 0)
            ->whereHas('invoice', fn ($query) => $query->where('due_date', '<', $asOf->toDateString()))
            ->when($cabangId, fn ($query) => $query->where('cabang_id', $cabangId))
            ->sum('remaining');

        $payables = AccountPayable::query()
            ->where('remaining', '>', 0)
            ->whereHas('invoice', function ($query) use ($asOf, $cabangId) {
                $query->where('due_date', '<', $asOf->toDateString());
                if ($cabangId) {
                    $query->where('cabang_id', $cabangId);
                }
            })
            ->sum('remaining');

        return [
            'receivables' => (float) $receivables,
            'payables' => (float) $payables,
        ];
    }

    public function resolveDaysOutstanding($record, Carbon|string|null $asOfDate = null): int
    {
        $asOf = $this->normalizeAsOfDate($asOfDate);

        if (!empty($record->ageingSchedule?->days_outstanding)) {
            return (int) $record->ageingSchedule->days_outstanding;
        }

        if ($record->invoice?->invoice_date) {
            return (int) Carbon::parse($record->invoice->invoice_date)->diffInDays($asOf, false);
        }

        return 0;
    }

    public function resolveBucketLabel(int|object $value, Carbon|string|null $asOfDate = null): string
    {
        $daysOutstanding = is_object($value)
            ? $this->resolveDaysOutstanding($value, $asOfDate)
            : (int) $value;

        if ($daysOutstanding <= 30) {
            return 'Current';
        }

        if ($daysOutstanding <= 60) {
            return '31–60';
        }

        if ($daysOutstanding <= 90) {
            return '61–90';
        }

        return '>90';
    }

    private function normalizeAsOfDate(Carbon|string|null $asOfDate): Carbon
    {
        if ($asOfDate instanceof Carbon) {
            return $asOfDate->copy()->endOfDay();
        }

        if (is_string($asOfDate) && $asOfDate !== '') {
            return Carbon::parse($asOfDate)->endOfDay();
        }

        return now()->endOfDay();
    }
}