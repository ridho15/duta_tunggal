<?php

namespace App\Services\Hp;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SuratJalan;
use App\Services\ReturnProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Ada kurang / rusak" dari layar checker HP (Tahap 3, mockup F2) → draft Retur DO (DO.000xR).
 * Kurang = kondisi "good" (kembali ke gudang sumber), rusak = "damage" (gudang karantina).
 * Persetujuan, restock, dan DO pengganti (…C) tetap memakai alur Retur DO yang sudah ada.
 */
class DeliveryOrderShortageReturn
{
    public function __construct(private readonly ReturnProductService $returns) {}

    public function blockReason(DeliveryOrder $deliveryOrder): ?string
    {
        if ($deliveryOrder->suratJalan()->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES)->exists()) {
            return "{$deliveryOrder->do_number} sudah punya Surat Jalan aktif, jadi tidak bisa diretur dari sini.";
        }

        $existing = $this->existing($deliveryOrder);
        if ($existing) {
            return "Retur {$existing->return_number} untuk DO ini sudah ada (status ".($existing->status === 'draft' ? 'menunggu persetujuan' : 'sudah diproses').').';
        }

        return null;
    }

    public function existing(DeliveryOrder $deliveryOrder): ?ReturnProduct
    {
        return ReturnProduct::where('from_model_type', DeliveryOrder::class)
            ->where('from_model_id', $deliveryOrder->getKey())
            ->first();
    }

    /**
     * @param  array<int|string, array{short?: float|int|string|null, reject?: float|int|string|null}>  $lines  per id item DO
     */
    public function create(DeliveryOrder $deliveryOrder, array $lines, ?string $note = null): ReturnProduct
    {
        if ($reason = $this->blockReason($deliveryOrder)) {
            throw ValidationException::withMessages(['lines' => $reason]);
        }

        $items = $deliveryOrder->deliveryOrderItem()->with(['warehouseSources', 'product'])->get()->keyBy('id');
        $prepared = [];

        foreach ($lines as $itemId => $line) {
            /** @var DeliveryOrderItem|null $item */
            $item = $items->get((int) $itemId);
            if (! $item) {
                continue;
            }

            $short = max(0, (float) ($line['short'] ?? 0));
            $reject = max(0, (float) ($line['reject'] ?? 0));

            if ($short + $reject <= 0) {
                continue;
            }

            if ($short + $reject > (float) $item->quantity) {
                throw ValidationException::withMessages([
                    "lines.{$itemId}" => sprintf(
                        '%s: kurang + rusak (%s) melebihi qty DO (%s).',
                        $item->product?->name,
                        $this->fmt($short + $reject),
                        $this->fmt((float) $item->quantity)
                    ),
                ]);
            }

            $prepared[] = [$item, $short, $reject];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['lines' => 'Isi jumlah kurang atau rusak minimal pada satu barang.']);
        }

        return DB::transaction(function () use ($deliveryOrder, $prepared, $note) {
            $locked = DeliveryOrder::lockForUpdate()->findOrFail($deliveryOrder->getKey());
            if ($reason = $this->blockReason($locked)) {
                throw ValidationException::withMessages(['lines' => $reason]);
            }

            $summary = collect($prepared)->map(fn ($p) => sprintf(
                '%s: kurang %s, rusak %s',
                $p[0]->product?->name,
                $this->fmt($p[1]),
                $this->fmt($p[2])
            ))->implode('; ');

            $returnProduct = ReturnProduct::create([
                'return_number' => $this->returns->generateReturnNumber(DeliveryOrder::class, $locked->getKey()),
                'from_model_id' => $locked->getKey(),
                'from_model_type' => DeliveryOrder::class,
                'warehouse_id' => $locked->warehouse_id
                    ?? $prepared[0][0]->warehouseSources->first()?->warehouse_id,
                'status' => 'draft',
                'reason' => trim('Dicatat checker dari HP saat pengecekan barang. '.$summary.'. '.($note ?? '')),
                'return_action' => 'reduce_quantity_only',
            ]);

            foreach ($prepared as [$item, $short, $reject]) {
                $rakId = $item->warehouseSources->first()?->rak_id;

                if ($short > 0) {
                    ReturnProductItem::create([
                        'return_product_id' => $returnProduct->getKey(),
                        'from_item_model_id' => $item->getKey(),
                        'from_item_model_type' => DeliveryOrderItem::class,
                        'product_id' => $item->product_id,
                        'quantity' => $short,
                        'rak_id' => $rakId,
                        'condition' => 'good',
                        'note' => 'Kurang saat dicek checker',
                    ]);
                }

                if ($reject > 0) {
                    ReturnProductItem::create([
                        'return_product_id' => $returnProduct->getKey(),
                        'from_item_model_id' => $item->getKey(),
                        'from_item_model_type' => DeliveryOrderItem::class,
                        'product_id' => $item->product_id,
                        'quantity' => $reject,
                        'rak_id' => $rakId,
                        'condition' => 'damage',
                        'note' => 'Rusak / reject saat dicek checker',
                    ]);
                }
            }

            return $returnProduct;
        });
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
