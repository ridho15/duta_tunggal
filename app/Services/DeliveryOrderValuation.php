<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya perhitungan NILAI sebuah Delivery Order — sama persis dengan yang dipakai saat invoice otomatis
 * diterbitkan dari DO (harga GROSS, diskon %, tarif & tipe pajak per baris dari item SO, lewat LineAmounts).
 *
 * Sebelumnya ada tiga perhitungan berbeda: label pilihan DO di form Invoice (harga − diskon + pajak, mencampur % dan Rp),
 * PDF DO (TaxService::compute dengan pembulatan lain), dan observer invoice (benar). Kini ketiganya memakai layanan ini.
 */
class DeliveryOrderValuation
{
    public function __construct(
        private readonly SalesInvoiceTaxResolver $taxResolver,
        private readonly SalesInvoiceLineBuilder $lineBuilder,
    ) {}

    /**
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     by_item: array<int, array<string, mixed>>,
     *     dpp: float, tax: float, goods_total: float, additional_cost: float, total: float,
     *     unlinked_items: array<int, int>,
     *     ppn_rate: float, tipe_pajak: string
     * }
     */
    public function forDeliveryOrder(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing('salesOrders', 'deliveryOrderItem.saleOrderItem', 'deliveryOrderItem.product');

        $primarySo = $deliveryOrder->salesOrders->first();
        $taxData = $primarySo ? $this->taxResolver->resolveFromSaleOrder($primarySo) : [];
        $ppnRate = (float) ($taxData['ppn_rate'] ?? 0);
        $tipePajak = $taxData['tipe_pajak'] ?? 'None';

        $dpp = 0.0;
        $totalTax = 0.0;
        $goodsTotal = 0.0;
        $lines = [];
        $byItem = [];
        $unlinked = [];

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qty = (float) ($item->quantity ?? 0);
            if ($qty <= 0) {
                continue;
            }

            // Relasi saleOrderItem memakai withDefault(): pastikan cek exists agar model kosong tidak dianggap ada.
            // Jika item DO belum bertaut langsung, cari dari SO terkait berdasarkan product_id.
            $saleOrderItem = ($item->saleOrderItem && $item->saleOrderItem->exists) ? $item->saleOrderItem : null;
            if (! $saleOrderItem && $primarySo) {
                $saleOrderItem = $primarySo->saleOrderItem->firstWhere('product_id', $item->product_id);
            }

            if (! $saleOrderItem) {
                $unlinked[] = (int) $item->id;
            }

            $unitPrice = $saleOrderItem ? (float) $saleOrderItem->unit_price : (float) ($item->product?->sell_price ?? 0);
            $discountPct = $saleOrderItem ? max(0.0, min(100.0, (float) $saleOrderItem->discount)) : 0.0;

            // Pajak per baris dari item SO-nya (Eksklusif / Inklusif / Non Pajak); tanpa pajak bila invoice bertipe None.
            $lineRate = $tipePajak === 'None' ? 0.0 : ($saleOrderItem ? (float) $saleOrderItem->tax : $ppnRate);
            $lineType = $tipePajak === 'None' ? 'Non Pajak' : ($saleOrderItem?->tipe_pajak ?: $tipePajak);

            $line = $this->lineBuilder->attributes(
                $item->product_id, $qty, $unitPrice, $discountPct, $lineRate, $lineType, $item->product?->sales_coa_id
            );

            $dpp += $line['subtotal'];
            $totalTax += $line['tax_amount'];
            $goodsTotal += $line['total'];
            $lines[] = $line;
            $byItem[$item->id] = $line;
        }

        $additionalCost = (float) ($deliveryOrder->additional_cost ?? 0);
        $total = round($goodsTotal + $additionalCost, 2);

        if ($unlinked !== [] || ($total <= 0 && $deliveryOrder->deliveryOrderItem->isNotEmpty())) {
            Log::warning('DeliveryOrderValuation: item DO tanpa tautan item SO / nilai DO 0 — harga tidak dapat ditentukan', [
                'delivery_order_id' => $deliveryOrder->id,
                'do_number' => $deliveryOrder->do_number,
                'unlinked_item_ids' => $unlinked,
            ]);
        }

        return [
            'lines' => $lines,
            'by_item' => $byItem,
            'dpp' => $dpp,
            'tax' => $totalTax,
            'goods_total' => $goodsTotal,
            'additional_cost' => $additionalCost,
            'total' => $total,
            'ppn_rate' => $ppnRate,
            'tipe_pajak' => $tipePajak,
            'unlinked_items' => $unlinked,
        ];
    }

    /**
     * Nilai banyak DO sekaligus: relasi dimuat SEKALI (jumlah query tetap, bukan per DO).
     *
     * @param  iterable<DeliveryOrder>  $deliveryOrders
     * @return Collection<int, array> dikunci menurut id DO
     */
    public function forDeliveryOrders(iterable $deliveryOrders): Collection
    {
        $collection = new \Illuminate\Database\Eloquent\Collection(is_array($deliveryOrders) ? $deliveryOrders : iterator_to_array($deliveryOrders, false));
        $collection->load('salesOrders.saleOrderItem', 'deliveryOrderItem.saleOrderItem', 'deliveryOrderItem.product');

        return $collection->mapWithKeys(fn (DeliveryOrder $order) => [$order->id => $this->forDeliveryOrder($order)]);
    }
}
