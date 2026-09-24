<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SuratJalan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun data cetak Surat Jalan (header perusahaan/cabang, pengiriman, barang per DO, tanda tangan)
 * dari satu sumber, sehingga template PDF hanya menampilkan dan dapat diuji tanpa merender PDF.
 *
 * Surat Jalan adalah dokumen serah-terima: TANPA harga, diskon, pajak, maupun subtotal.
 */
class SuratJalanDocumentBuilder
{
    public const COMPANY_NAME = 'PT DUTA TUNGGAL';

    public const DEFAULT_COMPANY_EMAIL = 'admin@dutatunggal.co.id';

    public const NOT_SCHEDULED_LABEL = 'Belum dijadwalkan';

    /** Relasi yang perlu dimuat agar penyusunan data tidak N+1 dan tidak error. */
    public const RELATIONS = [
        'cabang',
        'createdBy',
        'cancelledBy',
        'deliveryOrder.cabang',
        'deliveryOrder.driver',
        'deliveryOrder.vehicle',
        'deliveryOrder.salesOrders.customer',
        'deliveryOrder.deliveryOrderItem.product.uom',
        'deliverySchedules.driver',
        'deliverySchedules.vehicle',
    ];

    public function build(SuratJalan $suratJalan): array
    {
        $suratJalan->loadMissing(self::RELATIONS);

        $groups = $suratJalan->deliveryOrder
            ->sortBy('do_number')
            ->map(fn (DeliveryOrder $deliveryOrder) => $this->group($deliveryOrder))
            ->values();

        $delivery = $this->delivery($suratJalan);

        return [
            'company' => $this->company($suratJalan),
            'number' => $suratJalan->sj_number,
            'issued_date' => $this->formatDate($suratJalan->issued_at),
            'status' => $suratJalan->status,
            'status_label' => $suratJalan->status_label,
            'is_draft' => $suratJalan->isDraft(),
            'is_cancelled' => $suratJalan->isCancelled(),
            'cancelled_at' => $suratJalan->cancelled_at?->copy()->locale('id')->translatedFormat('d F Y H:i'),
            'cancelled_by' => $suratJalan->cancelled_by ? $suratJalan->cancelledBy->name : null,
            'cancel_reason' => $suratJalan->cancel_reason,
            'branch' => $this->issuingBranch($suratJalan)->nama ?: null,
            'customers' => $groups->flatMap(fn ($g) => $g['customers'])->unique()->values()->all(),
            'addresses' => $groups->flatMap(fn ($g) => $g['addresses'])->unique()->values()->all(),
            'delivery_order_numbers' => $groups->pluck('do_number')->all(),
            'sales_order_numbers' => $groups->flatMap(fn ($g) => $g['sales_orders'])->unique()->values()->all(),
            'delivery' => $delivery,
            'groups' => $groups->all(),
            'total_lines' => $groups->sum(fn ($g) => count($g['items'])),
            'signatures' => [
                'driver_name' => ($delivery['sender_name'] !== '-' && filled($delivery['sender_name'])) ? $delivery['sender_name'] : null,
                'driver_role' => $delivery['is_third_party'] ? 'Ekspedisi / Kurir' : 'Driver',
            ],
            'printed_at' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'printed_by' => auth()->user()?->name,
        ];
    }

    /**
     * Kop: nama perusahaan + data cabang penerbit. Alamat/telepon diambil dari master cabang; bila
     * kosong DIKOSONGKAN (tidak diisi placeholder) supaya tidak ada alamat palsu pada dokumen resmi.
     */
    protected function company(SuratJalan $suratJalan): array
    {
        $cabang = $this->issuingBranch($suratJalan);

        return [
            'name' => self::COMPANY_NAME,
            'branch' => $cabang->nama ?: null,
            'address' => $cabang->alamat ?: null,
            'phone' => $cabang->telepon ?: null,
            'email' => AppSetting::get('company_email', self::DEFAULT_COMPANY_EMAIL),
        ];
    }

    /** Cabang penerbit: cabang Surat Jalan; data lama tanpa cabang memakai cabang DO pertama. */
    protected function issuingBranch(SuratJalan $suratJalan): Cabang
    {
        if ($suratJalan->cabang_id && $suratJalan->cabang->exists) {
            return $suratJalan->cabang;
        }

        return $suratJalan->deliveryOrder->first(fn (DeliveryOrder $d) => $d->cabang_id && $d->cabang->exists)?->cabang
            ?? $suratJalan->cabang;
    }

    protected function delivery(SuratJalan $suratJalan): array
    {
        $schedule = $suratJalan->primaryDeliverySchedule();

        if (! $schedule) {
            $firstDo = $suratJalan->deliveryOrder->first(fn (DeliveryOrder $d) => $d->driver_id || $d->vehicle_id);

            $driverName = $firstDo?->driver?->name;
            $vehiclePlate = $firstDo?->vehicle?->plate;
            if ($vehiclePlate && $firstDo?->vehicle?->type) {
                $vehiclePlate .= " ({$firstDo->vehicle->type})";
            }

            $hasLogistics = filled($driverName) || filled($vehiclePlate);

            return [
                'scheduled' => $hasLogistics,
                'is_third_party' => false,
                'schedule_number' => null,
                'departure' => $firstDo?->delivery_date ? $this->formatDate($firstDo->delivery_date) : null,
                'method_label' => $hasLogistics ? 'Pengiriman Langsung' : self::NOT_SCHEDULED_LABEL,
                'sender_name' => $driverName ?: '-',
                'vehicle' => $vehiclePlate ?: '-',
                'tracking_number' => null,
            ];
        }

        return [
            'scheduled' => true,
            'is_third_party' => $schedule->delivery_method === 'ekspedisi',
            'schedule_number' => $schedule->schedule_number,
            'departure' => $this->formatDate($schedule->scheduled_date),
            'method_label' => $schedule->delivery_method_label,
            'sender_name' => $schedule->senderName(),
            'vehicle' => $schedule->vehicleLabel(),
            'tracking_number' => $schedule->tracking_number ?: null,
        ];
    }

    protected function group(DeliveryOrder $deliveryOrder): array
    {
        $salesOrders = $deliveryOrder->salesOrders;

        $itemsList = $deliveryOrder->deliveryOrderItem
            ->filter(fn (DeliveryOrderItem $item) => (float) $item->quantity > 0)
            ->values();

        $saleOrderItemIds = $itemsList->pluck('sale_order_item_id')->filter()->unique()->all();
        $progress = ! empty($saleOrderItemIds)
            ? app(SaleOrderDeliveryProgress::class)->forItems($saleOrderItemIds, excludeDeliveryOrderId: $deliveryOrder->id)
            : [];

        $items = $itemsList
            ->map(function (DeliveryOrderItem $item, int $index) use ($progress) {
                $prog = $item->sale_order_item_id ? ($progress[$item->sale_order_item_id] ?? null) : null;
                $ordered = $prog ? (float) $prog['ordered'] : (float) $item->quantity;
                $deliveredPrior = $prog ? (float) $prog['delivered'] : 0.0;
                $sendNow = (float) $item->quantity;
                $remaining = max(0.0, $ordered - $deliveredPrior - $sendNow);

                return [
                    'no' => $index + 1,
                    'sku' => $item->product->sku ?: '-',
                    'name' => $item->product->name ?: '-',
                    'ordered_quantity' => $this->formatQuantity($ordered),
                    'delivered_quantity' => $this->formatQuantity($deliveredPrior),
                    'quantity' => $this->formatQuantity($sendNow),
                    'remaining_quantity' => $this->formatQuantity($remaining),
                    'unit' => $item->product->uom->abbreviation ?: ($item->product->uom->name ?: '-'),
                    'note' => $item->reason ?: '',
                ];
            })
            ->all();

        return [
            'do_number' => $deliveryOrder->do_number,
            'do_status' => DeliveryOrder::statusLabel($deliveryOrder->status),
            'sales_orders' => $salesOrders->pluck('so_number')->filter()->unique()->values()->all(),
            'customers' => $this->customerNames($salesOrders),
            'addresses' => $salesOrders->pluck('shipped_to')
                ->map(fn ($address) => DeliveryOrderSourceValidator::normalizeAddress($address) === '' ? null : trim((string) $address))
                ->filter()->unique()->values()->all(),
            'items' => $items,
        ];
    }

    protected function customerNames(Collection $salesOrders): array
    {
        return $salesOrders
            ->map(fn ($salesOrder) => $salesOrder->customer?->name ?: $salesOrder->customer?->perusahaan)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function formatDate(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y');
    }

    /** 12 → "12", 12.5 → "12,5", 1200 → "1.200". */
    protected function formatQuantity(mixed $quantity): string
    {
        $formatted = number_format((float) $quantity, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
