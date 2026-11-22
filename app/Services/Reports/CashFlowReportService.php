<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Models\Reports\CashFlowCashAccount;
use App\Models\Reports\CashFlowSection;
use App\Models\Supplier;
use App\Services\BalanceSheetService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan Arus Kas (PSAK 2) yang DIREKONSILIASI ke Buku Besar.
 *
 *  - Kas & setara kas = COA bertanda is_cash_bank ∪ COA berawalan prefix report_cash_flow_cash_accounts
 *    (fallback 1111/1112 bila keduanya kosong).
 *  - Kas awal  = opening_balance COA kas (konsolidasi / cabang atribusi saldo awal, lihat
 *                BalanceSheetService::openingBalanceCabangId)
 *                + Σ(debit − kredit) jurnal kas sebelum tanggal awal.
 *  - Kas akhir = kas awal + Σ(debit − kredit) jurnal kas dalam periode → kenaikan bersih kas = Δ saldo GL kas.
 *  - Metode langsung: tiap voucher jurnal yang menyentuh kas diklasifikasikan berdasarkan AKUN LAWAN-nya
 *    (operasi / investasi / pendanaan) memakai konfigurasi report_cash_flow_* (prefix terpanjang menang),
 *    lalu sifat akun (ekuitas → pendanaan, aset tidak lancar → investasi, kewajiban jangka panjang → pendanaan,
 *    selebihnya → operasi). Transfer antar akun kas tidak menghasilkan arus kas (kecuali biayanya).
 *  - Metode tidak langsung: laba bersih + penyesuaian non-kas + perubahan modal kerja; investasi & pendanaan
 *    dihitung dari GL dengan klasifikasi yang sama dengan metode langsung.
 */
class CashFlowReportService
{
    private const SECTION_LABELS = [
        'operating' => 'Aktivitas Operasi',
        'investing' => 'Aktivitas Investasi',
        'financing' => 'Aktivitas Pendanaan',
    ];

    private const FALLBACK_ITEMS = [
        'operating' => ['key' => 'other_operating', 'label' => 'Arus Kas Operasi Lainnya'],
        'investing' => ['key' => 'other_investing', 'label' => 'Arus Kas Investasi Lainnya'],
        'financing' => ['key' => 'other_financing', 'label' => 'Arus Kas Pendanaan Lainnya'],
    ];

    private const DEPRECIATION_PREFIXES = ['631', '632'];

    /**
     * Runtime filters injected from the report UI.
     */
    private array $filters = [];

    private ?array $cashAccountPrefixes = null;

    private ?array $cashCoaIds = null;

    /**
     * Generate cash flow data for a period using the direct or indirect method.
     */
    public function generate(?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $method = $filters['method'] ?? 'direct';
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth();
        $this->filters = $filters;
        $this->cashAccountPrefixes = null;
        $this->cashCoaIds = null;

        if ($method === 'indirect') {
            return $this->generateIndirectMethod($start, $end);
        }

        return $this->generateDirectMethod($start, $end);
    }

    private function generateDirectMethod(Carbon $start, Carbon $end): array
    {
        $sections = $this->buildDirectSections($start, $end);

        return $this->buildReport('direct', $sections, $start, $end);
    }

    private function generateIndirectMethod(Carbon $start, Carbon $end): array
    {
        $branches = array_values(array_filter((array) ($this->filters['branches'] ?? [])));
        $incomeStatement = app(\App\Services\IncomeStatementService::class)->generate([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'cabang_id' => count($branches) === 1 ? (int) $branches[0] : null,
        ]);

        $netIncome = round((float) ($incomeStatement['net_profit'] ?? 0), 2);
        $direct = collect($this->buildDirectSections($start, $end))->keyBy('key');

        $items = [[
            'key' => 'net_income',
            'label' => 'Laba Bersih',
            'amount' => $netIncome,
            'metadata' => [],
        ]];
        foreach ($this->calculateIndirectAdjustments($start, $end) as $adjustment) {
            $items[] = $adjustment;
        }

        // Selisih antara arus kas operasi menurut GL dan (laba bersih + penyesuaian yang dirinci) adalah
        // perubahan akun operasi lain (uang muka, pajak, biaya YMH dibayar, akrual non-kas, dsb.).
        $operatingGl = (float) ($direct->get('operating')['total'] ?? 0);
        $other = round($operatingGl - collect($items)->sum('amount'), 2);
        if (abs($other) >= 0.01) {
            $items[] = [
                'key' => 'other_operating_adjustments',
                'label' => 'Penyesuaian Non-Kas & Perubahan Aset/Kewajiban Operasi Lainnya',
                'amount' => $other,
                'metadata' => [],
            ];
        }

        $sections = [[
            'key' => 'operating',
            'label' => self::SECTION_LABELS['operating'],
            'items' => $items,
            'total' => round(collect($items)->sum('amount'), 2),
        ]];

        foreach ($direct as $key => $section) {
            if ($key !== 'operating') {
                $sections[] = $section;
            }
        }

        return $this->buildReport('indirect', $sections, $start, $end);
    }

    private function buildReport(string $method, array $sections, Carbon $start, Carbon $end): array
    {
        [$opening, $closing] = $this->cashBalances($start, $end);
        $netChange = round($closing - $opening, 2);
        $sectionsTotal = round(collect($sections)->sum('total'), 2);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'method' => $method,
            'sections' => array_values($sections),
            'net_change' => $netChange,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'reconciliation' => [
                'gl_net_change' => $netChange,
                'sections_total' => $sectionsTotal,
                'difference' => round($netChange - $sectionsTotal, 2),
            ],
        ];
    }

    /**
     * Saldo kas awal & akhir dari buku besar kas/bank.
     *
     * @return array{0: float, 1: float}
     */
    private function cashBalances(Carbon $start, Carbon $end): array
    {
        $cashIds = $this->cashCoaIds();
        if (empty($cashIds)) {
            return [0.0, 0.0];
        }

        $openingBalance = BalanceSheetService::includesOpeningBalance($this->filters['branches'] ?? null)
            ? (float) ChartOfAccount::whereIn('id', $cashIds)->sum('opening_balance')
            : 0.0;

        $before = $this->journalQuery()
            ->whereIn('journal_entries.coa_id', $cashIds)
            ->where('journal_entries.date', '<', $start->toDateString())
            ->selectRaw('COALESCE(SUM(journal_entries.debit), 0) - COALESCE(SUM(journal_entries.credit), 0) as net')
            ->value('net');

        $during = $this->journalQuery()
            ->whereIn('journal_entries.coa_id', $cashIds)
            ->whereBetween('journal_entries.date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(journal_entries.debit), 0) - COALESCE(SUM(journal_entries.credit), 0) as net')
            ->value('net');

        $opening = round($openingBalance + (float) $before, 2);

        return [$opening, round($opening + (float) $during, 2)];
    }

    /**
     * Seksi metode langsung: mutasi kas per voucher dialokasikan ke akun lawannya.
     */
    private function buildDirectSections(Carbon $start, Carbon $end): array
    {
        $config = CashFlowSection::query()
            ->with(['items' => fn ($q) => $q->with(['prefixes', 'sources'])->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $sections = [];
        $prefixRules = [];
        $resolverRules = [];

        foreach ($config as $section) {
            $sections[$section->key] = ['key' => $section->key, 'label' => $section->label, 'items' => []];

            foreach ($section->items as $item) {
                $sections[$section->key]['items'][$item->key] = [
                    'key' => $item->key,
                    'label' => $item->label,
                    'amount' => 0.0,
                    'metadata' => [
                        'sources' => $item->sources->sortBy('sort_order')->pluck('label')->values()->toArray(),
                        'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                    ],
                    'coa' => [],
                ];

                foreach ($item->prefixes->where('is_asset', false) as $prefix) {
                    $prefixRules[] = [(string) $prefix->prefix, $section->key, $item->key];
                }

                if ($item->resolver) {
                    $resolverRules[$item->resolver] = [$section->key, $item->key];
                }
            }
        }

        foreach (self::SECTION_LABELS as $key => $label) {
            $sections[$key] ??= ['key' => $key, 'label' => $label, 'items' => []];
        }

        usort($prefixRules, fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));

        $allocations = $this->allocateCashMovements($start, $end);
        $coas = ChartOfAccount::withTrashed()
            ->whereIn('id', collect($allocations)->pluck('coa_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');
        $depositParties = $this->depositParties(collect($allocations));

        foreach ($allocations as $allocation) {
            $coa = $allocation['coa_id'] ? $coas->get($allocation['coa_id']) : null;
            $party = $allocation['source_type'] === Deposit::class ? ($depositParties[$allocation['source_id']] ?? null) : null;
            [$sectionKey, $itemKey] = $this->classify($coa, $party, $prefixRules, $resolverRules);

            if (! isset($sections[$sectionKey]['items'][$itemKey])) {
                $fallback = self::FALLBACK_ITEMS[$sectionKey];
                $sections[$sectionKey]['items'][$itemKey] = [
                    'key' => $fallback['key'],
                    'label' => $fallback['label'],
                    'amount' => 0.0,
                    'metadata' => [
                        'sources' => ['Buku Besar Kas & Bank'],
                        'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                    ],
                    'coa' => [],
                    'is_fallback' => true,
                ];
            }

            $sections[$sectionKey]['items'][$itemKey]['amount'] += $allocation['amount'];
            $coaKey = $coa?->id ?? 0;
            $sections[$sectionKey]['items'][$itemKey]['coa'][$coaKey] ??= [
                'coa_code' => $coa?->code,
                'coa_name' => $coa?->name ?? 'Tanpa akun lawan',
                'inflow' => 0.0,
                'outflow' => 0.0,
            ];
            $sections[$sectionKey]['items'][$itemKey]['coa'][$coaKey][$allocation['amount'] >= 0 ? 'inflow' : 'outflow'] += $allocation['amount'];
        }

        $result = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                $amount = round($item['amount'], 2);
                if (! empty($item['is_fallback']) && abs($amount) < 0.01) {
                    continue;
                }

                $coaRows = collect($item['coa']);
                $item['metadata']['breakdown'] = [
                    'inflow' => $coaRows->filter(fn ($r) => abs($r['inflow']) >= 0.005)
                        ->map(fn ($r) => ['coa_code' => $r['coa_code'], 'coa_name' => $r['coa_name'], 'amount' => round($r['inflow'], 2)])
                        ->values()->toArray(),
                    'outflow' => $coaRows->filter(fn ($r) => abs($r['outflow']) >= 0.005)
                        ->map(fn ($r) => ['coa_code' => $r['coa_code'], 'coa_name' => $r['coa_name'], 'amount' => round($r['outflow'], 2)])
                        ->values()->toArray(),
                ];

                $items[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'amount' => $amount,
                    'metadata' => $item['metadata'],
                ];
            }

            $result[] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'items' => $items,
                'total' => round(collect($items)->sum('amount'), 2),
            ];
        }

        return $result;
    }

    /**
     * Alokasikan mutasi kas tiap voucher ke baris akun lawannya (proporsional terhadap sisi lawan).
     *
     * @return array<int, array{coa_id: ?int, source_type: ?string, source_id: ?int, amount: float}>
     */
    private function allocateCashMovements(Carbon $start, Carbon $end): array
    {
        $cashIds = $this->cashCoaIds();
        if (empty($cashIds)) {
            return [];
        }

        $range = [$start->toDateString(), $end->toDateString()];
        $columns = ['journal_entries.id', 'journal_entries.coa_id', 'journal_entries.date', 'journal_entries.reference',
            'journal_entries.source_type', 'journal_entries.source_id', 'journal_entries.debit', 'journal_entries.credit'];

        $cashLines = $this->journalQuery()
            ->whereIn('journal_entries.coa_id', $cashIds)
            ->whereBetween('journal_entries.date', $range)
            ->get($columns);

        if ($cashLines->isEmpty()) {
            return [];
        }

        $references = $cashLines->whereNull('source_type')->pluck('reference')->filter()->unique()->values();
        $sources = $cashLines->whereNotNull('source_type')->groupBy('source_type')
            ->map(fn (Collection $lines) => $lines->pluck('source_id')->filter()->unique()->values());

        $counterLines = collect();
        foreach ($references->chunk(1000) as $chunk) {
            $counterLines = $counterLines->merge($this->journalQuery()
                ->whereNotIn('journal_entries.coa_id', $cashIds)
                ->whereBetween('journal_entries.date', $range)
                ->whereNull('journal_entries.source_type')
                ->whereIn('journal_entries.reference', $chunk->all())
                ->get($columns));
        }
        foreach ($sources as $sourceType => $ids) {
            foreach ($ids->chunk(1000) as $chunk) {
                $counterLines = $counterLines->merge($this->journalQuery()
                    ->whereNotIn('journal_entries.coa_id', $cashIds)
                    ->whereBetween('journal_entries.date', $range)
                    ->where('journal_entries.source_type', $sourceType)
                    ->whereIn('journal_entries.source_id', $chunk->all())
                    ->get($columns));
            }
        }

        $counterByVoucher = $counterLines->groupBy(fn ($line) => $this->voucherKey($line));
        $allocations = [];

        foreach ($cashLines->groupBy(fn ($line) => $this->voucherKey($line)) as $key => $lines) {
            $cashNet = (float) $lines->sum('debit') - (float) $lines->sum('credit');
            if (abs($cashNet) < 0.005) {
                continue; // transfer antar akun kas / voucher kas yang saling meniadakan
            }

            $counters = $counterByVoucher->get($key, collect());
            $counterNet = (float) $counters->sum('credit') - (float) $counters->sum('debit');

            if ($counters->isEmpty() || abs($counterNet) < 0.005) {
                $allocations[] = ['coa_id' => null, 'source_type' => $lines->first()->source_type, 'source_id' => $lines->first()->source_id, 'amount' => $cashNet];

                continue;
            }

            $ratio = $cashNet / $counterNet;
            foreach ($counters as $counter) {
                $contribution = ((float) $counter->credit - (float) $counter->debit) * $ratio;
                if (abs($contribution) < 0.000001) {
                    continue;
                }
                $allocations[] = [
                    'coa_id' => (int) $counter->coa_id,
                    'source_type' => $counter->source_type,
                    'source_id' => $counter->source_id,
                    'amount' => $contribution,
                ];
            }
        }

        return $allocations;
    }

    /**
     * Satu voucher = satu dokumen sumber pada satu tanggal; jurnal manual = referensi + tanggal.
     */
    private function voucherKey(JournalEntry $line): string
    {
        $date = Carbon::parse($line->date)->toDateString();

        if ($line->source_type && $line->source_id) {
            return "S|{$line->source_type}|{$line->source_id}|{$date}";
        }

        return $line->reference ? "R|{$line->reference}|{$date}" : "L|{$line->id}";
    }

    /**
     * @return array{0: string, 1: string} [section key, item key]
     */
    private function classify(?ChartOfAccount $coa, ?string $depositParty, array $prefixRules, array $resolverRules): array
    {
        if ($depositParty === 'customer' && isset($resolverRules['customerDeposits'])) {
            return $resolverRules['customerDeposits'];
        }
        if ($depositParty === 'supplier' && isset($resolverRules['supplierDeposits'])) {
            return $resolverRules['supplierDeposits'];
        }

        if (! $coa) {
            return ['operating', self::FALLBACK_ITEMS['operating']['key']];
        }

        $code = (string) $coa->code;
        foreach ($prefixRules as [$prefix, $sectionKey, $itemKey]) {
            if ($prefix !== '' && str_starts_with($code, $prefix)) {
                return [$sectionKey, $itemKey];
            }
        }

        if (isset($resolverRules['salesReceipts']) && ($coa->type === 'Revenue' || $this->isReceivable($code))) {
            return $resolverRules['salesReceipts'];
        }

        $section = $this->sectionByNature($coa);

        return [$section, self::FALLBACK_ITEMS[$section]['key']];
    }

    /**
     * Klasifikasi bawaan berdasarkan sifat akun lawan bila tidak ada konfigurasi yang cocok.
     */
    private function sectionByNature(ChartOfAccount $coa): string
    {
        $isCurrent = $coa->is_current;
        if ($isCurrent === null && in_array($coa->type, ['Asset', 'Liability'], true)) {
            $isCurrent = BalanceSheetService::inferIsCurrent((string) $coa->code, $coa->type);
        }

        return match ($coa->type) {
            'Equity' => 'financing',
            'Contra Asset' => 'investing',
            'Asset' => $isCurrent !== null && ! (bool) $isCurrent ? 'investing' : 'operating',
            'Liability' => $isCurrent !== null && ! (bool) $isCurrent ? 'financing' : 'operating',
            default => 'operating',
        };
    }

    private function isReceivable(string $code): bool
    {
        foreach ($this->getConfiguredAccountPrefixes('accounts_receivable', ['1120', '1121', '1122']) as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string> deposit id → 'customer' | 'supplier'
     */
    private function depositParties(Collection $allocations): array
    {
        $ids = $allocations->where('source_type', Deposit::class)->pluck('source_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Deposit::withoutGlobalScopes()->whereIn('id', $ids->all())->pluck('from_model_type', 'id')
            ->map(fn ($type) => match ($type) {
                Customer::class => 'customer',
                Supplier::class => 'supplier',
                default => null,
            })
            ->filter()
            ->toArray();
    }

    /**
     * Calculate adjustments for indirect method cash flow.
     */
    private function calculateIndirectAdjustments(Carbon $start, Carbon $end): array
    {
        $adjustments = [];

        // Beban penyusutan & amortisasi (non-kas) ditambahkan kembali.
        $depreciation = $this->sumMovementByPrefixes(self::DEPRECIATION_PREFIXES, $start, $end);
        if (abs($depreciation) >= 0.01) {
            $adjustments[] = [
                'key' => 'add_depreciation',
                'label' => 'Penambahan Beban Penyusutan',
                'amount' => round($depreciation, 2),
                'metadata' => [],
            ];
        }

        foreach ([
            ['ar_change', 'Penurunan/Penambahan Piutang', 'accounts_receivable', ['1120']],
            ['inventory_change', 'Penurunan/Penambahan Persediaan', 'inventory', ['1140', '1140.01']],
            ['ap_change', 'Kenaikan/Penurunan Hutang', 'accounts_payable', ['2110']],
        ] as [$key, $label, $configKey, $fallbacks]) {
            $change = $this->calculateWorkingCapitalChange($this->getConfiguredAccountPrefixes($configKey, $fallbacks), $start, $end);
            if (abs($change) >= 0.01) {
                $adjustments[] = ['key' => $key, 'label' => $label, 'amount' => round($change, 2), 'metadata' => []];
            }
        }

        return $adjustments;
    }

    /**
     * Perubahan modal kerja: saldo awal − saldo akhir (sisi debit). Kenaikan aset = kas berkurang,
     * kenaikan kewajiban = kas bertambah.
     */
    private function calculateWorkingCapitalChange(array $prefixes, Carbon $start, Carbon $end): float
    {
        if (empty($prefixes)) {
            return 0.0;
        }

        return -1 * $this->sumMovementByPrefixes($prefixes, $start, $end);
    }

    /**
     * Σ(debit − kredit) jurnal non-penutup dalam periode untuk akun berawalan salah satu prefix.
     */
    private function sumMovementByPrefixes(array $prefixes, Carbon $start, Carbon $end): float
    {
        $query = $this->journalQuery()
            ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
            ->where(function (Builder $q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('chart_of_accounts.code', 'like', $prefix . '%');
                }
            })
            ->where(fn (Builder $q) => $q->whereNull('journal_entries.journal_type')->orWhere('journal_entries.journal_type', '!=', 'closing'))
            ->whereBetween('journal_entries.date', [$start->toDateString(), $end->toDateString()]);

        return (float) $query->selectRaw('COALESCE(SUM(journal_entries.debit), 0) - COALESCE(SUM(journal_entries.credit), 0) as net')->value('net');
    }

    private function journalQuery(): Builder
    {
        $query = JournalEntry::query();

        if ($this->hasBranchFilter()) {
            $query->whereIn('journal_entries.cabang_id', array_values(array_filter((array) $this->filters['branches'])));
        }

        if (! empty($this->filters['project_id'])) {
            $query->where('journal_entries.project_id', $this->filters['project_id']);
        }

        if (! empty($this->filters['division_id'])) {
            $query->where('journal_entries.department_id', $this->filters['division_id']);
        }

        return $query;
    }

    private function hasBranchFilter(): bool
    {
        return ! empty(array_filter((array) ($this->filters['branches'] ?? [])));
    }

    /**
     * ID COA kas & setara kas.
     */
    private function cashCoaIds(): array
    {
        if ($this->cashCoaIds !== null) {
            return $this->cashCoaIds;
        }

        $prefixes = $this->getCashAccountPrefixes();
        $restrictToConfigured = ! empty($this->filters['cash_accounts']);

        if (empty($prefixes) && ! $restrictToConfigured) {
            $prefixes = ChartOfAccount::hasCashBankFlags() ? [] : [ChartOfAccount::CASH_PREFIX, ChartOfAccount::BANK_PREFIX];
        }

        if (empty($prefixes) && $restrictToConfigured) {
            return $this->cashCoaIds = [];
        }

        $useFlags = ! $restrictToConfigured && ChartOfAccount::hasCashBankFlags();

        return $this->cashCoaIds = ChartOfAccount::withTrashed()
            ->where(function (Builder $q) use ($prefixes, $useFlags) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('code', 'like', $prefix . '%');
                }
                if ($useFlags) {
                    $q->orWhere('is_cash_bank', true);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function getConfiguredAccountPrefixes(string $configKey, array $fallbacks = []): array
    {
        $configuredCode = config('coa.' . $configKey);
        $codes = array_filter([$configuredCode, ...$fallbacks]);
        $prefixes = [];

        foreach ($codes as $code) {
            $prefixes[] = (string) $code;
            if (str_contains((string) $code, '.')) {
                $prefixes[] = strstr((string) $code, '.', true);
            }
        }

        return array_values(array_unique(array_filter($prefixes)));
    }

    private function getCashAccountPrefixes(): array
    {
        if ($this->cashAccountPrefixes !== null) {
            return $this->cashAccountPrefixes;
        }

        return $this->cashAccountPrefixes = CashFlowCashAccount::query()
            ->when($this->filters['cash_accounts'] ?? null, function ($query, $ids) {
                $query->whereIn('id', (array) $ids);
            })
            ->pluck('prefix')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
