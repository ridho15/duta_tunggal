<?php

namespace App\Filament\Pages\Hp;

use App\Filament\Resources\ReturnProductResource;
use App\Models\DeliveryOrder;
use App\Services\Hp\CheckerQueue;
use App\Services\Hp\DeliveryOrderShortageReturn;
use App\Support\HpWarehouse;
use App\Support\MenuAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * "Ada kurang / rusak" dari layar checker (Tahap 3, mockup F2): catat qty kurang dan rusak per barang,
 * hasilnya draft Retur DO (DO.000xR) yang diproses lewat alur Retur DO biasa.
 */
class ReturDoPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $slug = 'kiriman/{record}/retur';

    protected static string $view = 'filament.pages.hp.retur-do';

    public int $recordId;

    /** @var array<int, array{short: float|int, reject: float|int}> */
    public array $lines = [];

    public ?string $note = null;

    public static function getRelativeRouteName(): string
    {
        return 'kiriman-retur';
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $deliveryOrder = DeliveryOrder::with('deliveryOrderItem')->findOrFail($record);
        $user = Auth::user();

        abort_unless($user->canAccessCabang($deliveryOrder->cabang_id), 403);
        abort_unless(
            HpWarehouse::allows($user, (int) $deliveryOrder->warehouse_id)
                || MenuAccess::isSuper($user)
                || array_intersect(MenuAccess::codesFor($user), ['SLS', 'ADP']),
            403
        );

        $this->recordId = (int) $deliveryOrder->getKey();

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $this->lines[$item->id] = ['short' => 0, 'reject' => 0];
        }
    }

    public function getTitle(): string
    {
        return 'Catat barang kurang / rusak';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function deliveryOrder(): DeliveryOrder
    {
        return DeliveryOrder::with(['deliveryOrderItem.product.uom', 'deliveryOrderItem.warehouseSources.rak', 'warehouse'])
            ->findOrFail($this->recordId);
    }

    public function step(int $itemId, string $field, int $delta): void
    {
        if (! isset($this->lines[$itemId]) || ! in_array($field, ['short', 'reject'], true)) {
            return;
        }

        $item = $this->deliveryOrder()->deliveryOrderItem->firstWhere('id', $itemId);
        $max = (float) ($item?->quantity ?? 0);
        $other = (float) $this->lines[$itemId][$field === 'short' ? 'reject' : 'short'];

        $value = (float) $this->lines[$itemId][$field] + $delta;
        $this->lines[$itemId][$field] = max(0, min($value, $max - $other));
    }

    public function save(): void
    {
        $deliveryOrder = $this->deliveryOrder();

        try {
            $return = app(DeliveryOrderShortageReturn::class)->create($deliveryOrder, $this->lines, $this->note);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Retur belum tersimpan')
                ->body(collect($e->errors())->flatten()->first())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("Retur {$return->return_number} tersimpan")
            ->body('Menunggu persetujuan. Setelah disetujui, stok kembali dan DO pengganti dibuat untuk Surat Jalan.')
            ->duration(6000)
            ->send();

        $this->redirect(KirimanPage::getUrl());
    }

    protected function getViewData(): array
    {
        $deliveryOrder = $this->deliveryOrder();
        $service = app(DeliveryOrderShortageReturn::class);
        $existing = $service->existing($deliveryOrder);

        $shortTotal = collect($this->lines)->sum(fn ($l) => (float) $l['short']);
        $rejectTotal = collect($this->lines)->sum(fn ($l) => (float) $l['reject']);

        return [
            'deliveryOrder' => $deliveryOrder,
            'items' => app(CheckerQueue::class)->items($deliveryOrder),
            'blockReason' => $service->blockReason($deliveryOrder),
            'existingUrl' => $existing && ReturnProductResource::canAccess() ? ReturnProductResource::getUrl('view', ['record' => $existing]) : null,
            'returnNumber' => $deliveryOrder->do_number.'R',
            'shortTotal' => $shortTotal,
            'rejectTotal' => $rejectTotal,
            'backUrl' => KirimanDetailPage::canAccess() ? KirimanDetailPage::getUrl(['record' => $deliveryOrder->id]) : KirimanPage::getUrl(),
        ];
    }
}
