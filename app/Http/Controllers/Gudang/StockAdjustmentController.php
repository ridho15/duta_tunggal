<?php

namespace App\Http\Controllers\Gudang;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Penyesuaian Stok: koreksi qty per produk/rak. Disetujui lewat StockAdjustmentService (mutasi stok + jurnal).
 * Akses: Owner/Admin, Gudang, Finance (sama dengan peta menu lama); tidak ada izin Spatie khusus untuk fitur ini.
 */
class StockAdjustmentController extends Controller
{
    private const STATUSES = ['draft' => 'Draft', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

    public function __construct(private StockAdjustmentService $service) {}

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && (MenuAccess::isSuper($user) || array_intersect(['GDG', 'FIN'], MenuAccess::codesFor($user))),
            403, 'Anda tidak memiliki hak akses untuk Penyesuaian Stok.');
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $adjustments = StockAdjustment::query()->with('warehouse')->withCount('items')
            ->when(array_key_exists((string) $status, self::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('adjustment_number', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->latest('adjustment_date')->latest('id')->paginate(15)->withQueryString();

        return view('gudang.adjustment.index', ['adjustments' => $adjustments, 'statuses' => self::STATUSES, 'status' => $status, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorizeAccess();

        $adjustment = new StockAdjustment(['adjustment_date' => now(), 'adjustment_number' => StockAdjustment::generateAdjustmentNumber()]);

        return view('gudang.adjustment.create', ['adjustment' => $adjustment, 'warehouses' => $this->warehouses(), 'items' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $data = $this->validated($request);

        $adjNumber = $request->filled('adjustment_number')
            ? trim((string) $request->input('adjustment_number'))
            : StockAdjustment::generateAdjustmentNumber();

        $adjustment = DB::transaction(fn () => $this->persist(new StockAdjustment([
            'status' => 'draft',
            'created_by' => Auth::id(),
            'adjustment_number' => $adjNumber,
        ]), $data));

        return redirect()->route('gudang.adjustment.show', $adjustment)->with('success', "Draft penyesuaian {$adjustment->adjustment_number} berhasil dibuat.");
    }

    public function show(StockAdjustment $adjustment): View
    {
        $this->authorizeAccess();
        $adjustment->load(['warehouse', 'creator', 'approver', 'items.product', 'items.rak']);

        return view('gudang.adjustment.show', compact('adjustment'));
    }

    public function edit(StockAdjustment $adjustment): View|RedirectResponse
    {
        $this->authorizeAccess();
        if ($adjustment->status !== 'draft') {
            return redirect()->route('gudang.adjustment.show', $adjustment)->with('error', 'Hanya penyesuaian berstatus Draft yang dapat diubah.');
        }

        $items = $adjustment->items()->with(['product', 'rak'])->get()->map(fn ($i) => [
            'product_id' => $i->product_id, 'product_label' => trim(($i->product->sku ? "[{$i->product->sku}] " : '').$i->product->name),
            'rak_id' => $i->rak_id, 'adjusted_qty' => (float) $i->adjusted_qty, 'unit_cost' => (float) $i->unit_cost, 'notes' => $i->notes,
        ])->all();

        return view('gudang.adjustment.edit', ['adjustment' => $adjustment, 'warehouses' => $this->warehouses(), 'items' => $items]);
    }

    public function update(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAccess();
        if ($adjustment->status !== 'draft') {
            return redirect()->route('gudang.adjustment.show', $adjustment)->with('error', 'Hanya penyesuaian berstatus Draft yang dapat diubah.');
        }
        $data = $this->validated($request);

        DB::transaction(fn () => $this->persist($adjustment, $data));

        return redirect()->route('gudang.adjustment.show', $adjustment)->with('success', 'Penyesuaian berhasil diperbarui.');
    }

    public function destroy(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAccess();
        if (! in_array($adjustment->status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'Penyesuaian yang sudah disetujui tidak dapat dihapus.');
        }
        $adjustment->delete();

        return redirect()->route('gudang.adjustment.index')->with('success', "Penyesuaian {$adjustment->adjustment_number} dihapus.");
    }

    public function approve(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAccess();
        try {
            $this->service->approveStockAdjustment($adjustment, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return back()->with('success', 'Penyesuaian disetujui: mutasi stok dan jurnal sudah dicatat.');
    }

    public function reject(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAccess();
        if ($adjustment->status !== 'draft') {
            return back()->with('error', 'Hanya penyesuaian berstatus Draft yang dapat ditolak.');
        }
        $adjustment->update(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now()]);

        return back()->with('success', 'Penyesuaian ditolak. Stok tidak berubah.');
    }

    /** Pencarian produk aktif (maks 25) untuk baris item. */
    public function products(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        $q = trim((string) $request->query('q', ''));
        abort_if($q === '', 422, 'Kata pencarian wajib diisi.');

        $query = Product::query();
        foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }

        return response()->json($query->orderBy('name')->limit(25)->get(['id', 'sku', 'name', 'cost_price'])->map(fn ($p) => [
            'id' => $p->id, 'label' => trim(($p->sku ? "[{$p->sku}] " : '').$p->name), 'cost_price' => (float) $p->cost_price,
        ]));
    }

    public function raks(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        $warehouseId = $request->integer('warehouse_id');
        abort_unless($this->warehouses()->contains('id', $warehouseId), 403);

        return response()->json(Rak::query()->where('warehouse_id', $warehouseId)->orderBy('name')->get(['id', 'name']));
    }

    public function stock(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        abort_unless($this->warehouses()->contains('id', $request->integer('warehouse_id')), 403);

        return response()->json(['qty' => InventoryStock::freeQtyFor($request->integer('product_id') ?: null, $request->integer('warehouse_id'), $request->integer('rak_id') ?: null)]);
    }

    /** Gudang aktif yang boleh dipakai user: semua bila manage_type memuat 'all', selain itu hanya cabangnya. */
    private function warehouses()
    {
        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];

        return Warehouse::query()->where('status', 1)
            ->when(! is_array($manageType) || ! in_array('all', $manageType), fn ($q) => $q->where('cabang_id', $user?->cabang_id))
            ->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $items = collect($request->input('items', []))->map(function ($item) {
            $item['unit_cost'] = filled($item['unit_cost'] ?? null) ? MoneyHelper::safeParse($item['unit_cost']) : 0;

            return $item;
        })->all();
        $request->merge(['items' => $items]);

        $data = $request->validate([
            'adjustment_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'in:'.$this->warehouses()->pluck('id')->implode(',')],
            'adjustment_type' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.rak_id' => ['nullable', 'integer'],
            'items.*.adjusted_qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ], ['items.required' => 'Tambahkan minimal satu item.', 'warehouse_id.in' => 'Gudang tidak tersedia untuk akun Anda.']);

        foreach ($data['items'] as $i => $item) {
            if (! empty($item['rak_id']) && ! Rak::where('id', $item['rak_id'])->where('warehouse_id', $data['warehouse_id'])->exists()) {
                throw ValidationException::withMessages(["items.$i.rak_id" => 'Rak harus berasal dari gudang yang dipilih.']);
            }
        }

        return $data;
    }

    /** Simpan header + item; qty saat ini, selisih, dan nilai dihitung ulang di server. */
    private function persist(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        $adjustment->fill(collect($data)->only(['adjustment_date', 'warehouse_id', 'adjustment_type', 'reason', 'notes'])->all())->save();
        $adjustment->items()->delete();

        $sign = $data['adjustment_type'] === 'increase' ? 1 : -1;
        $errors = [];
        foreach ($data['items'] as $i => $item) {
            $current = InventoryStock::freeQtyFor((int) $item['product_id'], (int) $data['warehouse_id'], ! empty($item['rak_id']) ? (int) $item['rak_id'] : null);
            $diff = round((float) $item['adjusted_qty'] - $current, 2);
            if ($diff * $sign <= 0) {
                $errors["items.$i.adjusted_qty"] = $sign > 0
                    ? 'Qty setelah penyesuaian harus lebih besar dari qty saat ini ('.$current.').'
                    : 'Qty setelah penyesuaian harus lebih kecil dari qty saat ini ('.$current.').';

                continue;
            }
            $adjustment->items()->create([
                'product_id' => $item['product_id'], 'rak_id' => $item['rak_id'] ?? null, 'current_qty' => $current,
                'adjusted_qty' => $item['adjusted_qty'], 'difference_qty' => $diff, 'unit_cost' => $item['unit_cost'],
                'difference_value' => round($diff * (float) $item['unit_cost'], 2), 'notes' => $item['notes'] ?? null,
            ]);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $adjustment;
    }
}
