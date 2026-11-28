<?php

namespace App\Http\Controllers\Laporan;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Services\Reports\Center\ProductStockList;
use App\Services\Reports\Center\SalesDetailReport;
use App\Services\Reports\Center\SoldStockReport;
use App\Support\Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pusat Laporan (permintaan Ko Handy 28 Nov 2026): satu menu untuk semua laporan.
 * Detail Penjualan, Stok Barang Terjual, Cetak Banyak Produk. Excel khusus Owner / Super Admin (middleware akses).
 */
class ReportCenterController extends Controller
{
    /** Batas baris PDF agar dokumen tetap bisa dibuka; Excel tidak dibatasi serendah ini. */
    private const PDF_MAX_ROWS = 2000;

    private const EXCEL_MAX_ROWS = 50000;

    /** Daftar laporan di Pusat Laporan: [route, judul, pertanyaan yang dijawab, kelompok]. Laporan baru cukup ditambah di sini. */
    private const REPORTS = [
        ['laporan.penjualan-detail.index', 'Detail Penjualan', 'Barang apa terjual ke siapa, berapa, sudah dikirim atau belum, ada retur atau tidak.', 'Penjualan'],
        ['penjualan.laporan.index', 'Laporan Penjualan', 'Rekap per faktur / SO / DO: DPP, PPN, HPP, margin, status bayar.', 'Penjualan'],
        ['laporan.stok-terjual.index', 'Stok Barang Terjual', 'Barang yang keluar karena penjualan per gudang, dibanding stok terakhir dan stok minimum.', 'Gudang & Stok'],
        ['laporan.cetak-produk.index', 'Cetak Banyak Produk', 'Daftar stok per produk per gudang untuk rentang kode produk, siap cetak / hitung fisik.', 'Gudang & Stok'],
        ['gudang.laporan.index', 'Laporan Stok & Mutasi', 'Saldo awal, masuk, keluar, saldo akhir per produk.', 'Gudang & Stok'],
        ['pembelian.laporan.index', 'Laporan Pembelian', 'Rekap pembelian per PO / supplier.', 'Pembelian'],
        ['laporan.financial.index', 'Laporan Keuangan', 'Neraca, laba rugi, neraca saldo.', 'Keuangan'],
    ];

    public function __construct(
        private SalesDetailReport $salesDetail,
        private SoldStockReport $soldStock,
        private ProductStockList $productList,
    ) {}

    public function index(): View
    {
        $groups = collect(self::REPORTS)
            ->filter(fn ($r) => \Illuminate\Support\Facades\Route::has($r[0]) && Access::allowsRoute($r[0]))
            ->groupBy(3);

        return view('laporan.pusat.index', ['groups' => $groups]);
    }

    // ------------------------------------------------------------------ Detail Penjualan

    private function salesFilters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cabang_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ], ['date_to.after_or_equal' => 'Tanggal "sampai" harus sama atau setelah tanggal "dari".']);

        return [
            'date_from' => $data['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $data['date_to'] ?? now()->toDateString(),
            'cabang_id' => $data['cabang_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'q' => trim((string) ($data['q'] ?? '')),
        ];
    }

    public function salesDetail(Request $request): View
    {
        $filters = $this->salesFilters($request);
        $query = $this->salesDetail->query($filters, Auth::user());
        $page = (clone $query)->paginate(25)->withQueryString();

        return view('laporan.pusat.penjualan-detail', [
            'filters' => $filters,
            'summary' => $this->salesDetail->summary($query),
            'rows' => $this->salesDetail->rows($page->getCollection()),
            'page' => $page,
            'branches' => $this->branches(),
            'customer' => $filters['customer_id'] ? Customer::withoutGlobalScopes()->find($filters['customer_id'], ['id', 'name', 'code']) : null,
        ]);
    }

    public function salesDetailPdf(Request $request): Response|RedirectResponse
    {
        $filters = $this->salesFilters($request);
        $query = $this->salesDetail->query($filters, Auth::user());
        $count = (clone $query)->count();
        if ($count > self::PDF_MAX_ROWS) {
            return back()->with('error', "Terlalu banyak baris untuk PDF ({$count}). Solusi: persempit tanggal / cabang / customer (maks. " . self::PDF_MAX_ROWS . ' baris).');
        }

        return $this->pdf('laporan.pusat.pdf.penjualan-detail', [
            'filters' => $filters,
            'summary' => $this->salesDetail->summary($query),
            'rows' => $this->salesDetail->rows($query->get()),
        ], 'detail-penjualan', 'landscape');
    }

    public function salesDetailExcel(Request $request): Response|RedirectResponse
    {
        $filters = $this->salesFilters($request);
        $query = $this->salesDetail->query($filters, Auth::user());
        $rows = [];
        $query->chunk(500, function ($items) use (&$rows) {
            foreach ($this->salesDetail->rows($items) as $r) {
                $rows[] = [
                    $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/Y') : '', $r['so_number'], $r['invoice_number'], $r['tax_invoice_number'],
                    $r['branch'], $r['customer'], $r['sku'], $r['product'], $r['qty'], $r['price'], $r['discount_pct'], $r['total'],
                    $r['delivery']['label'], $r['return_qty'], $r['return_total'],
                ];
            }

            return count($rows) < self::EXCEL_MAX_ROWS;
        });

        return $this->excel(['Tanggal', 'No. SO', 'No. Faktur', 'No. E-Faktur', 'Cabang', 'Customer', 'Kode Produk', 'Produk', 'Qty', 'Harga',
            'Diskon %', 'Total', 'Status Kirim', 'Qty Retur', 'Total Retur'], $rows, 'Detail Penjualan', 'detail-penjualan');
    }

    // ------------------------------------------------------------------ Stok Barang Terjual

    private function soldFilters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cabang_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ], ['date_to.after_or_equal' => 'Tanggal "sampai" harus sama atau setelah tanggal "dari".']);

        return [
            'date_from' => $data['date_from'] ?? now()->toDateString(),
            'date_to' => $data['date_to'] ?? ($data['date_from'] ?? now()->toDateString()),
            'cabang_id' => $data['cabang_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'q' => trim((string) ($data['q'] ?? '')),
        ];
    }

    public function soldStock(Request $request): View
    {
        $filters = $this->soldFilters($request);
        $user = Auth::user();
        $page = $this->soldStock->productQuery($filters, $user)->paginate(25)->withQueryString();

        return view('laporan.pusat.stok-terjual', [
            'filters' => $filters,
            'summary' => $this->soldStock->summary($filters, $user),
            'rows' => $this->soldStock->rows($page->getCollection(), $filters, $user),
            'page' => $page,
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
        ]);
    }

    public function soldStockPdf(Request $request): Response|RedirectResponse
    {
        $filters = $this->soldFilters($request);
        $user = Auth::user();
        $products = $this->soldStock->productQuery($filters, $user)->limit(self::PDF_MAX_ROWS + 1)->get();
        if ($products->count() > self::PDF_MAX_ROWS) {
            return back()->with('error', 'Terlalu banyak produk untuk PDF. Solusi: persempit tanggal atau pilih gudang (maks. ' . self::PDF_MAX_ROWS . ' produk).');
        }

        return $this->pdf('laporan.pusat.pdf.stok-terjual', [
            'filters' => $filters,
            'summary' => $this->soldStock->summary($filters, $user),
            'rows' => $this->soldStock->rows($products, $filters, $user),
        ], 'stok-terjual', 'portrait');
    }

    public function soldStockExcel(Request $request): Response
    {
        $filters = $this->soldFilters($request);
        $user = Auth::user();
        $rows = [];
        foreach ($this->soldStock->rows($this->soldStock->productQuery($filters, $user)->limit(self::EXCEL_MAX_ROWS)->get(), $filters, $user) as $p) {
            foreach ($p['lines'] as $l) {
                $rows[] = [$p['sku'], $p['name'], $l['warehouse'], $l['warehouse_name'], $l['sold'], $l['stock'], $l['min'] ?: null,
                    ['empty' => 'Habis', 'low' => 'Di bawah minimum', 'ok' => 'Aman'][$l['flag']]];
            }
        }

        return $this->excel(['Kode Produk', 'Produk', 'Kode Gudang', 'Gudang', 'Jumlah Jual', 'Stok Terakhir', 'Stok Minimum', 'Keterangan'],
            $rows, 'Stok Barang Terjual', 'stok-terjual');
    }

    // ------------------------------------------------------------------ Cetak Banyak Produk

    private function listFilters(Request $request): array
    {
        $data = $request->validate([
            'sku_from' => ['nullable', 'string', 'max:100'],
            'sku_to' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'tipe_pajak' => ['nullable', 'in:Non Pajak,Inklusif,Eksklusif'],
            'with_value' => ['nullable', 'boolean'],
            'include_zero' => ['nullable', 'boolean'],
        ]);
        $from = trim((string) ($data['sku_from'] ?? ''));
        $to = trim((string) ($data['sku_to'] ?? ''));
        if ($from !== '' && $to !== '' && strcmp($from, $to) > 0) {
            [$from, $to] = [$to, $from];   // rentang terbalik: ditukar, bukan ditolak
        }

        return [
            'sku_from' => $from ?: null,
            'sku_to' => $to ?: null,
            'cabang_id' => $data['cabang_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'tipe_pajak' => $data['tipe_pajak'] ?? null,
            // Nilai (harga pokok) hanya untuk yang dicentang "Lihat Nilai"
            'with_value' => (bool) ($data['with_value'] ?? false) && Access::allows('laporan.cetak-produk.value'),
            'include_zero' => (bool) ($data['include_zero'] ?? false),
        ];
    }

    public function productList(Request $request): View
    {
        $filters = $this->listFilters($request);
        $query = $this->productList->query($filters, Auth::user());

        return view('laporan.pusat.cetak-produk', [
            'filters' => $filters,
            'summary' => $this->productList->summary($query),
            'page' => (clone $query)->paginate(50)->withQueryString(),
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
            'canValue' => Access::allows('laporan.cetak-produk.value'),
        ]);
    }

    public function productListPdf(Request $request): Response|RedirectResponse
    {
        $filters = $this->listFilters($request);
        $query = $this->productList->query($filters, Auth::user());
        $rows = (clone $query)->limit(self::PDF_MAX_ROWS + 1)->get();
        if ($rows->count() > self::PDF_MAX_ROWS) {
            return back()->with('error', 'Terlalu banyak baris untuk PDF. Solusi: persempit rentang kode produk atau pilih gudang (maks. ' . self::PDF_MAX_ROWS . ' baris).');
        }

        return $this->pdf('laporan.pusat.pdf.cetak-produk', [
            'filters' => $filters, 'summary' => $this->productList->summary($query), 'rows' => $rows,
        ], 'daftar-stok-produk', 'portrait');
    }

    public function productListExcel(Request $request): Response
    {
        $filters = $this->listFilters($request);
        $rows = $this->productList->query($filters, Auth::user())->limit(self::EXCEL_MAX_ROWS)->get()
            ->map(fn ($r) => array_merge(
                [$r->sku, $r->name, $r->uom, $r->tipe_pajak, $r->branch, $r->warehouse_code, $r->warehouse, (float) $r->qty],
                $filters['with_value'] ? [(float) $r->cost_price, round((float) $r->qty * (float) $r->cost_price, 2)] : []
            ))->all();
        $head = ['Kode Produk', 'Produk', 'Satuan', 'Tipe Pajak', 'Cabang', 'Kode Gudang', 'Gudang', 'Qty'];

        return $this->excel($filters['with_value'] ? [...$head, 'Harga Pokok', 'Nilai'] : $head, $rows, 'Daftar Stok Produk', 'daftar-stok-produk');
    }

    // ------------------------------------------------------------------ bantu

    private function branches()
    {
        $user = Auth::user();
        $q = Cabang::query()->orderBy('nama');
        if ($user && ! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id) {
            $q->whereKey($user->cabang_id);
        }

        return $q->get(['id', 'nama', 'kode']);
    }

    private function warehouses()
    {
        $user = Auth::user();
        $q = Warehouse::withoutGlobalScopes()->whereNull('deleted_at')->where(fn ($w) => $w->whereNull('is_transit')->orWhere('is_transit', false))->orderBy('kode');
        if ($user && ! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id) {
            $q->where('cabang_id', $user->cabang_id);
        }

        return $q->get(['id', 'kode', 'name', 'cabang_id']);
    }

    private function pdf(string $view, array $data, string $name, string $orientation): Response|RedirectResponse
    {
        try {
            return Pdf::loadView($view, $data + ['printedAt' => now(), 'printedBy' => Auth::user()?->name])
                ->setPaper('a4', $orientation)
                ->stream($name . '-' . now()->format('Ymd-His') . '.pdf');
        } catch (Throwable $e) {
            Log::error('PDF laporan gagal', ['view' => $view, 'error' => $e->getMessage()]);

            return back()->with('error', 'PDF gagal dibuat: ' . $e->getMessage() . '. Solusi: persempit filter lalu coba lagi.');
        }
    }

    private function excel(array $headings, array $rows, string $title, string $name): Response
    {
        return Excel::download(new ArrayReportExport($headings, $rows, $title), $name . '-' . now()->format('Ymd-His') . '.xlsx');
    }
}
