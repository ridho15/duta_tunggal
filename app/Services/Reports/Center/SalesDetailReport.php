<?php

namespace App\Services\Reports\Center;

use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\InvoiceItem;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\Reports\SalesReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Detail Penjualan: satu baris per produk di faktur penjualan (bukan draft/batal), dengan status pengiriman
 * baris SO dan qty/nilai retur. Tanggal = tanggal faktur. Angka total sama dengan Laporan Penjualan (sebelum nota kredit).
 */
class SalesDetailReport
{
    public function query(array $filters, ?User $user): Builder
    {
        $query = InvoiceItem::query()
            ->select('invoice_items.*')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.from_model_type', SaleOrder::class)
            ->whereNotIn('invoices.status', SalesReportService::EXCLUDED_INVOICE_STATUSES)
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('invoices.invoice_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('invoices.invoice_date', '<=', $d))
            ->when($filters['cabang_id'] ?? null, fn ($q, $id) => $q->where('invoices.cabang_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->whereIn('invoices.from_model_id',
                SaleOrder::withoutGlobalScopes()->where('customer_id', $id)->select('id')))
            ->when(trim((string) ($filters['q'] ?? '')), function ($q, $term) {
                $like = '%' . $term . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('invoices.invoice_number', 'like', $like)
                        ->orWhere('invoices.tax_invoice_number', 'like', $like)
                        ->orWhereIn('invoices.from_model_id', SaleOrder::withoutGlobalScopes()->where('so_number', 'like', $like)->select('id'))
                        ->orWhereIn('invoice_items.product_id', DB::table('products')->where('name', 'like', $like)->orWhere('sku', 'like', $like)->select('id'));
                });
            })
            ->orderByDesc('invoices.invoice_date')->orderByDesc('invoices.id')->orderBy('invoice_items.id');

        if ($user && ! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id) {
            $query->where('invoices.cabang_id', $user->cabang_id);
        }

        return $query;
    }

    /** @return array{invoices: int, qty: float, total: float, returns: float} */
    public function summary(Builder $query): array
    {
        $base = (clone $query)->reorder();
        $agg = (clone $base)->toBase()
            ->select(DB::raw('COUNT(DISTINCT invoice_items.invoice_id) AS invoices, COALESCE(SUM(invoice_items.quantity),0) AS qty, COALESCE(SUM(invoice_items.total),0) AS total'))
            ->first();

        $ids = (clone $base)->select('invoice_items.id');
        $returns = DB::table('customer_return_items as cri')
            ->join('customer_returns as cr', 'cr.id', '=', 'cri.customer_return_id')
            ->join('invoice_items as ii', 'ii.id', '=', 'cri.invoice_item_id')
            ->whereNull('cr.deleted_at')
            ->where('cr.status', '!=', CustomerReturn::STATUS_REJECTED)
            ->whereIn('cri.invoice_item_id', $ids)
            ->selectRaw('COALESCE(SUM(cri.quantity * (CASE WHEN ii.quantity > 0 THEN ii.total / ii.quantity ELSE 0 END)), 0) AS v')
            ->value('v');

        return [
            'invoices' => (int) ($agg->invoices ?? 0),
            'qty' => (float) ($agg->qty ?? 0),
            'total' => round((float) ($agg->total ?? 0), 2),
            'returns' => round((float) $returns, 2),
        ];
    }

    /**
     * Baris siap tampil untuk sekumpulan item (satu halaman / satu potongan export).
     *
     * @param  Collection<int, InvoiceItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }
        $items->load(['product:id,name,sku', 'invoice' => fn ($q) => $q->withoutGlobalScopes(), 'invoice.cabang:id,nama']);
        $soIds = $items->map(fn ($i) => $i->invoice?->from_model_id)->filter()->unique()->values();
        $orders = SaleOrder::withoutGlobalScopes()->with('customer:id,name,code')->whereIn('id', $soIds)->get(['id', 'so_number', 'customer_id'])->keyBy('id');

        // Status kirim per (SO, produk): dipesan vs sudah keluar gudang (DO terkirim)
        $ordered = DB::table('sale_order_items')->whereIn('sale_order_id', $soIds)->whereNull('deleted_at')
            ->groupBy('sale_order_id', 'product_id')->selectRaw('sale_order_id, product_id, SUM(quantity) AS qty')->get()
            ->keyBy(fn ($r) => $r->sale_order_id . ':' . $r->product_id);
        $delivered = DB::table('delivery_order_items as doi')
            ->join('delivery_orders as do', 'do.id', '=', 'doi.delivery_order_id')
            ->join('sale_order_items as soi', 'soi.id', '=', 'doi.sale_order_item_id')
            ->whereIn('soi.sale_order_id', $soIds)->whereIn('do.status', DeliveryOrder::DELIVERED_STATUSES)
            ->whereNull('doi.deleted_at')->whereNull('do.deleted_at')
            ->groupBy('soi.sale_order_id', 'soi.product_id')->selectRaw('soi.sale_order_id, soi.product_id, SUM(doi.quantity) AS qty')->get()
            ->keyBy(fn ($r) => $r->sale_order_id . ':' . $r->product_id);

        $returned = DB::table('customer_return_items as cri')
            ->join('customer_returns as cr', 'cr.id', '=', 'cri.customer_return_id')
            ->whereNull('cr.deleted_at')->where('cr.status', '!=', CustomerReturn::STATUS_REJECTED)
            ->whereIn('cri.invoice_item_id', $items->pluck('id'))
            ->groupBy('cri.invoice_item_id')->selectRaw('cri.invoice_item_id, SUM(cri.quantity) AS qty')->pluck('qty', 'invoice_item_id');

        return $items->map(function (InvoiceItem $item) use ($orders, $ordered, $delivered, $returned) {
            $invoice = $item->invoice;
            $so = $orders->get($invoice?->from_model_id);
            $key = ($invoice?->from_model_id) . ':' . $item->product_id;
            $orderedQty = (float) ($ordered->get($key)->qty ?? 0);
            $deliveredQty = (float) ($delivered->get($key)->qty ?? 0);
            $b = $item->breakdown();
            $qty = (float) $item->quantity;
            $retQty = (float) ($returned[$item->id] ?? 0);

            return [
                'date' => $invoice?->invoice_date,
                'so_number' => $so?->so_number,
                'invoice_number' => $invoice?->invoice_number,
                'tax_invoice_number' => $invoice?->tax_invoice_number,
                'branch' => $invoice?->cabang?->nama ?? '-',
                'customer' => $so?->customer?->name ?? ($invoice?->customer_name ?: '-'),
                'product' => $item->product?->name ?? '-',
                'sku' => $item->product?->sku ?? '-',
                'qty' => $qty,
                'price' => (float) $b['unit_price'],
                'discount_pct' => (float) $b['discount_pct'],
                'total' => (float) $item->total,
                'delivery' => $this->deliveryStatus($orderedQty, $deliveredQty),
                'return_qty' => $retQty,
                'return_total' => $qty > 0 ? round($retQty * (float) $item->total / $qty, 2) : 0.0,
            ];
        })->all();
    }

    /** @return array{key: string, label: string} */
    private function deliveryStatus(float $ordered, float $delivered): array
    {
        return match (true) {
            $ordered <= 0 => ['key' => 'na', 'label' => 'Tanpa SO'],
            $delivered + 0.0001 >= $ordered => ['key' => 'done', 'label' => 'Terkirim semua'],
            $delivered > 0 => ['key' => 'partial', 'label' => 'Terkirim ' . rtrim(rtrim(number_format($delivered, 2, ',', '.'), '0'), ',') . ' dari ' . rtrim(rtrim(number_format($ordered, 2, ',', '.'), '0'), ',')],
            default => ['key' => 'none', 'label' => 'Belum dikirim'],
        };
    }
}
