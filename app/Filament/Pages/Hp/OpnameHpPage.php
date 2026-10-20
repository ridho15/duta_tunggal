<?php

namespace App\Filament\Pages\Hp;

use App\Filament\Resources\StockOpnameResource;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockOpnameService;
use App\Support\HpWarehouse;
use App\Support\MenuAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Stock Opname di HP, blind count (Spesifikasi UI/UX 6.12, mockup D3).
 *
 * Jumlah sistem (system_qty) TIDAK pernah dikirim ke browser selama penghitungan: properti
 * publik Livewire hanya id & angka hitungan, dan view tidak menampilkan system_qty.
 * Selisih baru terlihat di desktop setelah "Selesai menghitung", lalu direview & disetujui.
 */
class OpnameHpPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $slug = 'opname-hp';

    protected static ?string $title = 'Stock Opname';

    protected static string $view = 'filament.pages.hp.opname';

    #[Url(as: 'opname')]
    public ?int $opnameId = null;

    #[Locked]
    public ?int $currentItemId = null;

    public ?string $qty = null;

    /** @var array<int, int> item yang dilewati di sesi ini */
    #[Locked]
    public array $skipped = [];

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(): void
    {
        if ($this->opnameId && ! $this->opname()) {
            $this->opnameId = null;
        }

        if ($this->opnameId) {
            $this->moveToNext();
        }
    }

    public function getHeading(): string
    {
        return $this->opnameId ? '' : 'Stock Opname';
    }

    public function selectWarehouse(int $warehouseId): void
    {
        HpWarehouse::select(Auth::user(), $warehouseId);
    }

    /** Opname yang boleh dibuka user di gudangnya (status draft / sedang berlangsung). */
    public function opname(): ?StockOpname
    {
        if (! $this->opnameId) {
            return null;
        }

        $opname = StockOpname::with('warehouse')->find($this->opnameId);
        if (! $opname || ! in_array($opname->status, ['draft', 'in_progress'], true)) {
            return null;
        }

        $user = Auth::user();
        if (! $user->canAccessCabang($opname->warehouse?->cabang_id)) {
            return null;
        }

        if (! HpWarehouse::allows($user, (int) $opname->warehouse_id) && ! MenuAccess::isSuper($user)) {
            return null;
        }

        return $opname;
    }

    public function start(int $opnameId): void
    {
        $this->opnameId = $opnameId;
        $opname = $this->opname();
        abort_unless($opname, 403);

        if ($opname->status === 'draft') {
            try {
                app(StockOpnameService::class)->startPhysicalCount($opname);
            } catch (ValidationException $e) {
                $this->notifyError($e);
                $this->opnameId = null;

                return;
            }
        }

        $this->skipped = [];
        $this->moveToNext();
    }

    public function pause(): void
    {
        $this->opnameId = null;
        $this->currentItemId = null;
        $this->qty = null;
        $this->skipped = [];
    }

    public function step(int $delta): void
    {
        $value = is_numeric($this->qty) ? (float) $this->qty : 0;
        $this->qty = (string) max(0, $value + $delta);
    }

    public function saveAndNext(): void
    {
        $item = $this->currentItem();
        if (! $item) {
            return;
        }

        if (! is_numeric($this->qty) || (float) $this->qty < 0) {
            Notification::make()->danger()->title('Isi jumlah hasil hitung (0 atau lebih).')->send();

            return;
        }

        $item->physical_qty = (float) $this->qty;
        $item->save();

        $this->skipped = array_values(array_diff($this->skipped, [$item->id]));
        $this->moveToNext();
    }

    public function skip(): void
    {
        if ($this->currentItemId) {
            $this->skipped[] = $this->currentItemId;
            $this->skipped = array_values(array_unique($this->skipped));
        }

        $this->moveToNext();
    }

    public function edit(int $itemId): void
    {
        $item = $this->items()->firstWhere('id', $itemId);
        if (! $item) {
            return;
        }

        $this->currentItemId = $item->id;
        $this->qty = $item->physical_qty === null ? null : $this->fmt((float) $item->physical_qty);
    }

    /** Scan barcode SKU → langsung ke barang tersebut. */
    #[On('barcode-scanned')]
    public function onBarcodeScanned(string $code): void
    {
        if (! $this->opnameId) {
            return;
        }

        $item = $this->items()->first(fn (StockOpnameItem $i) => strcasecmp((string) $i->product?->sku, trim($code)) === 0);

        if (! $item) {
            Notification::make()->warning()->title('Barang tidak ada di daftar opname ini')->body("Kode {$code}. Laporkan ke admin bila barangnya memang ada di gudang.")->duration(6000)->send();

            return;
        }

        $this->edit($item->id);
    }

    public function finish(): void
    {
        $opname = $this->opname();
        if (! $opname) {
            return;
        }

        try {
            app(StockOpnameService::class)->completePhysicalCount($opname);
        } catch (ValidationException $e) {
            $this->notifyError($e);

            return;
        }

        Notification::make()
            ->success()
            ->title("Penghitungan {$opname->opname_number} selesai")
            ->body('Selisih dengan jumlah sistem sekarang bisa direview di menu Stock Opname sebelum disetujui.')
            ->duration(8000)
            ->send();

        $this->pause();
    }

    /** @return Collection<int, StockOpnameItem> */
    public function items(): Collection
    {
        if (! $this->opnameId) {
            return collect();
        }

        return StockOpnameItem::query()
            ->where('stock_opname_id', $this->opnameId)
            ->with(['product.uom', 'rak'])
            ->get(['id', 'stock_opname_id', 'product_id', 'rak_id', 'physical_qty'])
            ->sortBy(fn (StockOpnameItem $i) => sprintf('%s|%s', $i->rak?->code ?? 'zzzz', $i->product?->name))
            ->values();
    }

    protected function currentItem(): ?StockOpnameItem
    {
        if (! $this->currentItemId) {
            return null;
        }

        return StockOpnameItem::query()
            ->where('stock_opname_id', $this->opnameId)
            ->whereKey($this->currentItemId)
            ->first();
    }

    protected function moveToNext(): void
    {
        $items = $this->items();
        $uncounted = $items->filter(fn (StockOpnameItem $i) => $i->physical_qty === null);

        $next = $uncounted->first(fn (StockOpnameItem $i) => ! in_array($i->id, $this->skipped, true))
            ?? $uncounted->first();

        if ($next && $uncounted->every(fn (StockOpnameItem $i) => in_array($i->id, $this->skipped, true))) {
            $this->skipped = []; // semua sisa sudah dilewati: mulai lagi dari yang dilewati
        }

        $this->currentItemId = $next?->id;
        $this->qty = null;
    }

    protected function notifyError(ValidationException $e): void
    {
        Notification::make()->danger()->title('Belum bisa diproses')->body(collect($e->errors())->flatten()->first())->persistent()->send();
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $warehouseId = HpWarehouse::currentId($user);
        $opname = $this->opname();

        if (! $opname) {
            $opnames = $warehouseId
                ? StockOpname::query()
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('status', ['draft', 'in_progress'])
                    ->withCount([
                        'items',
                        'items as counted_count' => fn ($q) => $q->whereNotNull('physical_qty'),
                    ])
                    ->orderByDesc('opname_date')
                    ->get()
                : collect();

            return [
                'opname' => null,
                'warehouseId' => $warehouseId,
                'warehouseLabel' => HpWarehouse::label($warehouseId),
                'warehouseOptions' => HpWarehouse::canSwitch($user) ? HpWarehouse::options($user) : collect(),
                'opnames' => $opnames,
                'createUrl' => StockOpnameResource::canCreate() ? StockOpnameResource::getUrl('create') : null,
            ];
        }

        $items = $this->items();
        $counted = $items->filter(fn (StockOpnameItem $i) => $i->physical_qty !== null);
        $current = $this->currentItemId ? $items->firstWhere('id', $this->currentItemId) : null;

        return [
            'opname' => $opname,
            'items' => $items,
            'total' => $items->count(),
            'countedCount' => $counted->count(),
            'counted' => $counted->sortByDesc('id')->take(30),
            'current' => $current,
            'allCounted' => $items->isNotEmpty() && $counted->count() === $items->count(),
        ];
    }
}
