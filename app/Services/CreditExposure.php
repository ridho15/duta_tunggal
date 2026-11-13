<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paparan kredit customer (T3.2, D27): satu tempat menghitung
 *   paparan = piutang berjalan (AR belum lunas) + nilai SO Approved/Dikirim Sebagian yang BELUM ditagih.
 * SO yang sedang disetujui belum berstatus aktif, jadi nilainya ditambahkan pemanggil sebagai "order baru".
 */
class CreditExposure
{
    /** Status SO yang nilainya sudah menjadi komitmen tetapi belum (seluruhnya) menjadi piutang. */
    public const OPEN_SO_STATUSES = ['approved', 'confirmed', 'partial_confirmed', 'partially_delivered'];

    /** @return float piutang berjalan (Σ sisa AR aktif belum lunas/partial) */
    public function receivables(Customer $customer): float
    {
        return (float) (AccountReceivable::where('customer_id', $customer->id)
            ->where('remaining', '>', 0)
            ->sum('remaining') ?? 0);
    }

    /**
     * SO aktif yang belum ditagih: Σ max(0, total SO − Σ invoice terbit (selain yang dibatalkan)).
     * Mendukung invoice yang terbit langsung dari SaleOrder maupun via Surat Jalan.
     *
     * @return array{total: float, count: int}
     */
    public function openSalesOrders(Customer $customer): array
    {
        $orders = SaleOrder::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::OPEN_SO_STATUSES)
            ->get(['id', 'total_amount', 'shipping_cost']);

        if ($orders->isEmpty()) {
            return ['total' => 0.0, 'count' => 0];
        }

        $orderIds = $orders->pluck('id')->all();

        // 1. Invoice langsung dari SaleOrder
        $directInvoiced = Invoice::withoutGlobalScopes()
            ->where('from_model_type', SaleOrder::class)
            ->whereIn('from_model_id', $orderIds)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->selectRaw('from_model_id, SUM(total) as total')
            ->groupBy('from_model_id')
            ->pluck('total', 'from_model_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // 2. Invoice via Surat Jalan yang diturunkan dari DO milik SO-SO ini
        $sjInvoiced = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('delivery_sales_orders') && \Illuminate\Support\Facades\Schema::hasTable('surat_jalan_delivery_orders')) {
            $soToSjRows = DB::table('delivery_sales_orders')
                ->join('surat_jalan_delivery_orders', 'delivery_sales_orders.delivery_order_id', '=', 'surat_jalan_delivery_orders.delivery_order_id')
                ->whereIn('delivery_sales_orders.sales_order_id', $orderIds)
                ->select('delivery_sales_orders.sales_order_id as so_id', 'surat_jalan_delivery_orders.surat_jalan_id as sj_id')
                ->get();

            $sjToSo = [];
            foreach ($soToSjRows as $row) {
                $sjToSo[$row->sj_id] = $row->so_id;
            }

            if (! empty($sjToSo)) {
                $sjInvoices = Invoice::withoutGlobalScopes()
                    ->where('from_model_type', \App\Models\SuratJalan::class)
                    ->whereIn('from_model_id', array_keys($sjToSo))
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->get(['id', 'from_model_id', 'total']);

                foreach ($sjInvoices as $inv) {
                    $soId = $sjToSo[$inv->from_model_id] ?? null;
                    if ($soId) {
                        $sjInvoiced[$soId] = ($sjInvoiced[$soId] ?? 0.0) + (float) $inv->total;
                    }
                }
            }
        }

        $total = 0.0;
        $count = 0;
        foreach ($orders as $order) {
            $invTotal = ($directInvoiced[$order->id] ?? 0.0) + ($sjInvoiced[$order->id] ?? 0.0);
            $open = max(0.0, (float) $order->total_amount + (float) $order->shipping_cost - $invTotal);
            if ($open > 0.005) {
                $total += $open;
                $count++;
            }
        }

        return ['total' => round($total, 2), 'count' => $count];
    }

    public function exposure(Customer $customer): float
    {
        return round($this->receivables($customer) + $this->openSalesOrders($customer)['total'], 2);
    }

    /**
     * Ringkasan lengkap untuk Info Customer (semua tipe pembayaran): endpoint ringkas tunggal dipakai React & Filament.
     *
     * @return array<string, mixed>
     */
    public function summary(Customer $customer): array
    {
        $receivables = $this->receivables($customer);
        $open = $this->openSalesOrders($customer);
        $exposure = round($receivables + $open['total'], 2);

        $overdue = DB::table('invoices')
            ->join('account_receivables', 'invoices.id', '=', 'account_receivables.invoice_id')
            ->where('account_receivables.customer_id', $customer->id)
            ->where('account_receivables.remaining', '>', 0)
            ->whereNull('invoices.deleted_at')
            ->whereNull('account_receivables.deleted_at')
            ->where('invoices.due_date', '<', Carbon::now())
            ->whereNotIn('invoices.status', ['cancelled', 'canceled', 'paid'])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(account_receivables.remaining), 0) as total, MIN(invoices.due_date) as oldest')
            ->first();

        $limit = (float) ($customer->kredit_limit ?? 0);
        $type = (string) ($customer->tipe_pembayaran ?? 'Bebas');

        return [
            'payment_type' => $type,
            'policy' => match (true) {
                $type === 'Kredit' => 'block',                 // melebihi limit → tidak dapat disetujui (kecuali pengecualian beralasan)
                str_starts_with($type, 'COD') => 'cash',       // "COD (Bayar Lunas)": tanpa kredit; informasi saja
                default => 'info',                             // Bebas: informasi saja
            },
            'credit_limit' => $limit,
            'receivables' => $receivables,
            'open_sales_orders_total' => $open['total'],
            'open_sales_orders_count' => $open['count'],
            'exposure' => $exposure,
            'available_after_exposure' => $limit - $exposure,
            'overdue_count' => (int) ($overdue->n ?? 0),
            'overdue_total' => (float) ($overdue->total ?? 0),
            'oldest_overdue_days' => $overdue && $overdue->oldest ? (int) Carbon::parse($overdue->oldest)->diffInDays(Carbon::now()) : 0,
            'deposit_balance' => $customer->depositBalance(),
        ];
    }
}
