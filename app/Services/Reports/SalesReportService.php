<?php

namespace App\Services\Reports;

use App\Helpers\MoneyHelper;
use App\Models\AccountReceivable;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\LineAmounts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan penjualan (Fase 6 / Isu 9) dalam tiga mode (keputusan D7):
 *
 *  - invoice  (DEFAULT, akrual) : penjualan yang ditagih — DPP, PPN, total, HPP, margin, status pembayaran; tanggal = invoice_date
 *  - delivery                   : barang yang dikirim — nilai jual & HPP dari pergerakan stok; tanggal = delivery_date
 *  - order                      : pesanan (SO) — tanggal = order_date, status dari SaleOrder::STATUS_LABELS
 *
 * HPP mengambil snapshot per baris invoice (invoice_items.cogs_amount) yang ditulis pada saat jurnal HPP diposting dari
 * perhitungan yang sama dengan jurnal; bila snapshot belum ada, dihitung dari cost_price master dan DITANDAI ESTIMASI.
 * cogsReconciliation() membandingkan total HPP laporan dengan total jurnal HPP pada periode yang sama.
 */
class SalesReportService
{
    public const MODE_INVOICE = 'invoice';

    public const MODE_DELIVERY = 'delivery';

    public const MODE_ORDER = 'order';

    public const DEFAULT_MODE = self::MODE_INVOICE;

    /** Awalan deskripsi jurnal HPP yang diposting InvoiceObserver::postCostOfSalesEntries(). */
    public const COGS_DESCRIPTION_PREFIX = 'Sales Invoice - Cost of Goods Sold for ';

    /** Status pembayaran turunan (dari Account Receivable). */
    public const PAYMENT_STATUS_LABELS = [
        'belum' => 'Belum Bayar',
        'sebagian' => 'Dibayar Sebagian',
        'lunas' => 'Lunas',
        'jatuh_tempo' => 'Jatuh Tempo',
    ];

    /**
     * Invoice yang tidak dihitung sebagai penjualan: draf dan yang DIBATALKAN (T5: Nota Kredit penuh membalik seluruh nilainya,
     * sehingga penjualan bersihnya nol). Penulisan `canceled` dipertahankan untuk data/kode lama.
     */
    public const EXCLUDED_INVOICE_STATUSES = ['draft', 'cancelled', 'canceled'];

    private const TOLERANCE = 0.005;

    /** @var array<int, array<string, mixed>> */
    private array $invoiceRowCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $deliveryRowCache = [];

    /** Ada Nota Kredit terbit di sistem? (satu kueri per instance; tanpa Nota Kredit laporan tidak menambah kueri apa pun.) */
    private ?bool $hasIssuedCreditNotes = null;

    // ───────────────────────────── Mode, status, dan label ─────────────────────────────

    /** @return array<string, string> */
    public static function modeOptions(): array
    {
        return [
            self::MODE_INVOICE => 'Penjualan (Invoice)',
            self::MODE_DELIVERY => 'Pengiriman',
            self::MODE_ORDER => 'Pesanan (SO)',
        ];
    }

    public static function resolveMode(array $filters): string
    {
        $mode = $filters['mode'] ?? null;

        return array_key_exists((string) $mode, self::modeOptions()) ? (string) $mode : self::DEFAULT_MODE;
    }

    /**
     * Opsi filter status per mode — SEMUA dari konstanta model (tidak ada daftar tulis-tangan),
     * sehingga status nyata seperti "Disetujui" / "Dikirim Sebagian" selalu tersedia.
     *
     * @return array<string, string>
     */
    public static function statusOptions(string $mode): array
    {
        return match ($mode) {
            self::MODE_ORDER => SaleOrder::STATUS_LABELS,
            self::MODE_DELIVERY => DeliveryOrder::STATUS_LABELS,
            default => self::PAYMENT_STATUS_LABELS,
        };
    }

    public static function statusFilterLabel(string $mode): string
    {
        return $mode === self::MODE_INVOICE ? 'Status Pembayaran' : 'Status';
    }

    // ───────────────────────────── Query per mode ─────────────────────────────

    public function query(array $filters = [], ?User $user = null): Builder
    {
        return match (self::resolveMode($filters)) {
            self::MODE_ORDER => $this->orderQuery($filters, $user),
            self::MODE_DELIVERY => $this->deliveryQuery($filters, $user),
            default => $this->invoiceQuery($filters, $user),
        };
    }

    public function orderQuery(array $filters = [], ?User $user = null): Builder
    {
        $query = SaleOrder::query()
            // Tanggal mengikuti dokumen (order_date), bukan waktu pencatatan (created_at)
            ->when($filters['start_date'] ?? null, fn ($builder, $startDate) => $builder->whereDate('order_date', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($builder, $endDate) => $builder->whereDate('order_date', '<=', $endDate))
            ->when($filters['customer_id'] ?? null, fn ($builder, $customerId) => $builder->where('customer_id', $customerId))
            ->when($filters['so_number'] ?? null, fn ($builder, $soNumber) => $builder->where('so_number', 'like', '%' . $soNumber . '%'))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->with(['customer', 'saleOrderItem.product']);

        $this->scopeToUserBranch($query, $user);

        return match ($filters['sort_by_total'] ?? null) {
            'asc' => $query->orderBy('total_amount', 'asc'),
            'desc' => $query->orderBy('total_amount', 'desc'),
            default => $query,
        };
    }

    /** Invoice penjualan (bukan draft/batal) pada periode invoice_date. */
    public function invoiceQuery(array $filters = [], ?User $user = null): Builder
    {
        $query = Invoice::query()
            ->where('from_model_type', SaleOrder::class)
            ->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)
            ->when($filters['start_date'] ?? null, fn ($builder, $date) => $builder->whereDate('invoice_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($builder, $date) => $builder->whereDate('invoice_date', '<=', $date))
            ->when($filters['customer_id'] ?? null, function ($builder, $customerId) {
                $builder->whereIn('from_model_id', SaleOrder::withoutGlobalScopes()->where('customer_id', $customerId)->select('id'));
            })
            ->when($filters['so_number'] ?? null, function ($builder, $number) {
                // satu kolom pencarian nomor dokumen: No. Invoice atau No. SO
                $builder->where(function ($q) use ($number) {
                    $q->where('invoice_number', 'like', '%' . $number . '%')
                        ->orWhereIn('from_model_id', SaleOrder::withoutGlobalScopes()->where('so_number', 'like', '%' . $number . '%')->select('id'));
                });
            })
            ->when($filters['status'] ?? null, fn ($builder, $status) => $this->applyPaymentStatusFilter($builder, (string) $status))
            ->with(['invoiceItem.product', 'accountReceivable', 'fromModel.customer', 'cabang']);

        $this->scopeToUserBranch($query, $user);

        return match ($filters['sort_by_total'] ?? null) {
            'asc' => $query->orderBy('total', 'asc'),
            'desc' => $query->orderBy('total', 'desc'),
            default => $query->orderByDesc('invoice_date')->orderByDesc('id'),
        };
    }

    /** DO pada periode delivery_date; tanpa filter status hanya yang sudah keluar gudang (sent/received/completed). */
    public function deliveryQuery(array $filters = [], ?User $user = null): Builder
    {
        $query = DeliveryOrder::query()
            ->when($filters['start_date'] ?? null, fn ($builder, $date) => $builder->whereDate('delivery_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($builder, $date) => $builder->whereDate('delivery_date', '<=', $date))
            ->when($filters['customer_id'] ?? null, function ($builder, $customerId) {
                $builder->whereHas('salesOrders', fn ($q) => $q->withoutGlobalScopes()->where('customer_id', $customerId));
            })
            ->when($filters['so_number'] ?? null, function ($builder, $number) {
                $builder->where(function ($q) use ($number) {
                    $q->where('do_number', 'like', '%' . $number . '%')
                        ->orWhereHas('salesOrders', fn ($so) => $so->withoutGlobalScopes()->where('so_number', 'like', '%' . $number . '%'));
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn ($builder, $status) => $builder->where('status', $status),
                fn ($builder) => $builder->whereIn('status', DeliveryOrder::DELIVERED_STATUSES)
            )
            ->with(['salesOrders.customer', 'deliveryOrderItem.product', 'deliveryOrderItem.saleOrderItem', 'cabang']);

        $this->scopeToUserBranch($query, $user);

        return $query->orderByDesc('delivery_date')->orderByDesc('id');
    }

    private function scopeToUserBranch(Builder $query, ?User $user): void
    {
        if ($user && ! in_array('all', $user->manage_type ?? [], true)) {
            $query->where($query->getModel()->getTable() . '.cabang_id', $user->cabang_id);
        }
    }

    private function applyPaymentStatusFilter(Builder $builder, string $status): void
    {
        $unsettled = fn ($ar) => $ar->where('account_receivables.remaining', '>', self::TOLERANCE);

        match ($status) {
            'lunas' => $builder->where(function ($q) {
                // lunas: AR lunas, atau (tanpa AR) status invoice paid
                $q->whereHas('accountReceivable', fn ($ar) => $ar->where('account_receivables.remaining', '<=', self::TOLERANCE))
                    ->orWhere(fn ($noAr) => $noAr->whereDoesntHave('accountReceivable')->where('status', Invoice::STATUS_PAID));
            }),
            'sebagian' => $builder->whereHas('accountReceivable', fn ($ar) => $unsettled($ar)->where('account_receivables.paid', '>', self::TOLERANCE))
                ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', now()->toDateString())),
            'jatuh_tempo' => $builder->whereHas('accountReceivable', $unsettled)
                ->whereDate('due_date', '<', now()->toDateString()),
            'belum' => $builder->whereHas('accountReceivable', fn ($ar) => $unsettled($ar)->where('account_receivables.paid', '<=', self::TOLERANCE))
                ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', now()->toDateString())),
            default => null,
        };
    }

    // ───────────────────────────── Mode Invoice ─────────────────────────────

    /**
     * Baris laporan satu invoice (agregat) beserta rincian baris-barisnya.
     *
     * @return array<string, mixed>
     */
    public function invoiceRow(Invoice $invoice): array
    {
        if (isset($this->invoiceRowCache[$invoice->id])) {
            return $this->invoiceRowCache[$invoice->id];
        }

        $invoice->loadMissing(['invoiceItem.product', 'accountReceivable', 'fromModel.customer', 'cabang']);
        $lines = $this->invoiceLines($invoice);
        $creditTotal = $this->creditNoteTotal($invoice);   // T5: penjualan BERSIH setelah Nota Kredit terbit

        $dpp = $lines->isNotEmpty() ? (float) $lines->sum('dpp') : (float) ($invoice->subtotal ?? $invoice->dpp ?? 0);
        $ppn = $lines->isNotEmpty() ? (float) $lines->sum('ppn') : (float) $invoice->ppn_amount;
        $hpp = (float) $lines->sum('hpp');
        $margin = round($dpp - $hpp, 2);
        $customer = $invoice->fromModel?->customer;
        $deliveryOrderNumbers = $this->deliveryOrderNumbers($invoice);
        $ar = $invoice->accountReceivable?->getKey() ? $invoice->accountReceivable : null;
        $currency = $invoice->display_currency;

        return $this->invoiceRowCache[$invoice->id] = [
            'invoice_id' => $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'customer_code' => $customer?->code ?? '-',
            'customer_name' => $customer?->name ?? ($invoice->customer_name ?: '-'),
            'so_number' => $invoice->fromModel?->so_number ?? '-',
            'do_numbers' => $deliveryOrderNumbers,
            'dpp' => $dpp,
            'ppn' => $ppn,
            'total' => round((float) $invoice->total - $creditTotal, 2),
            'credit_note_total' => $creditTotal,
            'other_fees' => round((float) $invoice->total - $creditTotal - (float) $lines->sum('total'), 2),
            'hpp' => $hpp,
            'hpp_estimated' => $lines->contains(fn ($line) => $line['hpp_source'] === InvoiceItem::COGS_SOURCE_ESTIMATE),
            'margin' => $margin,
            'margin_pct' => $dpp > 0 ? round($margin / $dpp * 100, 2) : 0.0,
            'payment_status' => $this->paymentStatus($invoice),
            'remaining' => $ar ? (float) $ar->remaining : null,
            'due_date' => $invoice->due_date,
            'branch' => $invoice->cabang->nama ?? '-',
            'currency' => $currency?->code ?? 'IDR',
            'exchange_rate' => (float) ($invoice->exchange_rate ?? 1),
            'lines' => $lines,
        ];
    }

    /**
     * Rincian baris invoice: harga gross, diskon, DPP, PPN, total, HPP (snapshot atau estimasi), margin.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function invoiceLines(Invoice $invoice): Collection
    {
        $invoice->loadMissing('invoiceItem.product');

        $adjustments = $this->creditAdjustments($invoice);

        return $invoice->invoiceItem->map(function (InvoiceItem $item) use ($invoice, $adjustments) {
            $item->setRelation('invoice', $invoice);
            $b = $item->breakdown();
            $credit = $adjustments[$item->id] ?? null;

            if ($item->cogs_amount !== null) {
                $hpp = (float) $item->cogs_amount;
                $source = $item->cogs_source ?: InvoiceItem::COGS_SOURCE_JOURNAL;
            } else {
                $hpp = round((float) $item->quantity * (float) ($item->product?->cost_price ?? 0), 2);
                $source = InvoiceItem::COGS_SOURCE_ESTIMATE;
            }

            // T5: Nota Kredit terbit mengurangi DPP/PPN/total baris; HPP hanya berkurang untuk retur FISIK (barang kembali ke stok)
            if ($credit) {
                $b['dpp'] = round($b['dpp'] - $credit['subtotal'], 2);
                $b['ppn'] = round($b['ppn'] - $credit['tax'], 2);
                $b['total'] = round($b['total'] - $credit['subtotal'] - $credit['tax'], 2);
                if ($credit['returned_qty'] > 0 && (float) $b['quantity'] > 0) {
                    $hpp = round($hpp * max(0.0, ((float) $b['quantity'] - $credit['returned_qty']) / (float) $b['quantity']), 2);
                }
                $b['quantity'] = round((float) $b['quantity'] - $credit['quantity'], 2);
            }

            return [
                'product_sku' => $item->product?->sku ?? '-',
                'product_name' => $item->product?->name ?? '-',
                'quantity' => $b['quantity'],
                'unit_price' => $b['unit_price'],
                'gross' => $b['gross'],
                'discount_pct' => $b['discount_pct'],
                'discount_amount' => $b['discount_amount'],
                'dpp' => $b['dpp'],
                'ppn' => $b['ppn'],
                'total' => $b['total'],
                'hpp' => $hpp,
                'hpp_source' => $source,
                'margin' => round($b['dpp'] - $hpp, 2),
                'margin_pct' => $b['dpp'] > 0 ? round(($b['dpp'] - $hpp) / $b['dpp'] * 100, 2) : 0.0,
            ];
        })->values();
    }

    /** Σ Nota Kredit TERBIT atas invoice ini (total termasuk biaya pengiriman). */
    private function creditNoteTotal(Invoice $invoice): float
    {
        if (! $this->creditNotesExist()) {
            return 0.0;
        }

        return round((float) \App\Models\CreditNote::query()->where('invoice_id', $invoice->id)->where('status', \App\Models\CreditNote::STATUS_ISSUED)->sum('total'), 2);
    }

    /**
     * Nota Kredit terbit per baris invoice: DPP, PPN, kuantitas, dan kuantitas RETUR FISIK (tipe retur) untuk pengurang HPP.
     *
     * @return array<int, array{subtotal: float, tax: float, quantity: float, returned_qty: float}>
     */
    private function creditAdjustments(Invoice $invoice): array
    {
        if (! $this->creditNotesExist()) {
            return [];
        }

        return \App\Models\CreditNoteItem::query()
            ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
            ->whereNull('credit_notes.deleted_at')
            ->where('credit_notes.invoice_id', $invoice->id)
            ->where('credit_notes.status', \App\Models\CreditNote::STATUS_ISSUED)
            ->whereNotNull('credit_note_items.invoice_item_id')
            ->selectRaw("credit_note_items.invoice_item_id as item_id, SUM(credit_note_items.subtotal) as subtotal, SUM(credit_note_items.tax_amount) as tax, SUM(credit_note_items.quantity) as quantity, SUM(CASE WHEN credit_notes.type = 'retur' THEN credit_note_items.quantity ELSE 0 END) as returned_qty")
            ->groupBy('credit_note_items.invoice_item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id => [
                'subtotal' => (float) $row->subtotal, 'tax' => (float) $row->tax, 'quantity' => (float) $row->quantity, 'returned_qty' => (float) $row->returned_qty,
            ]])
            ->all();
    }

    private function creditNotesExist(): bool
    {
        return $this->hasIssuedCreditNotes ??= \Illuminate\Support\Facades\Schema::hasTable('credit_notes')
            && \App\Models\CreditNote::query()->where('status', \App\Models\CreditNote::STATUS_ISSUED)->exists();
    }

    /** belum | sebagian | lunas | jatuh_tempo — dari Account Receivable (fallback: status invoice). */
    public function paymentStatus(Invoice $invoice): string
    {
        $invoice->loadMissing('accountReceivable');
        $ar = $invoice->accountReceivable;

        if (! $ar?->getKey()) {
            return strtolower((string) $invoice->status) === Invoice::STATUS_PAID ? 'lunas' : 'belum';
        }

        $remaining = (float) $ar->remaining;
        if ($remaining <= self::TOLERANCE) {
            return 'lunas';
        }

        if ($invoice->due_date && Carbon::parse($invoice->due_date)->startOfDay()->lt(now()->startOfDay())) {
            return 'jatuh_tempo';
        }

        return (float) $ar->paid > self::TOLERANCE ? 'sebagian' : 'belum';
    }

    /** @return array<int, string> */
    private function deliveryOrderNumbers(Invoice $invoice): array
    {
        $ids = array_filter((array) $invoice->delivery_orders);

        if ($ids === []) {
            return [];
        }

        return DeliveryOrder::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('do_number')->pluck('do_number')->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function invoiceSummary(Collection $rows): array
    {
        $dpp = (float) $rows->sum('dpp');
        $hpp = (float) $rows->sum('hpp');
        $margin = round($dpp - $hpp, 2);

        return [
            'total_invoices' => $rows->count(),
            'total_dpp' => $dpp,
            'total_ppn' => (float) $rows->sum('ppn'),
            'total_amount' => (float) $rows->sum('total'),
            'total_hpp' => $hpp,
            'total_margin' => $margin,
            'margin_pct' => $dpp > 0 ? round($margin / $dpp * 100, 2) : 0.0,
            'estimated_invoices' => $rows->where('hpp_estimated', true)->count(),
            'outstanding' => (float) $rows->sum(fn ($row) => (float) ($row['remaining'] ?? 0)),
            'payment_counts' => collect(self::PAYMENT_STATUS_LABELS)
                ->mapWithKeys(fn ($label, $key) => [$key => $rows->where('payment_status', $key)->count()])
                ->all(),
            // Konversi ke IDR mengikuti nilai tersimpan pada invoice (sistem menyimpan invoice dalam IDR);
            // rincian per mata uang dokumen untuk multi-currency.
            'per_currency' => $rows->groupBy('currency')
                ->map(fn (Collection $group) => [
                    'invoices' => $group->count(),
                    'total_idr' => (float) $group->sum('total'),
                    'total_original' => (float) $group->sum(fn ($row) => $row['exchange_rate'] > 0 ? $row['total'] / $row['exchange_rate'] : $row['total']),
                ])->all(),
        ];
    }

    /**
     * Rekonsiliasi HPP: Σ HPP laporan (snapshot) vs Σ jurnal HPP pada periode yang sama.
     * Hanya membandingkan invoice yang sudah punya jurnal HPP; baris estimasi/tanpa snapshot dihitung terpisah.
     *
     * @return array{journal: float, report: float, difference: float, reconciled: bool, estimated_lines: int, invoices: int}
     */
    public function cogsReconciliation(?string $startDate = null, ?string $endDate = null, ?User $user = null): array
    {
        $journal = JournalEntry::query()
            ->where('source_type', Invoice::class)
            ->where('description', 'like', self::COGS_DESCRIPTION_PREFIX . '%')
            ->when($startDate, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($endDate, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($user && ! in_array('all', $user->manage_type ?? [], true), fn ($q) => $q->where('cabang_id', $user->cabang_id));

        $journalTotal = round((float) (clone $journal)->sum('debit'), 2);
        $invoiceIds = (clone $journal)->distinct()->pluck('source_id');

        $items = InvoiceItem::query()->with('product')->whereIn('invoice_id', $invoiceIds)->get();
        $reportTotal = round((float) $items->sum(fn (InvoiceItem $item) => $item->cogs_amount !== null
            ? (float) $item->cogs_amount
            : round((float) $item->quantity * (float) ($item->product?->cost_price ?? 0), 2)), 2);

        $difference = round($reportTotal - $journalTotal, 2);

        return [
            'journal' => $journalTotal,
            'report' => $reportTotal,
            'difference' => $difference,
            'reconciled' => abs($difference) <= self::TOLERANCE,
            'estimated_lines' => $items->whereNull('cogs_amount')->count(),
            'invoices' => $invoiceIds->count(),
        ];
    }

    // ───────────────────────────── Mode Pengiriman ─────────────────────────────

    /** @return array<string, mixed> */
    public function deliveryRow(DeliveryOrder $deliveryOrder): array
    {
        if (isset($this->deliveryRowCache[$deliveryOrder->id])) {
            return $this->deliveryRowCache[$deliveryOrder->id];
        }

        $deliveryOrder->loadMissing(['salesOrders.customer', 'deliveryOrderItem.product', 'deliveryOrderItem.saleOrderItem', 'cabang']);

        $itemIds = $deliveryOrder->deliveryOrderItem->pluck('id');
        $hppByItem = StockMovement::query()
            ->where('from_model_type', \App\Models\DeliveryOrderItem::class)
            ->whereIn('from_model_id', $itemIds)
            ->selectRaw('from_model_id, SUM(ABS(value)) as total_value')
            ->groupBy('from_model_id')
            ->pluck('total_value', 'from_model_id');

        $lines = $deliveryOrder->deliveryOrderItem
            ->filter(fn ($item) => (float) $item->quantity > 0)
            ->values()
            ->map(function ($item) use ($hppByItem) {
                $soItem = $item->saleOrderItem;
                $amounts = LineAmounts::calculate(
                    $item->quantity,
                    $soItem?->unit_price ?? ($item->product?->sell_price ?? 0),
                    $soItem?->discount ?? 0,
                    $soItem?->tax ?? 0,
                    $soItem?->tipe_pajak
                );
                $hpp = round((float) ($hppByItem[$item->id] ?? 0), 2);

                return [
                    'product_sku' => $item->product?->sku ?? '-',
                    'product_name' => $item->product?->name ?? '-',
                    'quantity' => (float) $item->quantity,
                    'dpp' => $amounts['dpp'],
                    'ppn' => $amounts['ppn'],
                    'total' => $amounts['total'],
                    'hpp' => $hpp,
                    'margin' => round($amounts['dpp'] - $hpp, 2),
                ];
            });

        $dpp = (float) $lines->sum('dpp');
        $hpp = (float) $lines->sum('hpp');

        $invoiceNumbers = Invoice::withoutGlobalScopes()
            ->where('from_model_type', SaleOrder::class)
            ->whereJsonContains('delivery_orders', $deliveryOrder->id)
            ->pluck('invoice_number')
            ->all();

        return $this->deliveryRowCache[$deliveryOrder->id] = [
            'do_number' => (string) $deliveryOrder->do_number,
            'delivery_date' => $deliveryOrder->delivery_date,
            'customer_name' => $deliveryOrder->salesOrders->map(fn ($so) => $so->customer?->name)->filter()->unique()->implode(', ') ?: '-',
            'so_numbers' => $deliveryOrder->salesOrders->pluck('so_number')->filter()->implode(', ') ?: '-',
            'invoice_numbers' => $invoiceNumbers,
            'status' => $deliveryOrder->status,
            'status_label' => DeliveryOrder::statusLabel($deliveryOrder->status),
            'quantity' => (float) $lines->sum('quantity'),
            'dpp' => $dpp,
            'ppn' => (float) $lines->sum('ppn'),
            'total' => (float) $lines->sum('total'),
            'hpp' => $hpp,
            'margin' => round($dpp - $hpp, 2),
            'margin_pct' => $dpp > 0 ? round(($dpp - $hpp) / $dpp * 100, 2) : 0.0,
            'branch' => $deliveryOrder->cabang->nama ?? '-',
            'lines' => $lines,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function deliverySummary(Collection $rows): array
    {
        $dpp = (float) $rows->sum('dpp');
        $hpp = (float) $rows->sum('hpp');

        return [
            'total_deliveries' => $rows->count(),
            'total_quantity' => (float) $rows->sum('quantity'),
            'total_dpp' => $dpp,
            'total_ppn' => (float) $rows->sum('ppn'),
            'total_amount' => (float) $rows->sum('total'),
            'total_hpp' => $hpp,
            'total_margin' => round($dpp - $hpp, 2),
            'margin_pct' => $dpp > 0 ? round(($dpp - $hpp) / $dpp * 100, 2) : 0.0,
        ];
    }

    // ───────────────────────────── Ringkasan & ekspor ─────────────────────────────

    public function exportCollectionFromQuery(Builder $query): Collection
    {
        return match (true) {
            $query->getModel() instanceof Invoice => $this->invoiceExportCollection($query->get()),
            $query->getModel() instanceof DeliveryOrder => $this->deliveryExportCollection($query->get()),
            default => $this->exportCollectionFromOrders($query->get()),
        };
    }

    /** @return array<int, string> */
    public function exportHeadings(string $mode): array
    {
        return match ($mode) {
            self::MODE_INVOICE => [
                'No. Invoice', 'Tanggal', 'Kode Customer', 'Nama Customer', 'No. SO', 'No. DO', 'Produk', 'Qty', 'Harga Satuan',
                'Diskon (%)', 'Diskon (Rp)', 'DPP', 'PPN', 'Total', 'HPP', 'Basis HPP', 'Margin (Rp)', 'Margin (%)',
                'Status Pembayaran', 'Cabang', 'Mata Uang',
            ],
            self::MODE_DELIVERY => [
                'No. DO', 'Tanggal Kirim', 'Nama Customer', 'No. SO', 'No. Invoice', 'Status', 'Produk', 'Qty', 'DPP', 'PPN', 'Total',
                'HPP (Stok)', 'Margin (Rp)', 'Margin (%)', 'Cabang',
            ],
            default => [
                'No. SO', 'Tanggal', 'Kode Customer', 'Nama Customer', 'Alamat Customer', 'No. Telp', 'Email', 'Produk', 'Qty',
                'Harga Satuan', 'Discount (%)', 'Tax Rate (%)', 'Tipe Pajak', 'DPP', 'PPN Amount', 'Item Subtotal', 'Subtotal',
                'Total SO', 'Status',
            ],
        };
    }

    /** Satu baris per baris invoice (datar, mudah difilter/pivot di Excel) + baris total. */
    private function invoiceExportCollection(Collection $invoices): Collection
    {
        $data = collect();
        $rows = collect();

        foreach ($invoices as $invoice) {
            $row = $this->invoiceRow($invoice);
            $rows->push($row);

            $lines = $row['lines']->isNotEmpty() ? $row['lines'] : collect([[
                'product_name' => '(tanpa baris)', 'quantity' => 0, 'unit_price' => 0, 'discount_pct' => 0, 'discount_amount' => 0,
                'dpp' => $row['dpp'], 'ppn' => $row['ppn'], 'total' => $row['total'], 'hpp' => $row['hpp'], 'hpp_source' => '-',
                'margin' => $row['margin'], 'margin_pct' => $row['margin_pct'],
            ]]);

            foreach ($lines as $line) {
                $data->push([
                    $row['invoice_number'], $row['invoice_date'] ? Carbon::parse($row['invoice_date'])->format('d/m/Y') : '',
                    $row['customer_code'], $row['customer_name'], $row['so_number'], implode(', ', $row['do_numbers']),
                    $line['product_name'], $line['quantity'], $line['unit_price'], $line['discount_pct'], $line['discount_amount'],
                    $line['dpp'], $line['ppn'], $line['total'], $line['hpp'], $line['hpp_source'] === InvoiceItem::COGS_SOURCE_ESTIMATE ? 'Estimasi' : 'Snapshot jurnal',
                    $line['margin'], $line['margin_pct'], self::PAYMENT_STATUS_LABELS[$row['payment_status']] ?? $row['payment_status'],
                    $row['branch'], $row['currency'],
                ]);
            }
        }

        $summary = $this->invoiceSummary($rows);
        $data->push(['TOTAL', '', '', '', '', '', '', '', '', '', '', $summary['total_dpp'], $summary['total_ppn'], $summary['total_amount'],
            $summary['total_hpp'], '', $summary['total_margin'], $summary['margin_pct'], '', '', '']);

        return $data;
    }

    private function deliveryExportCollection(Collection $deliveries): Collection
    {
        $data = collect();
        $rows = collect();

        foreach ($deliveries as $delivery) {
            $row = $this->deliveryRow($delivery);
            $rows->push($row);

            foreach ($row['lines'] as $line) {
                $data->push([
                    $row['do_number'], $row['delivery_date'] ? Carbon::parse($row['delivery_date'])->format('d/m/Y') : '',
                    $row['customer_name'], $row['so_numbers'], implode(', ', $row['invoice_numbers']), $row['status_label'],
                    $line['product_name'], $line['quantity'], $line['dpp'], $line['ppn'], $line['total'], $line['hpp'], $line['margin'],
                    $line['dpp'] > 0 ? round($line['margin'] / $line['dpp'] * 100, 2) : 0.0, $row['branch'],
                ]);
            }
        }

        $summary = $this->deliverySummary($rows);
        $data->push(['TOTAL', '', '', '', '', '', '', $summary['total_quantity'], $summary['total_dpp'], $summary['total_ppn'],
            $summary['total_amount'], $summary['total_hpp'], $summary['total_margin'], $summary['margin_pct'], '']);

        return $data;
    }

    /**
     * Payload PDF. Mode invoice/delivery: kolom + baris + ringkasan siap cetak; mode order: bentuk lama (so_number, dst.).
     */
    public function pdfPayload(array $filters = [], ?User $user = null): array
    {
        $mode = self::resolveMode($filters);

        if ($mode === self::MODE_ORDER) {
            $orders = $this->orders($filters, $user);

            return [
                'mode' => $mode,
                'rows' => $this->pdfRowsFromOrders($orders),
                'summary' => $this->summary($orders),
            ];
        }

        if ($mode === self::MODE_DELIVERY) {
            $rows = $this->deliveryQuery($filters, $user)->get()->map(fn ($do) => $this->deliveryRow($do));

            return [
                'mode' => $mode,
                'title' => 'Laporan Pengiriman',
                'columns' => ['No. DO', 'Tanggal Kirim', 'Customer', 'No. SO', 'Status', 'Qty', 'DPP', 'HPP (Stok)', 'Margin', 'Margin %'],
                'rows' => $rows->map(fn ($r) => [
                    $r['do_number'], $r['delivery_date'] ? Carbon::parse($r['delivery_date'])->format('d/m/Y') : '-', $r['customer_name'], $r['so_numbers'],
                    $r['status_label'], $this->number($r['quantity']), MoneyHelper::rupiah($r['dpp']), MoneyHelper::rupiah($r['hpp']),
                    MoneyHelper::rupiah($r['margin']), number_format($r['margin_pct'], 2, ',', '.') . '%',
                ])->all(),
                'summary' => $summary = $this->deliverySummary($rows),
                'summary_lines' => [
                    'Jumlah Pengiriman' => (string) $summary['total_deliveries'],
                    'Total Qty' => $this->number($summary['total_quantity']),
                    'Total DPP' => MoneyHelper::rupiah($summary['total_dpp']),
                    'Total HPP (Stok)' => MoneyHelper::rupiah($summary['total_hpp']),
                    'Margin' => MoneyHelper::rupiah($summary['total_margin']) . ' (' . number_format($summary['margin_pct'], 2, ',', '.') . '%)',
                ],
            ];
        }

        $rows = $this->invoiceQuery($filters, $user)->get()->map(fn ($invoice) => $this->invoiceRow($invoice));
        $summary = $this->invoiceSummary($rows);

        return [
            'mode' => $mode,
            'title' => 'Laporan Penjualan (Invoice)',
            'columns' => ['No. Invoice', 'Tanggal', 'Customer', 'No. SO', 'DPP', 'PPN', 'Total', 'HPP', 'Margin', 'Margin %', 'Pembayaran'],
            'rows' => $rows->map(fn ($r) => [
                $r['invoice_number'], $r['invoice_date'] ? Carbon::parse($r['invoice_date'])->format('d/m/Y') : '-', $r['customer_name'], $r['so_number'],
                MoneyHelper::rupiah($r['dpp']), MoneyHelper::rupiah($r['ppn']), MoneyHelper::rupiah($r['total']),
                MoneyHelper::rupiah($r['hpp']) . ($r['hpp_estimated'] ? ' *' : ''), MoneyHelper::rupiah($r['margin']),
                number_format($r['margin_pct'], 2, ',', '.') . '%', self::PAYMENT_STATUS_LABELS[$r['payment_status']] ?? $r['payment_status'],
            ])->all(),
            'summary' => $summary,
            'summary_lines' => [
                'Jumlah Invoice' => (string) $summary['total_invoices'],
                'Total DPP' => MoneyHelper::rupiah($summary['total_dpp']),
                'Total PPN' => MoneyHelper::rupiah($summary['total_ppn']),
                'Total Penjualan' => MoneyHelper::rupiah($summary['total_amount']),
                'Total HPP' => MoneyHelper::rupiah($summary['total_hpp']),
                'Margin' => MoneyHelper::rupiah($summary['total_margin']) . ' (' . number_format($summary['margin_pct'], 2, ',', '.') . '%)',
                'Piutang Belum Dibayar' => MoneyHelper::rupiah($summary['outstanding']),
                'Rekonsiliasi HPP dengan Jurnal' => $this->reconciliationLabel($filters, $user),
            ],
            'footnote' => $summary['estimated_invoices'] > 0
                ? '* HPP ditandai estimasi (dari cost_price master saat ini) karena invoice belum memiliki snapshot HPP dari jurnal.'
                : null,
        ];
    }

    /**
     * Status rekonsiliasi HPP laporan vs jurnal HPP pada periode. Hanya bermakna tanpa filter customer/nomor/status
     * (jurnal HPP mencakup seluruh invoice periode).
     */
    public function reconciliationLabel(array $filters, ?User $user = null): string
    {
        if (filled($filters['customer_id'] ?? null) || filled($filters['so_number'] ?? null) || filled($filters['status'] ?? null)) {
            return 'Tidak dihitung (filter customer/nomor/status aktif)';
        }

        $r = $this->cogsReconciliation($filters['start_date'] ?? null, $filters['end_date'] ?? null, $user);

        if ($r['invoices'] === 0) {
            return 'Belum ada jurnal HPP pada periode';
        }

        return $r['reconciled']
            ? 'Sesuai (' . MoneyHelper::rupiah($r['journal']) . ')'
            : 'SELISIH ' . MoneyHelper::rupiah($r['difference']) . ' (laporan ' . MoneyHelper::rupiah($r['report']) . ' vs jurnal ' . MoneyHelper::rupiah($r['journal']) . ')';
    }

    public function pdfRows(array $filters = [], ?User $user = null): Collection
    {
        return $this->pdfRowsFromOrders($this->orders($filters, $user));
    }

    /**
     * Ringkasan mode PESANAN (SO). Status dihitung dari status nyata sistem; kunci lama
     * ('cancelled') dipertahankan sebagai alias dari 'canceled' agar konsumen lama tidak rusak.
     */
    public function summary(Collection $orders): array
    {
        $totalOrders = $orders->count();
        $totalAmount = (float) $orders->sum('total_amount');

        $statusCounts = collect(SaleOrder::STATUS_LABELS)
            ->mapWithKeys(fn ($label, $status) => [$status => $orders->where('status', $status)->count()])
            ->all();
        $statusCounts['cancelled'] = $statusCounts['canceled'] ?? 0;

        $totalQuantity = (float) $orders->sum(fn ($order) => $order->saleOrderItem->sum('quantity'));
        $uniqueProducts = $orders->flatMap(fn ($order) => $order->saleOrderItem->pluck('product_id'))->unique()->count();
        $totalDpp = 0.0;

        $orders->each(function ($order) use (&$totalDpp): void {
            $totalDpp += $order->saleOrderItem->sum(fn ($item) => LineAmounts::calculate(
                $item->quantity, $item->unit_price, $item->discount, $item->tax, $item->tipe_pajak ?? 'Exclusive'
            )['dpp']);
        });

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'average_amount' => $totalOrders > 0 ? $totalAmount / $totalOrders : 0.0,
            'total_quantity' => $totalQuantity,
            'unique_products' => $uniqueProducts,
            'total_dpp' => $totalDpp,
            'status_counts' => $statusCounts,
        ];
    }

    private function orders(array $filters = [], ?User $user = null): Collection
    {
        return $this->orderQuery($filters, $user)->get();
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function exportCollectionFromOrders(Collection $orders): Collection
    {
        $data = collect();
        $summary = $this->summary($orders);
        $blank = fn (array $overrides = []) => array_merge(array_fill_keys($this->exportHeadings(self::MODE_ORDER), ''), $overrides);

        foreach ($orders as $order) {
            $data->push($blank([
                'No. SO' => $order->so_number,
                'Tanggal' => $order->order_date ? Carbon::parse($order->order_date)->format('d/m/Y') : '',
                'Kode Customer' => $order->customer->code ?? '-',
                'Nama Customer' => $order->customer->name ?? '-',
                'Alamat Customer' => $order->customer->address ?? '-',
                'No. Telp' => $order->customer->phone ?? '-',
                'Email' => $order->customer->email ?? '-',
                'Total SO' => MoneyHelper::rupiah($order->total_amount ?? 0),
                'Status' => SaleOrder::statusLabel($order->status),
            ]));

            foreach ($order->saleOrderItem as $item) {
                if (($item->unit_price ?? 0) <= 0 || ($item->quantity ?? 0) <= 0) {
                    continue;
                }

                $discountPct = $item->discount ?? 0;
                $taxRate = $item->tax ?? 0;
                $taxResult = LineAmounts::calculate($item->quantity, $item->unit_price, $discountPct, $taxRate, $item->tipe_pajak ?? 'Exclusive');

                $data->push($blank([
                    'Produk' => $item->product->name ?? '-',
                    'Qty' => $item->quantity ?? 0,
                    'Harga Satuan' => MoneyHelper::rupiah($item->unit_price ?? 0),
                    'Discount (%)' => number_format($discountPct, 2),
                    'Tax Rate (%)' => number_format($taxRate, 2),
                    'Tipe Pajak' => \App\Services\TaxService::normalizeType($item->tipe_pajak),
                    'DPP' => MoneyHelper::rupiah($taxResult['dpp']),
                    'PPN Amount' => MoneyHelper::rupiah($taxResult['ppn']),
                    'Item Subtotal' => MoneyHelper::rupiah($taxResult['total']),
                ]));
            }

            $data->push($blank());
        }

        $data->push($blank([
            'No. SO' => 'SUMMARY',
            'DPP' => 'Total DPP: ' . MoneyHelper::rupiah($summary['total_dpp']),
            'Total SO' => 'Total: ' . MoneyHelper::rupiah($summary['total_amount']),
        ]));

        // Ringkasan status dari status nyata (bukan lima kunci tulis-tangan)
        $statusLine = collect(SaleOrder::STATUS_LABELS)
            ->map(fn ($label, $status) => $label . ': ' . ($summary['status_counts'][$status] ?? 0))
            ->filter(fn ($text, $status) => ($summary['status_counts'][$status] ?? 0) > 0)
            ->implode(' | ');

        $data->push($blank(['Produk' => 'Total Orders: ' . $summary['total_orders'], 'Qty' => $statusLine !== '' ? $statusLine : '-']));
        $data->push($blank([
            'Produk' => 'Total Qty: ' . $summary['total_quantity'],
            'Qty' => 'Avg Transaction: ' . MoneyHelper::rupiah($summary['average_amount']),
            'Harga Satuan' => 'Unique Products: ' . $summary['unique_products'],
        ]));

        return $data;
    }

    private function pdfRowsFromOrders(Collection $orders): Collection
    {
        return $orders->map(function ($order) {
            return [
                'so_number' => mb_convert_encoding($order->so_number ?? '', 'UTF-8', 'UTF-8'),
                'created_at' => $order->order_date ?? $order->created_at,
                'customer_code' => mb_convert_encoding($order->customer->code ?? '-', 'UTF-8', 'UTF-8'),
                'customer_name' => mb_convert_encoding($order->customer->name ?? '-', 'UTF-8', 'UTF-8'),
                'total_amount' => $order->total_amount ?? 0,
                'status' => mb_convert_encoding(SaleOrder::statusLabel($order->status), 'UTF-8', 'UTF-8'),
                'status_key' => (string) $order->status,
            ];
        });
    }
}
