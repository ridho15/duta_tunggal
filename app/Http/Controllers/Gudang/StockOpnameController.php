<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use App\Support\MenuAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameController extends Controller
{
    protected StockOpnameService $stockOpnameService;

    public function __construct(StockOpnameService $stockOpnameService)
    {
        $this->stockOpnameService = $stockOpnameService;
    }

    /**
     * Menampilkan daftar sesi Stock Opname.
     */
    public function index(Request $request)
    {
        $this->authorizeAccess('view any inventory stock');

        $search = trim($request->input('search', ''));
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $status = $request->input('status', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = StockOpname::query()
            ->with([
                'warehouse',
                'creator',
                'approver',
                'items',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('opname_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } elseif ($startDate) {
            $query->where('opname_date', '>=', Carbon::parse($startDate)->startOfDay());
        } elseif ($endDate) {
            $query->where('opname_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $kpi = [
            'total' => StockOpname::count(),
            'draft' => StockOpname::where('status', 'draft')->count(),
            'in_progress' => StockOpname::where('status', 'in_progress')->count(),
            'completed' => StockOpname::where('status', 'completed')->count(),
            'approved' => StockOpname::where('status', 'approved')->count(),
        ];

        $opnames = $query->orderByDesc('opname_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        return view('gudang.opname.index', compact(
            'opnames',
            'warehouses',
            'kpi',
            'search',
            'warehouseId',
            'status',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Form pembukaan sesi Stock Opname baru.
     */
    public function create()
    {
        $this->authorizeAccess('create inventory stock');

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $suggestedNumber = StockOpname::generateOpnameNumber();

        return view('gudang.opname.create', compact('warehouses', 'suggestedNumber'));
    }

    /**
     * Menyimpan sesi Stock Opname baru (status: draft).
     */
    public function store(Request $request)
    {
        $this->authorizeAccess('create inventory stock');

        $validated = $request->validate([
            'opname_number' => 'nullable|string|max:50',
            'opname_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $opnameNumber = $validated['opname_number'] ?: StockOpname::generateOpnameNumber();

        $opname = StockOpname::create([
            'opname_number' => $opnameNumber,
            'opname_date' => $validated['opname_date'],
            'warehouse_id' => $validated['warehouse_id'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($request->input('action') === 'start_immediately') {
            $this->stockOpnameService->startPhysicalCount($opname);
            return redirect()->route('gudang.opname.show', $opname)
                ->with('success', "Sesi opname {$opname->opname_number} berhasil dibuka dan proses hitung fisik telah dimulai.");
        }

        return redirect()->route('gudang.opname.show', $opname)
            ->with('success', "Sesi opname {$opname->opname_number} berhasil dibuat (Status: Draft).");
    }

    /**
     * Halaman Workspace Hitung Fisik / Detail Stock Opname.
     */
    public function show(StockOpname $opname)
    {
        $this->authorizeAccess('view any inventory stock');

        $opname->load([
            'warehouse',
            'creator',
            'approver',
            'items.product.uom',
            'items.rak',
        ]);

        // Ringkasan metrik audit opname
        $items = $opname->items;
        $summary = [
            'total_items' => $items->count(),
            'counted_items' => $items->whereNotNull('physical_qty')->count(),
            'uncounted_items' => $items->whereNull('physical_qty')->count(),
            'matched_items' => $items->whereNotNull('physical_qty')->filter(fn ($i) => (float) $i->difference_qty === 0.0)->count(),
            'surplus_items' => $items->whereNotNull('physical_qty')->filter(fn ($i) => (float) $i->difference_qty > 0.0)->count(),
            'deficit_items' => $items->whereNotNull('physical_qty')->filter(fn ($i) => (float) $i->difference_qty < 0.0)->count(),
            'total_system_qty' => $items->sum('system_qty'),
            'total_physical_qty' => $items->whereNotNull('physical_qty')->sum('physical_qty'),
            'total_diff_qty' => $items->whereNotNull('physical_qty')->sum('difference_qty'),
            'total_diff_value' => $items->whereNotNull('physical_qty')->sum('difference_value'),
        ];

        return view('gudang.opname.show', compact('opname', 'summary'));
    }

    /**
     * Memulai hitung fisik (Draft -> In Progress).
     */
    public function startCount(StockOpname $opname)
    {
        $this->authorizeAccess('create inventory stock');

        try {
            $count = $this->stockOpnameService->startPhysicalCount($opname);
            return redirect()->route('gudang.opname.show', $opname)
                ->with('success', "Hitung fisik dimulai! Sebanyak {$count} item produk berhasil dimuat ke lembar kerja opname.");
        } catch (ValidationException $e) {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Menyimpan progres hasil hitung fisik dari form input tabel.
     */
    public function saveProgress(Request $request, StockOpname $opname)
    {
        $this->authorizeAccess('create inventory stock');

        if ($opname->status === 'approved') {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', 'Stock opname yang sudah disetujui tidak dapat diubah.');
        }

        $inputCounts = $request->input('counts', []);
        $inputNotes = $request->input('notes', []);

        DB::transaction(function () use ($inputCounts, $inputNotes, $opname) {
            foreach ($inputCounts as $itemId => $val) {
                $item = StockOpnameItem::where('stock_opname_id', $opname->id)->find($itemId);
                if (! $item) {
                    continue;
                }

                $physicalQty = ($val === '' || $val === null) ? null : (float) $val;
                $note = $inputNotes[$itemId] ?? $item->notes;

                $item->physical_qty = $physicalQty;
                $item->notes = $note;
                $item->save(); // Observer booted() will calculate difference_qty and values
            }
        });

        if ($request->input('action') === 'complete') {
            return $this->completeCount($opname);
        }

        return redirect()->route('gudang.opname.show', $opname)
            ->with('success', 'Progres penghitungan fisik berhasil disimpan.');
    }

    /**
     * Menandai selesai hitung fisik (In Progress -> Completed).
     */
    public function completeCount(StockOpname $opname)
    {
        $this->authorizeAccess('create inventory stock');

        try {
            $this->stockOpnameService->completePhysicalCount($opname);
            return redirect()->route('gudang.opname.show', $opname)
                ->with('success', 'Penghitungan fisik telah selesai! Seluruh selisih siap direview dan disetujui.');
        } catch (ValidationException $e) {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Mengembalikan status dari Completed kembali ke In Progress (jika perlu hitung ulang).
     */
    public function recount(StockOpname $opname)
    {
        $this->authorizeAccess('create inventory stock');

        if ($opname->status !== 'completed') {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', 'Hanya stock opname berstatus selesai yang dapat dihitung ulang.');
        }

        $opname->update(['status' => 'in_progress']);

        return redirect()->route('gudang.opname.show', $opname)
            ->with('success', 'Sesi opname dikembalikan ke status Sedang Dihitung untuk koreksi kuantitas fisik.');
    }

    /**
     * Menyetujui Stock Opname, membuat mutasi penyesuaian stok dan jurnal GL.
     */
    public function approve(StockOpname $opname)
    {
        $this->authorizeAccess('approve warehouse');

        try {
            $this->stockOpnameService->approveStockOpname($opname, Auth::id());
            return redirect()->route('gudang.opname.show', $opname)
                ->with('success', 'Stock opname berhasil disetujui! Penyesuaian saldo stok fisik dan Jurnal Umum persediaan telah dibukukan otomatis.');
        } catch (ValidationException $e) {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Menghapus sesi Stock Opname (hanya saat draft atau belum disetujui).
     */
    public function destroy(StockOpname $opname)
    {
        $this->authorizeAccess('delete inventory stock');

        if ($opname->status === 'approved' && !MenuAccess::isSuper(Auth::user())) {
            return redirect()->route('gudang.opname.show', $opname)
                ->with('error', 'Stock opname yang telah disetujui tidak dapat dihapus.');
        }

        $number = $opname->opname_number;
        $opname->items()->delete();
        $opname->delete();

        return redirect()->route('gudang.opname.index')
            ->with('success', "Stock opname {$number} berhasil dihapus.");
    }

    /**
     * Cetak Lembar Kerja / Berita Acara Stock Opname (PDF).
     */
    public function pdf(StockOpname $opname)
    {
        $this->authorizeAccess('view any inventory stock');

        $opname->load([
            'warehouse',
            'creator',
            'approver',
            'items.product.uom',
            'items.rak',
        ]);

        $pdf = Pdf::loadView('pdf.gudang.berita-acara-opname', compact('opname'))
            ->setPaper('a4', 'landscape');

        $filename = ($opname->status === 'draft')
            ? "Form-Hitung-Fisik-{$opname->opname_number}.pdf"
            : "Berita-Acara-Opname-{$opname->opname_number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Ekspor Laporan Rekapitulasi Opname ke format CSV/Excel.
     */
    public function excel(StockOpname $opname)
    {
        $this->authorizeAccess('view any inventory stock');

        $opname->load(['warehouse', 'items.product.uom', 'items.rak']);

        $filename = "Rekap-Opname-{$opname->opname_number}-" . now()->format('Ymd') . ".csv";

        return response()->streamDownload(function () use ($opname) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['REKAPITULASI HASIL STOCK OPNAME - CV. DUTA TUNGGAL']);
            fputcsv($handle, ['Nomor Opname', $opname->opname_number]);
            fputcsv($handle, ['Tanggal', Carbon::parse($opname->opname_date)->format('d/m/Y')]);
            fputcsv($handle, ['Gudang', $opname->warehouse?->name ?? '-']);
            fputcsv($handle, ['Status', strtoupper($opname->status)]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'No',
                'Kode / SKU',
                'Nama Produk',
                'Satuan',
                'Lokasi Rak',
                'Stok Sistem',
                'Hasil Fisik',
                'Selisih Qty',
                'HPP / Biaya Satuan',
                'Selisih Nilai (Rp)',
                'Catatan',
            ]);

            foreach ($opname->items as $idx => $item) {
                fputcsv($handle, [
                    $idx + 1,
                    $item->product?->sku ?? '-',
                    $item->product?->name ?? '-',
                    $item->product?->uom?->name ?? 'Pcs',
                    $item->rak?->name ?? '-',
                    $item->system_qty ?? 0,
                    $item->physical_qty ?? '-',
                    $item->difference_qty ?? '-',
                    $item->unit_cost ?? 0,
                    $item->difference_value ?? '-',
                    $item->notes ?? '',
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['', '', '', '', 'Total Selisih Nilai:', '', '', '', '', $opname->items->sum('difference_value')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Helper otorisasi peran / permission.
     */
    protected function authorizeAccess(string $permission): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (MenuAccess::isSuper($user) || in_array('GDG', MenuAccess::codesFor($user))) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk fitur ini.');
    }
}
