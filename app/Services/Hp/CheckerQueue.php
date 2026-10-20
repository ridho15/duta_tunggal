<?php

namespace App\Services\Hp;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemCheck;
use App\Models\User;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Antrian kiriman untuk checker gudang di HP (Spesifikasi UI/UX 6.5, Tahap 3).
 * Hanya membaca DO dan memanggil DeliveryOrderTransitions — tidak ada logika stok sendiri.
 */
class CheckerQueue
{
    /** Status DO yang siap dicek & dikirim gudang (sama dengan aksi Kirim di desktop). */
    public const SHIPPABLE = ['approved', 'confirmed', 'partial', 'delivery_failed'];

    public const TAB_READY = 'siap';
    public const TAB_WAITING_SJ = 'menunggu_sj';
    public const TAB_SENT_TODAY = 'terkirim';

    public function baseQuery(int $warehouseId): Builder
    {
        return DeliveryOrder::query()
            ->where('warehouse_id', $warehouseId)
            ->with(['salesOrders.customer', 'deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources.rak']);
    }

    public function query(int $warehouseId, string $tab, ?string $search = null): Builder
    {
        $query = $this->baseQuery($warehouseId);

        match ($tab) {
            self::TAB_SENT_TODAY => $query->where('status', 'sent')->whereDate('updated_at', today()),
            self::TAB_WAITING_SJ => $query->whereIn('status', self::SHIPPABLE)->when(
                DeliveryOrderTransitions::requiresSuratJalan(),
                fn (Builder $q) => $this->whereNoIssuedSuratJalan($q),
                fn (Builder $q) => $q->whereRaw('1 = 0'),
            ),
            default => $query->whereIn('status', self::SHIPPABLE)->when(
                DeliveryOrderTransitions::requiresSuratJalan(),
                fn (Builder $q) => $this->whereHasIssuedSuratJalan($q),
            ),
        };

        if (filled($search)) {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('do_number', 'like', $term)
                    ->orWhereHas('salesOrders.customer', fn (Builder $c) => $c->where('name', 'like', $term))
                    ->orWhereHas('salesOrders', fn (Builder $s) => $s->where('so_number', 'like', $term));
            });
        }

        return $query->orderBy('delivery_date')->orderBy('id');
    }

    /** @return array<string, int> jumlah per tab */
    public function counts(int $warehouseId): array
    {
        return [
            self::TAB_READY => $this->query($warehouseId, self::TAB_READY)->count(),
            self::TAB_WAITING_SJ => $this->query($warehouseId, self::TAB_WAITING_SJ)->count(),
            self::TAB_SENT_TODAY => $this->query($warehouseId, self::TAB_SENT_TODAY)->count(),
        ];
    }

    /** @return array<int, int> id item DO yang sudah dicentang */
    public function checkedItemIds(DeliveryOrder $deliveryOrder): array
    {
        return DeliveryOrderItemCheck::query()
            ->whereIn('delivery_order_item_id', $deliveryOrder->deliveryOrderItem()->pluck('id'))
            ->pluck('delivery_order_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function setChecked(DeliveryOrderItem $item, bool $checked, User $user): void
    {
        if ($checked) {
            DeliveryOrderItemCheck::updateOrCreate(
                ['delivery_order_item_id' => $item->getKey()],
                ['checked_by' => $user->getKey(), 'checked_at' => now()],
            );

            return;
        }

        DeliveryOrderItemCheck::where('delivery_order_item_id', $item->getKey())->delete();
    }

    /** Barang yang akan dikirim (qty > 0). */
    public function items(DeliveryOrder $deliveryOrder): Collection
    {
        return $deliveryOrder->deliveryOrderItem
            ->filter(fn (DeliveryOrderItem $item) => (float) $item->quantity > 0)
            ->sortBy(fn (DeliveryOrderItem $item) => ($item->warehouseSources->first()?->rak?->code ?? 'zzz').'|'.$item->product?->name)
            ->values();
    }

    /**
     * Alasan tombol KIRIM belum bisa dipakai, atau null bila boleh dikirim.
     *
     * @param  array<int, int>  $checkedIds
     */
    public function blockReason(DeliveryOrder $deliveryOrder, array $checkedIds): ?string
    {
        if (! in_array($deliveryOrder->status, self::SHIPPABLE, true)) {
            return 'DO ini berstatus "'.DeliveryOrder::statusLabel($deliveryOrder->status).'", bukan siap dikirim.';
        }

        $items = $this->items($deliveryOrder);
        $unchecked = $items->reject(fn (DeliveryOrderItem $item) => in_array((int) $item->getKey(), $checkedIds, true))->count();

        if ($unchecked > 0) {
            return 'Centang semua barang dulu untuk mengirim ('.$unchecked.' belum dicek).';
        }

        if (DeliveryOrderTransitions::requiresSuratJalan() && ! $deliveryOrder->hasIssuedSuratJalan()) {
            return 'Belum bisa: menunggu Surat Jalan dari admin pengiriman.';
        }

        return null;
    }

    /**
     * KIRIM dari HP: memakai pintu status yang sama dengan desktop (validasi stok fisik, log, jurnal).
     *
     * @throws DeliveryOrderTransitionException
     */
    public function ship(DeliveryOrder $deliveryOrder, User $user): DeliveryOrder
    {
        $reason = $this->blockReason($deliveryOrder->fresh(['deliveryOrderItem.warehouseSources.rak', 'deliveryOrderItem.product']), $this->checkedItemIds($deliveryOrder));
        if ($reason !== null) {
            throw new DeliveryOrderTransitionException($reason);
        }

        return app(DeliveryOrderTransitions::class)->to($deliveryOrder, 'sent', [
            'actor' => $user,
            'comments' => 'Dikirim checker dari HP; semua barang sudah dicek.',
            'source' => 'hp_checker',
        ]);
    }

    private function whereHasIssuedSuratJalan(Builder $query): Builder
    {
        return $query->whereHas('suratJalan', fn (Builder $q) => $q->where('surat_jalans.status', \App\Models\SuratJalan::STATUS_ISSUED));
    }

    private function whereNoIssuedSuratJalan(Builder $query): Builder
    {
        return $query->whereDoesntHave('suratJalan', fn (Builder $q) => $q->where('surat_jalans.status', \App\Models\SuratJalan::STATUS_ISSUED));
    }
}
