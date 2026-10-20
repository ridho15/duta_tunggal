<?php

namespace App\Filament\Pages\Hp;

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Services\Hp\CheckerQueue;
use App\Support\HpWarehouse;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Layar checker di HP untuk satu DO (Spesifikasi UI/UX 6.5, mockup E5):
 * centang barang satu per satu, KIRIM aktif setelah semua dicentang.
 */
class KirimanDetailPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $slug = 'kiriman/{record}';

    protected static string $view = 'filament.pages.hp.kiriman-detail';

    public int $recordId;

    public static function getRelativeRouteName(): string
    {
        return 'kiriman-detail';
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $deliveryOrder = DeliveryOrder::findOrFail($record);

        $user = Auth::user();
        abort_unless($user->canAccessCabang($deliveryOrder->cabang_id), 403);
        abort_unless(
            HpWarehouse::allows($user, (int) $deliveryOrder->warehouse_id) || \App\Support\MenuAccess::isSuper($user),
            403
        );

        $this->recordId = (int) $deliveryOrder->getKey();
    }

    public function getTitle(): string
    {
        return $this->deliveryOrder()->do_number ?? 'Kiriman';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function deliveryOrder(): DeliveryOrder
    {
        return DeliveryOrder::with(['salesOrders.customer', 'deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources.rak', 'warehouse'])
            ->findOrFail($this->recordId);
    }

    public function toggle(int $itemId): void
    {
        $item = $this->item($itemId);
        if (! $item) {
            return;
        }

        $queue = app(CheckerQueue::class);
        $isChecked = in_array($itemId, $queue->checkedItemIds($this->deliveryOrder()), true);
        $queue->setChecked($item, ! $isChecked, Auth::user());
    }

    /** Scan barcode SKU → centang barang yang cocok. */
    #[On('barcode-scanned')]
    public function onBarcodeScanned(string $code): void
    {
        $code = trim($code);
        $deliveryOrder = $this->deliveryOrder();
        $item = $deliveryOrder->deliveryOrderItem->first(fn (DeliveryOrderItem $i) => strcasecmp((string) $i->product?->sku, $code) === 0);

        if (! $item) {
            Notification::make()
                ->warning()
                ->title('Barang tidak ada di DO ini')
                ->body("Kode {$code} tidak termasuk {$deliveryOrder->do_number}. Periksa lagi barang yang diambil.")
                ->duration(6000)
                ->send();

            return;
        }

        app(CheckerQueue::class)->setChecked($item, true, Auth::user());

        Notification::make()->success()->title($item->product?->name.' dicentang')->duration(2500)->send();
    }

    public function ship(): void
    {
        $deliveryOrder = $this->deliveryOrder();

        try {
            app(CheckerQueue::class)->ship($deliveryOrder, Auth::user());
        } catch (DeliveryOrderTransitionException $e) {
            Notification::make()->danger()->title('Belum bisa dikirim')->body($e->getMessage())->persistent()->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($deliveryOrder->do_number.' dikirim')
            ->body('Stok gudang sudah dikurangi. Lanjutkan ke kiriman berikutnya.')
            ->duration(6000)
            ->send();

        $this->redirect(KirimanPage::getUrl());
    }

    protected function item(int $itemId): ?DeliveryOrderItem
    {
        return DeliveryOrderItem::query()
            ->where('delivery_order_id', $this->recordId)
            ->whereKey($itemId)
            ->first();
    }

    protected function getViewData(): array
    {
        $deliveryOrder = $this->deliveryOrder();
        $queue = app(CheckerQueue::class);
        $checked = $queue->checkedItemIds($deliveryOrder);
        $items = $queue->items($deliveryOrder);

        return [
            'deliveryOrder' => $deliveryOrder,
            'items' => $items,
            'checked' => $checked,
            'checkedCount' => $items->filter(fn ($item) => in_array((int) $item->id, $checked, true))->count(),
            'blockReason' => $queue->blockReason($deliveryOrder, $checked),
            'isShippable' => in_array($deliveryOrder->status, CheckerQueue::SHIPPABLE, true),
            'customer' => $deliveryOrder->salesOrders->first()?->customer?->name,
            'backUrl' => KirimanPage::getUrl(),
            'queueCount' => HpWarehouse::currentId(Auth::user())
                ? $queue->query((int) HpWarehouse::currentId(Auth::user()), CheckerQueue::TAB_READY)->count()
                : null,
            'returUrl' => class_exists(ReturDoPage::class) ? ReturDoPage::getUrl(['record' => $deliveryOrder->id]) : null,
        ];
    }
}
