<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Services\InvoiceService;
use App\Services\SalesInvoiceLineBuilder;
use App\Services\SalesInvoiceTaxResolver;
use App\Support\CurrencyConversionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    protected InvoiceService $invoiceService;
    protected SalesInvoiceLineBuilder $lineBuilder;
    protected SalesInvoiceTaxResolver $taxResolver;

    public function __construct(
        InvoiceService $invoiceService,
        SalesInvoiceLineBuilder $lineBuilder,
        SalesInvoiceTaxResolver $taxResolver
    ) {
        $this->invoiceService = $invoiceService;
        $this->lineBuilder = $lineBuilder;
        $this->taxResolver = $taxResolver;
    }

    /**
     * Menampilkan daftar Faktur Penjualan (Sales Invoice).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');

        // Query dasar invoice penjualan
        $baseQuery = Invoice::with(['cabang', 'invoiceItem.product'])
            ->where(function ($q) {
                $q->where('from_model_type', SuratJalan::class)
                  ->orWhere('from_model_type', SaleOrder::class)
                  ->orWhereNotNull('surat_jalan_ids');
            });

        // Hitung live counter status
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'unpaid' => (clone $baseQuery)->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, 'sent'])->count(),
            'overdue' => (clone $baseQuery)->where('status', '!=', Invoice::STATUS_PAID)->where('due_date', '<', today())->count(),
            'paid' => (clone $baseQuery)->where('status', Invoice::STATUS_PAID)->count(),
        ];

        // Total nominal piutang belum tertagih
        $totalUnpaidAmount = (clone $baseQuery)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, 'sent'])
            ->sum('total');

        $query = clone $baseQuery;

        // Filter tab
        if ($tab === 'unpaid') {
            $query->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, 'sent']);
        } elseif ($tab === 'overdue') {
            $query->where('status', '!=', Invoice::STATUS_PAID)->where('due_date', '<', today());
        } elseif ($tab === 'paid') {
            $query->where('status', Invoice::STATUS_PAID);
        }

        // Filter cabang
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        // Pencarian multi-kolom
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $invoices = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->get();

        return view('penjualan.invoices.index', compact('invoices', 'counts', 'totalUnpaidAmount', 'tab', 'search', 'cabangs', 'cabangId'));
    }

    /**
     * Form Buat Faktur Penjualan (Pilih Customer > SO > centang SJ).
     */
    public function create(Request $request): View
    {
        $soId = $request->query('so_id');
        $sjId = $request->query('sj_id');

        $activeCabang = Auth::user()->cabang ?? Cabang::first();

        // Cari Surat Jalan yang Terbit (STATUS_ISSUED = 1) dan belum pernah difakturkan
        $availableSJs = SuratJalan::with([
            'deliveryOrder.salesOrders.customer',
            'deliveryOrder.deliveryOrderItem.product.uom',
            'cabang',
        ])
        ->where('status', SuratJalan::STATUS_ISSUED)
        ->orderBy('id', 'desc')
        ->get()
        ->reject(fn ($sj) => $sj->isInvoiced());

        // Kelompokkan Surat Jalan berdasarkan SO
        $groupedSJs = [];
        foreach ($availableSJs as $sj) {
            $primarySO = $sj->deliveryOrder->flatMap->salesOrders->first();
            if ($primarySO) {
                if (!isset($groupedSJs[$primarySO->id])) {
                    // Cari term customer jika ada
                    $customer = $primarySO->customer;
                    $groupedSJs[$primarySO->id] = [
                        'so_id' => $primarySO->id,
                        'so_number' => $primarySO->so_number,
                        'customer' => $customer,
                        'cabang' => $primarySO->cabang,
                        'tipe_pajak' => $primarySO->tipe_pajak ?? 'None',
                        'ppn_rate' => $primarySO->ppn_rate ?? 0,
                        'shipping_cost' => (float)$primarySO->shipping_cost,
                        'surat_jalans' => [],
                    ];
                }
                $groupedSJs[$primarySO->id]['surat_jalans'][] = $sj;
            }
        }

        return view('penjualan.invoices.create', compact('groupedSJs', 'availableSJs', 'activeCabang', 'soId', 'sjId'));
    }

    /**
     * Menyimpan dan menerbitkan Faktur Penjualan resmi.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'surat_jalan_ids' => 'required|array|min:1',
            'surat_jalan_ids.*' => 'exists:surat_jalans,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'other_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $selectedSjIds = $validated['surat_jalan_ids'];
        $sjs = SuratJalan::with([
            'deliveryOrder.salesOrders.customer',
            'deliveryOrder.deliveryOrderItem.product',
            'cabang',
        ])->whereIn('id', $selectedSjIds)->get();

        // 1. Validasi status Surat Jalan wajib Terbit
        foreach ($sjs as $sj) {
            if ($sj->status !== SuratJalan::STATUS_ISSUED) {
                throw ValidationException::withMessages([
                    'surat_jalan_ids' => "Surat Jalan {$sj->sj_number} berstatus {$sj->status_label}. Hanya Surat Jalan berstatus Terbit yang dapat dibuatkan faktur.",
                ]);
            }
            if ($sj->isInvoiced()) {
                throw ValidationException::withMessages([
                    'surat_jalan_ids' => "Surat Jalan {$sj->sj_number} sudah pernah difakturkan pada invoice aktif lain.",
                ]);
            }
        }

        // 2. Validasi keseragaman Cabang & Customer
        $cabangIds = $sjs->pluck('cabang_id')->unique()->values();
        if ($cabangIds->count() > 1) {
            throw ValidationException::withMessages([
                'surat_jalan_ids' => 'Penggabungan Surat Jalan hanya diizinkan untuk cabang yang sama.',
            ]);
        }

        $allSos = $sjs->flatMap(fn ($sj) => $sj->deliveryOrder)->flatMap(fn ($do) => $do->salesOrders)->unique('id')->values();
        if ($allSos->count() > 1) {
            throw ValidationException::withMessages([
                'surat_jalan_ids' => 'Surat Jalan dalam satu faktur harus berasal dari Sales Order yang sama.',
            ]);
        }

        $primarySo = $allSos->first();
        $customer = $primarySo?->customer;
        $cabangId = $validated['cabang_id'] ?? $sjs->first()->cabang_id ?? 1;

        $invoice = DB::transaction(function () use ($validated, $sjs, $primarySo, $customer, $cabangId) {
            $taxData = app(SalesInvoiceTaxResolver::class)->resolveFromSaleOrder($primarySo);
            $taxable = ($taxData['ppn_rate'] ?? 0) > 0;

            $invoiceNumber = $this->invoiceService->generateSalesInvoiceNumber($cabangId, $taxable);

            // Kumpulkan DO dari seluruh SJ
            $allDoIds = $sjs->flatMap(fn ($sj) => $sj->deliveryOrder->pluck('id'))->unique()->values()->all();

            // Hitung baris item barang dari SO & DO
            $lineItems = [];
            $subtotal = 0;
            $totalTax = 0;

            foreach ($sjs as $sj) {
                foreach ($sj->deliveryOrder as $do) {
                    foreach ($do->deliveryOrderItem as $doItem) {
                        $qty = (float) $doItem->quantity;
                        if ($qty <= 0) continue;

                        // Ambil harga satuan dan diskon dari SaleOrderItem terkait
                        $soItem = $doItem->saleOrderItem;
                        $unitPriceGross = $soItem ? (float)$soItem->unit_price : (float)$doItem->product->sell_price;
                        $discountPct = $soItem ? (float)$soItem->discount : 0.0;
                        $taxRate = (float)($taxData['ppn_rate'] ?? 0);
                        $taxType = $taxData['tipe_pajak'] ?? 'None';

                        $attrs = $this->lineBuilder->attributes(
                            $doItem->product_id,
                            $qty,
                            $unitPriceGross,
                            $discountPct,
                            $taxRate,
                            $taxType,
                            $doItem->product->sales_coa_id
                        );

                        $lineItems[] = $attrs;
                        $subtotal += (float)$attrs['subtotal'];
                        $totalTax += (float)$attrs['tax_amount'];
                    }
                }
            }

            $otherFee = (float) ($validated['other_fee'] ?? $primarySo->shipping_cost ?? 0);
            $totalAmount = $subtotal + $totalTax + $otherFee;

            if ($totalAmount <= 0) {
                throw ValidationException::withMessages([
                    'surat_jalan_ids' => 'Invoice bernilai Rp 0 tidak dapat diterbitkan. Pastikan harga satuan dan kuantitas valid.',
                ]);
            }

            // Simpan header invoice
            $inv = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'from_model_type' => SuratJalan::class,
                'from_model_id' => $sjs->first()->id,
                'surat_jalan_ids' => $sjs->pluck('id')->values()->all(),
                'delivery_orders' => $allDoIds,
                'invoice_date' => Carbon::parse($validated['invoice_date']),
                'due_date' => Carbon::parse($validated['due_date']),
                'cabang_id' => $cabangId,
                'currency_id' => $primarySo->currency_id ?? \App\Support\CurrencyConversionResolver::resolveCurrencyIdByCode('IDR') ?? \App\Models\Currency::first()?->id,
                'exchange_rate' => (float)($primarySo->exchange_rate ?? 1.0),
                'customer_name' => $customer->name ?? 'Customer Umum',
                'customer_phone' => $customer->phone ?? null,
                'subtotal' => $subtotal,
                'dpp' => $subtotal,
                'tax' => $totalTax,
                'other_fee' => $otherFee,
                'total' => $totalAmount,
                'tipe_pajak' => $taxData['tipe_pajak'] ?? 'None',
                'ppn_rate' => $taxData['ppn_rate'] ?? 0,
                'status' => Invoice::STATUS_SENT,
            ]);

            // Simpan baris-baris InvoiceItem
            foreach ($lineItems as $line) {
                $inv->invoiceItem()->create($line);
            }

            // Posting jurnal penjualan otomatis
            (new \App\Observers\InvoiceObserver())->postSalesInvoice($inv->fresh());

            return $inv;
        });

        return redirect()->route('penjualan.invoices.show', $invoice->id)
            ->with('success', "Faktur Penjualan {$invoice->invoice_number} berhasil diterbitkan.");
    }

    /**
     * Menampilkan detail Faktur Penjualan.
     */
    public function show(Invoice $invoice): View
    {
        $invoice->loadMissing([
            'invoiceItem.product.uom',
            'cabang',
            'accountReceivable',
        ]);

        $suratJalans = SuratJalan::whereIn('id', $invoice->surat_jalan_ids ?? [])->get();
        $primarySO = SaleOrder::find($invoice->from_model_id) ?? $suratJalans->flatMap->deliveryOrder->flatMap->salesOrders->first();

        // Hitung nominal yang sudah dibayar
        $paidAmount = (float) ($invoice->accountReceivable?->paid ?? 0);
        $remainingAmount = max(0, (float)$invoice->total - $paidAmount);

        return view('penjualan.invoices.show', compact('invoice', 'suratJalans', 'primarySO', 'paidAmount', 'remainingAmount'));
    }

    /**
     * Cetak PDF Faktur Penjualan resmi (Kop, NPWP, Rekening transfer, rincian PPN).
     */
    public function pdf(Invoice $invoice)
    {
        $invoice->loadMissing(['invoiceItem.product.uom', 'cabang']);

        $pdf = Pdf::loadView('pdf.sale-order-invoice', compact('invoice'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream("Invoice-Penjualan-{$invoice->invoice_number}.pdf");
    }

    /**
     * Mengisi/mengubah No. Faktur Pajak 16 digit pada Faktur Penjualan resmi (UAT-INV-04).
     */
    public function updateTaxNumber(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'tax_invoice_number' => ['nullable', 'string', 'max:50', new \App\Rules\TaxInvoiceNumber($invoice->getKey())],
        ]);

        app(\App\Services\SalesInvoiceTaxNumber::class)->set($invoice, $validated['tax_invoice_number'] ?? null);

        $msg = filled($validated['tax_invoice_number'] ?? null)
            ? 'No. Faktur Pajak disimpan. Jurnal dan piutang tidak berubah.'
            : 'No. Faktur Pajak dihapus.';

        return redirect()->route('penjualan.invoices.show', $invoice->id)->with('success', $msg);
    }
}
