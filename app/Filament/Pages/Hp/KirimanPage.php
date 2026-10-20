<?php

namespace App\Filament\Pages\Hp;

use App\Services\Hp\CheckerQueue;
use App\Support\HpWarehouse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * GUDANG → Siapkan & Kirim, tampilan HP (Spesifikasi UI/UX 6.5, mockup F1).
 * Daftar DO gudang user yang siap dicek & dikirim.
 */
class KirimanPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'GUDANG';

    protected static ?string $navigationLabel = 'Kirim (HP)';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'kiriman';

    protected static ?string $title = 'Siapkan & Kirim';

    protected static string $view = 'filament.pages.hp.kiriman';

    #[Url(as: 'tab')]
    public string $tab = CheckerQueue::TAB_READY;

    #[Url(as: 'q')]
    public string $search = '';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        // Dibuka dari navigasi bawah HP dan Beranda; di desktop tetap ada menu "Siapkan & Kirim".
        return false;
    }

    public function getHeading(): string
    {
        return 'Siapkan & Kirim';
    }

    public function warehouseId(): ?int
    {
        return HpWarehouse::currentId(Auth::user());
    }

    public function selectWarehouse(int $warehouseId): void
    {
        HpWarehouse::select(Auth::user(), $warehouseId);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, [CheckerQueue::TAB_READY, CheckerQueue::TAB_WAITING_SJ, CheckerQueue::TAB_SENT_TODAY], true)
            ? $tab
            : CheckerQueue::TAB_READY;
    }

    /** Hasil scan di daftar: buka DO yang memuat barang tersebut atau cocok dengan nomor DO. */
    #[On('barcode-scanned')]
    public function onBarcodeScanned(string $code): void
    {
        $warehouseId = $this->warehouseId();
        if (! $warehouseId) {
            return;
        }

        $code = trim($code);
        $match = app(CheckerQueue::class)->query($warehouseId, $this->tab)
            ->where(fn ($q) => $q->where('do_number', $code)->orWhereHas('deliveryOrderItem.product', fn ($p) => $p->where('sku', $code)))
            ->first();

        if ($match) {
            $this->redirect(KirimanDetailPage::getUrl(['record' => $match->getKey()]));

            return;
        }

        $this->search = $code;
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $warehouseId = $this->warehouseId();
        $queue = app(CheckerQueue::class);

        return [
            'warehouseId' => $warehouseId,
            'warehouseLabel' => HpWarehouse::label($warehouseId),
            'warehouseOptions' => HpWarehouse::canSwitch($user) ? HpWarehouse::options($user) : collect(),
            'counts' => $warehouseId ? $queue->counts($warehouseId) : [],
            'orders' => $warehouseId ? $queue->query($warehouseId, $this->tab, $this->search)->limit(50)->get() : collect(),
            'queue' => $queue,
            'pickingListUrl' => $warehouseId ? route('pdf.picking-list', ['warehouse' => $warehouseId]) : null,
        ];
    }
}
